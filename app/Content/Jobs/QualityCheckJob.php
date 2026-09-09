<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Generation\ContextAssembler;
use App\Content\Generation\FixSectionsStep;
use App\Content\Generation\GenerationContext;
use App\Content\Generation\HtmlAssembler;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\FactSnippet;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Quality\FactChecker;
use App\Content\Quality\LinkChecker;
use App\Content\Quality\ReadabilityScorer;
use App\Content\Quality\RubricEvaluator;
use App\Content\Quality\SeoLinter;
use App\Content\Services\RegionScopeResolver;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Qualitaetsgate eines Artikelentwurfs (#15).
 *
 * Drei Stufen, in dieser Reihenfolge und bewusst von billig nach teuer:
 *
 *  1. SeoLinter — deterministisch, ohne Modellaufruf, Regelwerk in
 *     config/content_seo_rules.php.
 *  2. FactChecker — jede Zahl im Text gegen die Faktenschnipsel und
 *     Quellenausschnitte des Entwurfs. Eine unbelegte Zahl ist blockierend,
 *     unabhaengig von jedem Score.
 *  3. RubricEvaluator — die redaktionelle Rubrik (#13) mit claude-sonnet-5.
 *
 * `quality_score` ist die gewichtete Kombination der Teilnoten
 * (config('content.quality.weights')). Freigegeben wird nur, wer die Schwelle
 * erreicht UND keine blockierende Beanstandung hat.
 *
 * Bleibt der Entwurf darunter, laeuft genau ein Fix-Durchlauf: der
 * FixSectionsStep ueberarbeitet die in `fix_instructions` genannten
 * Abschnitte, danach wird vollstaendig neu bewertet. Bei einer
 * Aktualisierung (#24) ist dieser Durchlauf auf die Abschnitte begrenzt, die
 * im juengsten Eintrag des `changelog_json` stehen — Hinweise zu fremden
 * Abschnitten bleiben im Bericht und in der Note, loesen aber keinen
 * Schreibaufruf aus (#96). Scheitert auch das,
 * geht der Entwurf in die manuelle Pruefung; solange das Tageskontingent des
 * Slots reicht, rueckt zusaetzlich der beste Reserve-Kandidat nach. Ist es
 * erschoepft, traegt der Bericht die Marke `quality.at_risk` — daraus baut
 * die Uebersicht (#19) den Alarm „Tagesziel gefaehrdet".
 */
class QualityCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Wiederholungen organisiert das Gate selbst (Fix-Durchlauf,
     * Reserve-Kandidat). Ein Queue-Retry wuerde dieselbe Bewertung ein
     * zweites Mal bezahlen.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $tenantId,
        public int $draftId,
        public int $slot = 1,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.llm', 'content-llm'));
    }

    public function uniqueId(): string
    {
        return "content-quality-check:{$this->tenantId}:{$this->draftId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        ContextAssembler $assembler,
        SeoLinter $linter,
        FactChecker $factChecker,
        LinkChecker $linkChecker,
        ReadabilityScorer $readability,
        RubricEvaluator $rubric,
        FixSectionsStep $fixStep,
        HtmlAssembler $html,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use (
            $tenant, $assembler, $linter, $factChecker, $linkChecker, $readability, $rubric, $fixStep, $html
        ): void {
            $draft = ArticleDraft::query()->find($this->draftId);

            if ($draft === null) {
                Log::warning('Qualitaetsgate ohne Entwurf.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $this->draftId,
                ]);

                return;
            }

            if ($draft->status !== DraftStatus::CHECKING && ! $draft->status->canTransitionTo(DraftStatus::CHECKING)) {
                Log::info('Qualitaetsgate uebersprungen, Status passt nicht.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $draft->getKey(),
                    'status' => $draft->status->value,
                ]);

                return;
            }

            $draft->transitionTo(DraftStatus::CHECKING);

            $context = $this->context($assembler, $tenant, $draft);

            $evaluation = $this->evaluate($draft, $context, $linter, $factChecker, $linkChecker, $readability, $rubric, $html);

            if ($evaluation['approved']) {
                $this->approve($tenant, $draft, $evaluation, fixRuns: 0);

                return;
            }

            // Genau ein Fix-Durchlauf. Er fasst ausschliesslich die Abschnitte
            // an, die in fix_instructions stehen — bei einer Aktualisierung
            // zusaetzlich nur die, die diese Aktualisierung selbst geaendert
            // hat (#96).
            if ($context !== null && $this->applyFixes($draft, $context, $evaluation, $fixStep, $html)) {
                $evaluation = $this->evaluate($draft, $context, $linter, $factChecker, $linkChecker, $readability, $rubric, $html);

                if ($evaluation['approved']) {
                    $this->approve($tenant, $draft, $evaluation, fixRuns: 1);

                    return;
                }

                $this->sendToReview($draft, $evaluation, fixRuns: 1);

                return;
            }

            $this->sendToReview($draft, $evaluation, fixRuns: 0);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Bewertung
    |--------------------------------------------------------------------------
    */

    /**
     * Alle drei Stufen und die gewichtete Gesamtnote.
     *
     * @return array<string, mixed>
     */
    private function evaluate(
        ArticleDraft $draft,
        ?GenerationContext $context,
        SeoLinter $linter,
        FactChecker $factChecker,
        LinkChecker $linkChecker,
        ReadabilityScorer $readability,
        RubricEvaluator $rubric,
        HtmlAssembler $html,
    ): array {
        $body = (string) $draft->body_html;
        $settings = TenantContentSetting::current();
        $snippets = $this->factSnippets($draft, $context);
        $sources = $draft->sources()->get();

        $linkResults = $linkChecker->check($html->links($body, internal: false));
        $readabilityResult = $readability->score($body);
        $factResult = $factChecker->check($body, $snippets, $sources);

        $lint = $linter->lint($draft, [
            'primary_keyword' => $context?->primaryKeyword ?? (string) $draft->topicCandidate?->primary_keyword,
            'region_scope' => (string) $draft->region_scope,
            'region_name' => $context?->regionName ?? $this->regionName($draft),
            'is_ymyl' => (bool) $settings->is_ymyl,
            'link_results' => $linkResults,
            'readability' => $readabilityResult,
            'has_regional_fact' => $this->hasRegionalFact($draft, $snippets),
            'intent' => $context?->intent,
            'target_words' => $context?->targetWords,
        ]);

        $sections = FixSectionsStep::split($body);
        $rubricResult = null;
        $rubricError = null;

        if ($context !== null) {
            try {
                $rubricResult = $rubric->evaluate($context, $draft, $sections);
            } catch (BudgetExceededException|LlmSchemaException $exception) {
                $rubricError = $exception->getMessage();

                Log::warning('Rubrik-Bewertung nicht moeglich.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $draft->getKey(),
                    'exception' => $rubricError,
                ]);
            }
        }

        $blocking = array_values(array_unique(array_merge(
            $lint['blocking'],
            $factResult['blocking'],
            $rubricResult['blocking_issues'] ?? [],
            $rubricResult === null
                ? [__('Die redaktionelle Rubrik konnte nicht bewertet werden; eine automatische Freigabe ist damit ausgeschlossen.')]
                : [],
        )));

        $finalScore = $this->finalScore($lint['score'], $factResult['score'], $readabilityResult['score'], $rubricResult['score'] ?? null);
        $threshold = $this->threshold($settings);

        return [
            'approved' => $finalScore >= $threshold && $blocking === [],
            'final_score' => $finalScore,
            'threshold' => $threshold,
            'seo_lint' => $lint['rules'],
            'seo_score' => $lint['score'],
            'fact_check' => $factResult['facts'],
            'fact_score' => $factResult['score'],
            'link_check' => $linkResults,
            'readability' => $readabilityResult,
            'rubric' => $rubricResult,
            'rubric_error' => $rubricError,
            'blocking_issues' => $blocking,
            'sections' => $sections,
        ];
    }

    /**
     * Gewichtete Kombination der Teilnoten. Eine fehlende Teilnote (etwa die
     * Rubrik bei erschoepftem Budget) faellt heraus, die uebrigen Gewichte
     * werden auf 1.0 normiert — sonst wuerde ein ausgefallener Provider den
     * Gesamtscore rechnerisch halbieren.
     */
    private function finalScore(float $seo, float $fact, float $readability, ?float $rubric): float
    {
        $weights = (array) config('content.quality.weights', []);

        $parts = [
            'rubric' => $rubric,
            'seo' => $seo,
            'fact' => $fact,
            'readability' => max(0.0, min(100.0, $readability)),
        ];

        $sum = 0.0;
        $total = 0.0;

        foreach ($parts as $key => $value) {
            if ($value === null) {
                continue;
            }

            $weight = (float) ($weights[$key] ?? 0);

            if ($weight <= 0.0) {
                continue;
            }

            $sum += $weight * $value;
            $total += $weight;
        }

        return $total <= 0.0 ? 0.0 : round($sum / $total, 1);
    }

    /**
     * Freigabeschwelle des Mandanten. Ein gepflegter Wert gilt unveraendert;
     * ohne Pflege liegt die Schwelle fuer YMYL-Mandanten hoeher als sonst
     * (Abnahme #15).
     */
    private function threshold(TenantContentSetting $settings): int
    {
        $configured = (int) $settings->auto_publish_threshold;

        if ($configured > 0) {
            return $configured;
        }

        return (bool) $settings->is_ymyl
            ? (int) config('content.quality.ymyl_auto_approve_score', 90)
            : (int) config('content.quality.auto_approve_score', 85);
    }

    /*
    |--------------------------------------------------------------------------
    | Entscheidungen
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function approve(Tenant $tenant, ArticleDraft $draft, array $evaluation, int $fixRuns): void
    {
        $this->store($draft, $evaluation, $fixRuns, 'approved', atRisk: false);
        $draft->transitionTo(DraftStatus::APPROVED);

        Log::info('Artikel automatisch freigegeben.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'score' => $evaluation['final_score'],
            'threshold' => $evaluation['threshold'],
            'fix_runs' => $fixRuns,
        ]);

        $this->dispatchFollowUps($tenant, $draft);
    }

    /**
     * Nicht freigabefaehig: der Entwurf geht in die Pruef-Queue. Solange das
     * Tageskontingent des Slots reicht, rueckt zusaetzlich der beste
     * Reserve-Kandidat nach — der Slot soll trotzdem einen Artikel bekommen.
     *
     * @param  array<string, mixed>  $evaluation
     */
    private function sendToReview(ArticleDraft $draft, array $evaluation, int $fixRuns): void
    {
        $attempt = max(1, (int) $draft->attempt);
        $max = max(1, (int) config('content.generation.max_attempts_per_slot', 3));

        // Eine Aktualisierung (#24) haengt an keinem Tages-Slot: sie hat
        // keinen Reserve-Kandidaten, und ihr Scheitern gefaehrdet das
        // Tagesziel nicht. Der Artikel steht weiter live, nur eben im alten
        // Stand — die neue Fassung wartet in der Pruef-Queue.
        $reserve = ! $draft->isRefresh() && $attempt < $max ? $this->reserveCandidate($draft) : null;
        $atRisk = $reserve === null && ! $draft->isRefresh();

        $this->store($draft, $evaluation, $fixRuns, 'review', $atRisk);
        $draft->transitionTo(DraftStatus::REVIEW);

        Log::warning('Qualitaetsgate nicht bestanden, Entwurf in der manuellen Pruefung.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'score' => $evaluation['final_score'],
            'threshold' => $evaluation['threshold'],
            'blocking_issues' => count((array) $evaluation['blocking_issues']),
            'attempt' => $attempt,
            'fix_runs' => $fixRuns,
        ]);

        if ($reserve === null) {
            if ($draft->isRefresh()) {
                return;
            }

            Log::warning('Tagesziel gefaehrdet: kein weiterer Versuch fuer den Slot.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'slot' => $this->slot($draft),
                'attempt' => $attempt,
            ]);

            return;
        }

        $reserve->forceFill([
            'status' => TopicStatus::SELECTED,
            'selected_for_date' => $draft->topicCandidate?->selected_for_date ?? now()->toDateString(),
        ])->save();

        GenerateDraftJob::dispatch($this->tenantId, null, (int) $reserve->getKey(), $this->slot($draft), $attempt + 1);
    }

    /**
     * Der beste Reserve-Kandidat des Tages, ohne das gescheiterte Thema.
     */
    private function reserveCandidate(ArticleDraft $draft): ?TopicCandidate
    {
        $topic = $draft->topicCandidate;
        $date = $topic?->selected_for_date ?? now();

        return TopicCandidate::query()
            ->reserveFor($date)
            ->when($topic !== null, fn (Builder $query) => $query->whereKeyNot($topic->getKey()))
            ->first();
    }

    /**
     * Folgejob der Freigabe. Wie ueberall in der Pipeline wird genau der
     * naechste Schritt angestossen: die Asset-Erzeugung (#16), die ihrerseits
     * den Publisher (#21) startet. Fehlt der Job noch, bleibt es beim
     * Statuswechsel, aus dem der spaetere Lauf ohnehin zieht — dieselbe Regel
     * wie im ReviewQueueService.
     */
    private function dispatchFollowUps(Tenant $tenant, ArticleDraft $draft): void
    {
        // Eine Aktualisierung (#24) geht sofort live und nicht in einen Slot:
        // der Artikel steht bereits, die neue Fassung soll ihn moeglichst
        // schnell ersetzen. Bilder braucht sie auch keine — sie hat die des
        // Erstentwurfs geerbt.
        if ($draft->isRefresh()) {
            PublishDraftJob::dispatch((int) $tenant->getKey(), (int) $draft->getKey());

            return;
        }

        foreach (['App\\Content\\Jobs\\GenerateAssetsJob', 'App\\Content\\Jobs\\ScheduleAndPublishJob'] as $job) {
            if (! class_exists($job)) {
                continue;
            }

            try {
                $job::dispatch((int) $tenant->getKey(), (int) $draft->getKey());
            } catch (Throwable $exception) {
                Log::error('Qualitaetsgate: Folgejob konnte nicht angestossen werden.', [
                    'job' => $job,
                    'tenant_id' => $tenant->getKey(),
                    'draft_id' => $draft->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }

            // Der erste vorhandene Job der Kette genuegt; er zieht den Rest
            // nach. Sonst liefe der Publisher gegen einen Entwurf ohne Bilder.
            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fix-Durchlauf
    |--------------------------------------------------------------------------
    */

    /**
     * Ueberarbeitet die beanstandeten Abschnitte. Rueckgabe false, wenn es
     * nichts Abschnittsbezogenes zu tun gibt oder der Durchlauf schon gelaufen
     * ist — dann geht der Entwurf ohne zweiten Modellaufwand in die Pruefung.
     *
     * @param  array<string, mixed>  $evaluation
     */
    private function applyFixes(
        ArticleDraft $draft,
        GenerationContext $context,
        array $evaluation,
        FixSectionsStep $fixStep,
        HtmlAssembler $html,
    ): bool {
        $report = (array) ($draft->quality_report_json ?? []);

        if ((int) Arr::get($report, 'quality.fix_runs', 0) > 0) {
            return false;
        }

        $sections = (array) $evaluation['sections'];
        $byId = $this->instructionsBySection($evaluation, $sections);

        // Bei einer Aktualisierung (#24) darf der Korrekturlauf nur die
        // Abschnitte anfassen, die im Aenderungshinweis dieser Fassung
        // stehen. Alles andere ist Text der Elternfassung: er war schon
        // einmal durch das Gate, er steht so live, und die Gegenueberstellung
        // (#20) soll genau die Aktualisierung zeigen und nicht den stillen
        // Umbau des restlichen Artikels (#96).
        $writable = $this->writableSections($draft);

        if ($writable !== null) {
            $foreign = array_keys(array_diff_key($byId, array_flip($writable)));
            $byId = array_intersect_key($byId, array_flip($writable));

            if ($foreign !== []) {
                Log::info('Korrekturlauf ausserhalb der Aktualisierung uebersprungen.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $draft->getKey(),
                    'writable' => $writable,
                    'skipped' => $foreign,
                ]);
            }
        }

        if ($byId === []) {
            return false;
        }

        $internalUrls = array_map(
            static fn (array $target): string => (string) $target['url'],
            $context->linkTargets,
        );
        $externalUrls = $draft->sources()
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url) && str_starts_with($url, 'http'))
            ->values()
            ->all();

        $changed = 0;

        foreach ($sections as $index => $section) {
            $instructions = $byId[$section['id']] ?? [];

            if ($instructions === []) {
                continue;
            }

            try {
                $revised = $fixStep->run($context, $draft, $section, $instructions);
            } catch (BudgetExceededException|LlmSchemaException $exception) {
                Log::warning('Fix-Durchlauf abgebrochen.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $draft->getKey(),
                    'section' => $section['id'],
                    'exception' => $exception->getMessage(),
                ]);

                break;
            }

            $revised['body'] = $html->sanitize($revised['body'], $internalUrls, $externalUrls);

            if (trim($revised['body']) === '') {
                continue;
            }

            $sections[$index] = $revised;
            $changed++;
        }

        if ($changed === 0) {
            return false;
        }

        $body = FixSectionsStep::join($sections);

        $outline = (array) ($draft->outline_json ?? []);
        $outline['word_count'] = $html->wordCount($body);
        $outline['internal_links'] = $html->links($body, internal: true);
        $outline['external_links'] = $html->links($body, internal: false);

        $draft->forceFill([
            'body_html' => $body,
            'outline_json' => $outline,
        ])->save();

        Log::info('Fix-Durchlauf abgeschlossen.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'sections' => $changed,
        ]);

        return true;
    }

    /**
     * Korrekturhinweise je Abschnitt: die abschnittsbezogenen Hinweise der
     * Rubrik und zusaetzlich jede unbelegte Zahl, zugeordnet ueber den
     * Abschnitt, in dem sie steht.
     *
     * @param  array<string, mixed>  $evaluation
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     * @return array<string, array<int, string>>
     */
    private function instructionsBySection(array $evaluation, array $sections): array
    {
        $byId = [];

        foreach ((array) ($evaluation['rubric']['fix_instructions'] ?? []) as $entry) {
            $id = (string) ($entry['section_id'] ?? '');

            if ($id === '') {
                continue;
            }

            $byId[$id][] = (string) $entry['instruction'];
        }

        foreach ((array) $evaluation['fact_check'] as $fact) {
            if (($fact['status'] ?? '') !== FactChecker::STATUS_MISSING) {
                continue;
            }

            $number = (string) $fact['zahl'];

            foreach ($sections as $section) {
                if (! str_contains(strip_tags($section['body']), $number)) {
                    continue;
                }

                $byId[$section['id']][] = __('Die Zahl „:number" ist durch keine Quelle belegt. Streichen Sie die Angabe oder ersetzen Sie sie durch einen belegten Wert aus der Faktenliste.', [
                    'number' => $number,
                ]);

                break;
            }
        }

        return array_map(
            static fn (array $instructions): array => array_values(array_unique($instructions)),
            $byId,
        );
    }

    /**
     * Die Abschnitte, die dieser Lauf ueberschreiben darf.
     *
     * `null` heisst „keine Begrenzung" — das ist der Normalfall eines
     * Erstentwurfs, bei dem der ganze Text aus diesem Lauf stammt. Eine
     * Aktualisierung (#24) verantwortet dagegen nur die Abschnitte, die im
     * juengsten Eintrag ihres `changelog_json` stehen; genau diese Liste
     * nennt auch der oeffentliche Aenderungshinweis unter dem Artikel.
     *
     * Steht dort nichts, ist die Liste leer und der Korrekturlauf faellt
     * ganz aus — lieber eine Fassung in der manuellen Pruefung als eine, die
     * mehr aendert als sie ausweist.
     *
     * @return array<int, string>|null
     */
    private function writableSections(ArticleDraft $draft): ?array
    {
        if (! $draft->isRefresh()) {
            return null;
        }

        // Bewusst der Rohwert und nicht changelogEntries(): dort faellt ein
        // Eintrag ohne Zusammenfassung heraus, und der letzte verbliebene
        // waere dann der geerbte der Elternfassung — also fremde Abschnitte.
        $entries = array_values(array_filter((array) ($draft->changelog_json ?? []), 'is_array'));
        $latest = $entries === [] ? [] : $entries[array_key_last($entries)];

        $ids = array_filter(
            array_map(static fn ($id): string => trim((string) $id), (array) ($latest['sections'] ?? [])),
            static fn (string $id): bool => $id !== '',
        );

        return array_values(array_unique($ids));
    }

    /**
     * Korrekturhinweise zu Abschnitten, die diese Aktualisierung nicht
     * geaendert hat. Sie bleiben im Bericht sichtbar und gehen ueber die
     * Rubriknote in den Score ein, loesen aber keinen Schreibaufruf aus (#96).
     *
     * @param  array<string, mixed>  $evaluation
     * @param  array<int, string>  $writable
     * @return array<int, array{section_id: string, heading: string, instructions: array<int, string>}>
     */
    private function outOfScopeInstructions(array $evaluation, array $writable): array
    {
        $sections = (array) $evaluation['sections'];
        $byId = $this->instructionsBySection($evaluation, $sections);
        $headings = [];

        foreach ($sections as $section) {
            $headings[(string) $section['id']] = (string) $section['heading'];
        }

        $skipped = [];

        foreach ($byId as $id => $instructions) {
            if (in_array((string) $id, $writable, true)) {
                continue;
            }

            $skipped[] = [
                'section_id' => (string) $id,
                'heading' => $headings[(string) $id] ?? '',
                'instructions' => array_values($instructions),
            ];
        }

        return $skipped;
    }

    /**
     * Klartexthinweis fuer die Pruefflaeche: welcher fremde Abschnitt die
     * Fassung aufhaelt und warum er nicht automatisch umgeschrieben wurde.
     *
     * @param  array<int, string>  $writable
     * @param  array<int, array{section_id: string, heading: string, instructions: array<int, string>}>  $skipped
     */
    private function outOfScopeNote(array $writable, array $skipped): string
    {
        $label = static function (array $entry): string {
            $heading = trim($entry['heading']);

            return $heading === ''
                ? "[{$entry['section_id']}]"
                : "[{$entry['section_id']}] {$heading}";
        };

        return __('Diese Aktualisierung hat nur :own geaendert. Beanstandet werden zusaetzlich :foreign — Abschnitte aus der Vorfassung, die der automatische Korrekturlauf bewusst nicht anfasst. Bitte von Hand entscheiden, ob der Artikel dafuer eine eigene Ueberarbeitung braucht.', [
            'own' => $writable === [] ? __('keinen Abschnitt') : implode(', ', array_map(static fn (string $id): string => "[{$id}]", $writable)),
            'foreign' => implode('; ', array_map($label, $skipped)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Bericht
    |--------------------------------------------------------------------------
    */

    /**
     * Schreibt `quality_report_json` und `quality_score`.
     *
     * Der Bericht traegt die drei Stufen getrennt (`seo_lint`, `fact_check`,
     * `rubric`) und spiegelt zusaetzlich `per_criterion`, `blocking_issues`
     * und `fix_instructions` auf die oberste Ebene: dort liest sie der
     * QualityReportPresenter der Pruefflaeche (#20), und dort legt auch die
     * Redaktion ihre eigenen Hinweise ab.
     *
     * @param  array<string, mixed>  $evaluation
     */
    private function store(ArticleDraft $draft, array $evaluation, int $fixRuns, string $decision, bool $atRisk): void
    {
        $report = (array) ($draft->quality_report_json ?? []);
        $rubric = $evaluation['rubric'];

        $report['score'] = $evaluation['final_score'];
        $report['final_score'] = $evaluation['final_score'];
        $report['threshold'] = $evaluation['threshold'];
        $report['seo_lint'] = $evaluation['seo_lint'];
        $report['fact_check'] = $evaluation['fact_check'];
        $report['link_check'] = $evaluation['link_check'];
        $report['readability'] = $evaluation['readability'];
        $report['rubric'] = $rubric === null
            ? ['score' => null, 'per_criterion' => [], 'blocking_issues' => [], 'fix_instructions' => [], 'error' => $evaluation['rubric_error']]
            : $rubric;
        $report['per_criterion'] = $rubric['per_criterion'] ?? [];
        $report['blocking_issues'] = $evaluation['blocking_issues'];
        $report['fix_instructions'] = array_values(array_map(
            static fn (array $entry): string => trim(($entry['section_id'] !== '' ? "[{$entry['section_id']}] " : '').$entry['instruction']),
            (array) ($rubric['fix_instructions'] ?? []),
        ));
        $report['checked_at'] = now()->toIso8601String();
        $report['quality'] = [
            'decision' => $decision,
            // Ein frueherer Lauf kann schon einen Fix-Durchlauf verbraucht
            // haben; der Zaehler faellt nie zurueck.
            'fix_runs' => max($fixRuns, (int) Arr::get($report, 'quality.fix_runs', 0)),
            'attempt' => (int) $draft->attempt,
            'slot' => $this->slot($draft),
            'scores' => [
                'seo' => $evaluation['seo_score'],
                'fact' => $evaluation['fact_score'],
                'readability' => $evaluation['readability']['score'],
                'rubric' => $rubric['score'] ?? null,
            ],
            // Marke fuer den Alarm „Tagesziel gefaehrdet" in der Uebersicht (#19).
            'at_risk' => $atRisk,
        ];

        // Bei einer Aktualisierung haelt der Bericht fest, welche Abschnitte
        // der Korrekturlauf ueberhaupt anfassen durfte und welche Hinweise
        // deshalb offen geblieben sind (#96).
        $writable = $this->writableSections($draft);

        if ($writable !== null) {
            $skipped = $this->outOfScopeInstructions($evaluation, $writable);

            $report['quality']['refresh_scope'] = [
                'sections' => $writable,
                'skipped' => $skipped,
            ];

            if ($decision === 'review' && $skipped !== []) {
                array_unshift($report['blocking_issues'], $this->outOfScopeNote($writable, $skipped));
            }
        }

        $draft->forceFill([
            'quality_score' => $evaluation['final_score'],
            'quality_report_json' => $report,
            // Das Gate selbst kostet Geld (Rubrik, Fix-Durchlauf). Die Spalte
            // wird deshalb am Ende jedes Laufs aus dem Hauptbuch nachgezogen,
            // sonst steht im Tagesbericht (#94) nur der Stand der Erzeugung —
            // und bei einer Aktualisierung gar nichts.
            'generation_cost_usd' => LlmUsageLog::costForReference('article_drafts', (int) $draft->getKey()),
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Hilfsmittel
    |--------------------------------------------------------------------------
    */

    /**
     * Der Generierungskontext des Entwurfs. Er kostet Abfragen, aber keine
     * neuen Modellaufrufe: Rubrik und Fix-Durchlauf brauchen Styleguide,
     * Fakten und Linkziele in genau derselben Fassung, mit der der Artikel
     * geschrieben wurde.
     */
    private function context(ContextAssembler $assembler, Tenant $tenant, ArticleDraft $draft): ?GenerationContext
    {
        $topic = $draft->topicCandidate;

        if ($topic === null) {
            Log::warning('Qualitaetsgate ohne Themenkandidaten; nur die deterministischen Stufen laufen.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
            ]);

            return null;
        }

        try {
            return $assembler->assemble($tenant, $topic);
        } catch (Throwable $exception) {
            Log::warning('Kontext fuer das Qualitaetsgate nicht aufbaubar.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Belegstellen des Entwurfs: die am Entwurf haengenden Schnipsel und die,
     * die der Generator als verwendet vermerkt hat.
     *
     * @return Collection<int, FactSnippet>
     */
    private function factSnippets(ArticleDraft $draft, ?GenerationContext $context): Collection
    {
        $ids = $draft->factSnippetIds();

        $snippets = FactSnippet::query()
            ->where(function (Builder $query) use ($draft, $ids): void {
                $query->where('article_draft_id', $draft->getKey());

                if ($ids !== []) {
                    $query->orWhereIn('id', $ids);
                }
            })
            ->get();

        if ($context === null) {
            return $snippets;
        }

        // Der Kontext haelt zusaetzlich die Schnipsel des Themas. Sie sind
        // ebenfalls Beleg: der Generator hat sie im Prompt gehabt.
        return $snippets->concat($context->factSnippets)->unique(fn (FactSnippet $snippet) => $snippet->getKey())->values();
    }

    /**
     * @param  Collection<int, FactSnippet>  $snippets
     */
    private function hasRegionalFact(ArticleDraft $draft, Collection $snippets): bool
    {
        $outline = (array) ($draft->outline_json ?? []);

        if ((array) ($outline['regional_facts'] ?? []) !== []) {
            return true;
        }

        // Ein Fakt aus dem uebergeordneten Bundesland zaehlt fuer einen
        // Stadtartikel mit: er ist regionaler Beleg, nur groeber geschnitten.
        return $snippets->contains(
            fn (FactSnippet $snippet): bool => (string) $snippet->region_scope !== RegionScopeResolver::SCOPE_NATIONAL
        );
    }

    /**
     * Klarname der Region, wenn kein Kontext aufgebaut werden konnte.
     */
    private function regionName(ArticleDraft $draft): ?string
    {
        $code = $draft->region_code;

        if ($code === null) {
            return null;
        }

        $state = (array) config("content.regions.states.{$code}", []);

        return (string) ($state['name'] ?? $code);
    }

    /**
     * Der Slot des Tages. Der Generator haelt ihn im Bericht fest; der
     * Aufrufparameter gilt nur, wenn dort nichts steht.
     */
    private function slot(?ArticleDraft $draft = null): int
    {
        $report = (array) ($draft?->quality_report_json ?? []);
        $slot = (int) Arr::get($report, 'generation.slot', 0);

        return $slot > 0 ? $slot : $this->slot;
    }
}
