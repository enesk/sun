<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Generation\ContextAssembler;
use App\Content\Generation\Exceptions\MissingRegionalFactsException;
use App\Content\Generation\FaqStep;
use App\Content\Generation\GenerationContext;
use App\Content\Generation\HtmlAssembler;
use App\Content\Generation\MetaStep;
use App\Content\Generation\OutlineStep;
use App\Content\Generation\RegionalBlockStep;
use App\Content\Generation\SectionStep;
use App\Content\Generation\ShortAnswerStep;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\DraftSource;
use App\Content\Models\SourceItem;
use App\Content\Models\TopicCandidate;
use App\Content\Services\RegionScopeResolver;
use App\Content\Services\SlugService;
use App\Models\Portal\City;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Erzeugt aus einem ausgewaehlten Themenkandidaten einen vollstaendigen
 * Artikelentwurf (#14).
 *
 * Ablauf: Kontext-Assembly, Gliederung, Abschnitte einzeln, optionaler
 * Regionalblock, FAQ, Kurzantwort, Meta-Angaben, Zusammenbau. Ein Aufruf je
 * Abschnitt statt eines Aufrufs fuer den ganzen Artikel — jede Ausgabe bleibt
 * damit unter ~2.000 Tokens, und ein Schema-Retry wiederholt einen Abschnitt
 * statt 1.800 Woerter.
 *
 * Fehlerbild:
 *
 *  - LlmSchemaException oder BudgetExceededException: der Entwurf geht auf
 *    `failed`, und der beste Reserve-Kandidat des Tages rueckt nach. Nach
 *    config('content.generation.max_attempts_per_slot') Versuchen bleibt der
 *    Slot leer; der Tagesbericht (#22) meldet das.
 *  - Regionales Thema ohne regionalen Faktenschnipsel: Abbruch mit
 *    'missing_regional_facts'. Der Kandidat faellt auf 'national' zurueck und
 *    wird bundesweit erzeugt, statt eine Doorway-Seite zu werden.
 *  - Slug-Kollision mit einem bestehenden Artikel zum selben Hauptkeyword:
 *    kein Zaehlersuffix, sondern Ablehnung des Kandidaten — das waere ein
 *    zweiter Artikel zum selben Thema und damit Kannibalisierung.
 *
 * Der Job ist die einzige Stelle, die `article_drafts` anlegt. Deshalb legt
 * auch der Einstieg ueber ein Thema ohne Entwurf (Board-Karte „Jetzt
 * generieren", #37/#52) den Entwurf hier an und nicht im Dashboard-Service.
 */
class GenerateDraftJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retries organisiert der Job selbst ueber den Reserve-Kandidaten; ein
     * Queue-Retry wuerde denselben Kandidaten mit denselben Daten wiederholen.
     */
    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * Der Versuch, der tatsaechlich laeuft. Gibt die Aufrufstelle keinen mit,
     * traegt ihn der Entwurf: die Pruefung zaehlt ihn dort schon hoch, bevor
     * sie einreiht.
     */
    private int $currentAttempt = 1;

    /**
     * Reihenfolge und Typen wie bei den Folgejobs (`GenerateAssetsJob`,
     * `ScheduleAndPublishJob`): Mandant zuerst, dann das Ziel.
     *
     * Genau eines der beiden Ziele reicht:
     *
     *  - `draftId`: ein bestehender Entwurf wird neu erzeugt. Das Thema haengt
     *    am Entwurf.
     *  - `topicId`: eine Board-Karte vom Typ Thema hat noch keinen Entwurf —
     *    der Job legt ihn an und setzt ihn auf `generating`.
     *
     * Ohne beides gibt es kein Ziel; der Job protokolliert und endet.
     */
    public function __construct(
        public int $tenantId,
        public ?int $draftId = null,
        public ?int $topicId = null,
        public int $slot = 1,
        public ?int $attempt = null,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.llm', 'content-llm'));
    }

    public function uniqueId(): string
    {
        $target = $this->draftId !== null ? "draft:{$this->draftId}" : "topic:{$this->topicId}";

        return "content-generate-draft:{$this->tenantId}:{$target}";
    }

    public function uniqueFor(): int
    {
        return 7200;
    }

    public function handle(
        ContextAssembler $assembler,
        OutlineStep $outlineStep,
        SectionStep $sectionStep,
        RegionalBlockStep $regionalStep,
        FaqStep $faqStep,
        ShortAnswerStep $shortAnswerStep,
        MetaStep $metaStep,
        HtmlAssembler $html,
        SlugService $slugs,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use (
            $tenant, $assembler, $outlineStep, $sectionStep, $regionalStep,
            $faqStep, $shortAnswerStep, $metaStep, $html, $slugs
        ): void {
            $target = $this->resolveTarget();

            if ($target === null) {
                return;
            }

            [$draft, $topic] = $target;

            try {
                $context = $assembler->assemble($tenant, $topic);

                // Vor dem ersten Modellaufruf pruefen: ein regionales Thema
                // ohne regionalen Beleg wird gar nicht erst geschrieben.
                if ($context->isRegional() && $context->regionalFactSnippets()->isEmpty()) {
                    throw MissingRegionalFactsException::for($context->regionScope, $context->regionCode);
                }

                $this->generate(
                    $tenant, $topic, $draft, $context,
                    $outlineStep, $sectionStep, $regionalStep,
                    $faqStep, $shortAnswerStep, $metaStep, $html, $slugs,
                );
            } catch (MissingRegionalFactsException $exception) {
                $this->downgradeToNational($topic, $draft, $exception);

                return;
            } catch (BudgetExceededException|LlmSchemaException $exception) {
                $this->failAndRetry($topic, $draft, $exception);

                return;
            }
        });
    }

    /**
     * Der eigentliche Durchlauf. Wird ausschliesslich aus handle() heraus im
     * Tenant-Kontext gerufen.
     */
    private function generate(
        Tenant $tenant,
        TopicCandidate $topic,
        ArticleDraft $draft,
        GenerationContext $context,
        OutlineStep $outlineStep,
        SectionStep $sectionStep,
        RegionalBlockStep $regionalStep,
        FaqStep $faqStep,
        ShortAnswerStep $shortAnswerStep,
        MetaStep $metaStep,
        HtmlAssembler $html,
        SlugService $slugs,
    ): void {
        $outline = $outlineStep->run($context, $draft);

        if ($outline['sections'] === []) {
            throw LlmSchemaException::afterRetries('outline', 1, ['Gliederung ohne H2-Abschnitt.']);
        }

        $sections = [];
        $total = count($outline['sections']);

        foreach ($outline['sections'] as $index => $section) {
            $sections[] = $sectionStep->run(
                $context,
                $draft,
                $section,
                $outline['items'],
                $index + 1,
                $total,
            );
        }

        $regional = $context->isRegional()
            ? $regionalStep->run($context, $draft, $outline['items'])
            : null;

        $faq = $faqStep->run($context, $draft, $outline['items']);
        $shortAnswer = $shortAnswerStep->run($context, $draft, $outline['items']);
        $meta = $metaStep->run($context, $draft, $outline['title'], $outline['items']);

        // Der Regionalblock wird bei Stadt-Zuschnitt vom Ratgeber-Template als
        // eigener Baustein gerendert (#17) und darf dann nicht zusaetzlich im
        // Fliesstext stehen. Bei Landes-Zuschnitt gibt es diesen Baustein
        // nicht, dort wandert er in den Text.
        $renderedByTemplate = $regional !== null && $this->templateRendersRegion($context);

        $assembled = $html->assemble(
            $context,
            $sections,
            $renderedByTemplate ? null : $regional,
            $this->citableUrls($context),
        );

        $usedFactIds = $this->usedFactIds($sections, $regional);
        $keyFacts = $html->keyFacts($context, $usedFactIds);

        $slug = $slugs->resolve(
            $outline['title'],
            $context->primaryKeyword,
            $context->isRegional() ? $context->regionName : null,
            (int) $draft->getKey(),
        );

        if ($slug['duplicate_of'] !== null) {
            $this->rejectAsDuplicate($topic, $draft, $slug);

            return;
        }

        $draft->forceFill([
            'title' => Str::limit($outline['title'], 255, ''),
            'slug' => $slug['slug'],
            'meta_title' => $meta['meta_title'],
            'meta_description' => $meta['meta_description'],
            'short_answer' => $shortAnswer,
            'outline_json' => $this->outlineJson(
                $outline, $sections, $regional, $assembled, $renderedByTemplate, $usedFactIds,
            ),
            'body_html' => $assembled['html'],
            'faq_json' => $faq,
            'key_facts_json' => $keyFacts,
            'region_scope' => $context->regionScope,
            'region_code' => $context->regionCode,
            'quality_report_json' => null,
            'attempt' => $this->currentAttempt,
            'generation_cost_usd' => LlmUsageLog::costForReference('article_drafts', (int) $draft->getKey()),
            'status' => DraftStatus::GENERATED,
        ])->save();

        $this->linkSources($draft, $context, $usedFactIds, $assembled['external_links']);
        $this->warnOnLength($draft, $context, $assembled['word_count']);

        Log::info('Artikelentwurf erzeugt.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'topic' => $context->label(),
            'sections' => count($sections),
            'words' => $assembled['word_count'],
            'internal_links' => count($assembled['internal_links']),
            'cost_usd' => (float) $draft->generation_cost_usd,
        ]);

        // Naechstes Glied der Kette (#15). Der Nachfolger wird erst angestossen,
        // nachdem `generated` persistiert ist — die Kette ist damit an jeder
        // Stelle wiederaufsetzbar (docs/content-pipeline.md, §3).
        QualityCheckJob::dispatch($this->tenantId, (int) $draft->getKey(), $this->slot);
    }

    /**
     * Entwurf und Thema zum Auftrag. Der `topicId`-Pfad legt den Entwurf an,
     * wenn es noch keinen gibt — das ist der Fall der Board-Karte vom Typ
     * Thema (#52). So bleibt der Generator die einzige Stelle, die Entwuerfe
     * erzeugt.
     *
     * @return array{0: ArticleDraft, 1: TopicCandidate}|null
     */
    private function resolveTarget(): ?array
    {
        $draft = $this->draftId === null ? null : ArticleDraft::query()->find($this->draftId);

        if ($this->draftId !== null && $draft === null) {
            Log::warning('Generator ohne Entwurf.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $this->draftId,
            ]);

            return null;
        }

        $topicId = $this->topicId;

        if ($topicId === null && $draft !== null && $draft->topic_candidate_id !== null) {
            $topicId = (int) $draft->topic_candidate_id;
        }

        $topic = $topicId === null ? null : TopicCandidate::query()->find($topicId);

        if ($topic === null) {
            Log::warning('Generator ohne Themenkandidaten.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $this->draftId,
                'topic_candidate_id' => $topicId,
            ]);

            // Ein Entwurf ohne Thema laesst sich nicht schreiben: der
            // Kontext-Assembler zieht Keywords, Quellen und Zuschnitt aus dem
            // Kandidaten. Der Entwurf bliebe sonst auf `generating` haengen.
            if ($draft !== null) {
                $this->currentAttempt = $this->attempt ?? max(1, (int) $draft->attempt);
                $this->markFailed($draft, 'missing_topic', 'Kein Themenkandidat zum Entwurf.');
            }

            return null;
        }

        // Ein Wiederholungslauf schreibt den bestehenden Entwurf fort, statt
        // eine zweite Zeile anzulegen — sonst haette ein Thema nach drei
        // Versuchen drei Entwuerfe im Board.
        $draft ??= $this->existingDraftFor($topic);
        $this->currentAttempt = $this->attempt ?? max(1, (int) ($draft?->attempt ?? 1));
        $draft ??= $this->createDraft($topic);

        $this->markGenerating($draft);

        return [$draft, $topic];
    }

    private function existingDraftFor(TopicCandidate $topic): ?ArticleDraft
    {
        return ArticleDraft::query()
            ->where('topic_candidate_id', $topic->getKey())
            ->whereIn('status', [DraftStatus::GENERATING->value, DraftStatus::FAILED->value])
            ->orderByDesc('id')
            ->first();
    }

    private function createDraft(TopicCandidate $topic): ArticleDraft
    {
        return ArticleDraft::query()->create([
            'topic_candidate_id' => $topic->getKey(),
            'status' => DraftStatus::GENERATING,
            'title' => (string) ($topic->title ?: $topic->primary_keyword),
            'slug' => Str::slug((string) ($topic->title ?: $topic->primary_keyword)),
            'region_scope' => (string) $topic->region_scope,
            'region_code' => $topic->region_code,
            'attempt' => $this->currentAttempt,
        ]);
    }

    /**
     * Setzt den Entwurf auf `generating` und haelt den Startzeitpunkt fest.
     * Das Board liest ihn fuer die Zeile „laeuft seit hh:mm"; `updated_at`
     * taugt dafuer nicht, weil jeder Zwischenschritt ihn verschiebt. Die
     * uebrigen Felder des Qualitaetsberichts — allen voran `fix_instructions`
     * — bleiben unberuehrt, der FixSectionsStep liest sie im Lauf.
     */
    private function markGenerating(ArticleDraft $draft): void
    {
        $report = (array) ($draft->quality_report_json ?? []);
        $generation = Arr::except((array) ($report['generation'] ?? []), ['failed_reason', 'message']);

        $generation['started_at'] = now()->toIso8601String();
        $generation['attempt'] = $this->currentAttempt;
        $generation['slot'] = $this->slot;

        $report['generation'] = $generation;

        $draft->forceFill([
            'status' => DraftStatus::GENERATING,
            'attempt' => $this->currentAttempt,
            'quality_report_json' => $report,
        ])->save();
    }

    /**
     * Rendert das Ratgeber-Template den Regionalblock selbst? Es tut das nur
     * bei Stadt-Zuschnitt und nur, wenn die Stadt im Portal gefuehrt ist —
     * dieselbe Bedingung wie im ArticleBlockPresenter.
     */
    private function templateRendersRegion(GenerationContext $context): bool
    {
        if ($context->regionScope !== RegionScopeResolver::SCOPE_CITY || $context->regionCode === null) {
            return false;
        }

        return City::query()
            ->where('slug', Str::slug($context->regionCode))
            ->orWhere('name', $context->regionCode)
            ->exists();
    }

    /**
     * Externe URLs, die im Artikeltext stehen duerfen: ausschliesslich Quellen
     * des Themas und Belegquellen der Faktenschnipsel.
     *
     * @return array<int, string>
     */
    private function citableUrls(GenerationContext $context): array
    {
        $urls = $context->sourceItems
            ->pluck('url')
            ->merge($context->factSnippets->pluck('source_url'))
            ->filter(fn ($url): bool => is_string($url) && str_starts_with($url, 'http'))
            ->map(fn (string $url): string => trim($url));

        return $urls->unique()->values()->all();
    }

    /**
     * Faktennummern, die die Stufen als verwendet gemeldet haben — begrenzt
     * auf die tatsaechlich uebergebenen Schnipsel. Eine erfundene Nummer
     * zaehlt nicht.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $regional
     * @return array<int, int>
     */
    private function usedFactIds(array $sections, ?array $regional): array
    {
        $ids = [];

        foreach ($sections as $section) {
            foreach ((array) ($section['used_facts'] ?? []) as $id) {
                $ids[] = (int) $id;
            }
        }

        foreach ((array) ($regional['used_facts'] ?? []) as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Die strukturierten Nebenfelder. `regional_intro`, `regional_outro` und
     * `regional_facts` liest der ArticleBlockPresenter (#17) fuer den
     * Regionalbaustein; `sections` und `word_count` braucht das Qualitaetsgate
     * (#15) und der Refresh-Loop (#24).
     *
     * @param  array{title: string, items: array<int, array<string, mixed>>, sections: array<int, array<string, mixed>>}  $outline
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $regional
     * @param  array{html: string, word_count: int, internal_links: array<int, string>, external_links: array<int, string>}  $assembled
     * @param  array<int, int>  $usedFactIds
     * @return array<string, mixed>
     */
    private function outlineJson(
        array $outline,
        array $sections,
        ?array $regional,
        array $assembled,
        bool $renderedByTemplate,
        array $usedFactIds,
    ): array {
        $json = [
            'title' => $outline['title'],
            'headings' => $outline['items'],
            'sections' => array_map(static fn (array $section): array => [
                'heading' => $section['heading'],
                'summary_sentence' => $section['summary_sentence'],
                'word_count' => $section['word_count'],
            ], $sections),
            'word_count' => $assembled['word_count'],
            'internal_links' => $assembled['internal_links'],
            'external_links' => $assembled['external_links'],

            // Die belegten Fakten dieses Entwurfs. Die Schnipsel selbst
            // bleiben gemeinsamer Bestand (#11); hier steht nur, welche
            // verwendet wurden — Grundlage des Faktenchecks (#15).
            'fact_snippet_ids' => $usedFactIds,
        ];

        if ($regional !== null) {
            $json['regional_facts'] = $regional['facts'];
            $json['regional_in_body'] = ! $renderedByTemplate;

            if ($renderedByTemplate) {
                $json['regional_intro'] = $regional['intro'];
                $json['regional_outro'] = $regional['outro'];
            }
        }

        return $json;
    }

    /**
     * Verknuepft jede verwendete Quelle mit dem Entwurf. `is_cited` trennt die
     * belegte Quelle von der blossen Anregung: nur zitierte Quellen erscheinen
     * in der Quellenzeile des Artikels (#27).
     *
     * @param  array<int, int>  $usedFactIds
     * @param  array<int, string>  $externalLinks
     */
    private function linkSources(
        ArticleDraft $draft,
        GenerationContext $context,
        array $usedFactIds,
        array $externalLinks,
    ): void {
        $draft->sources()->delete();

        $order = 0;
        $seen = [];

        foreach ($context->factSnippets as $snippet) {
            if (! in_array((int) $snippet->getKey(), $usedFactIds, true)) {
                continue;
            }

            $url = (string) ($snippet->source_url ?? '');
            $key = $url !== '' ? $url : 'fact:'.$snippet->getKey();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DraftSource::query()->create([
                'article_draft_id' => $draft->getKey(),
                'source_item_id' => $snippet->source_item_id,
                'title' => Str::limit((string) ($snippet->source_name ?: $snippet->statement), 250, ''),
                'url' => $url !== '' ? $url : null,
                'publisher' => $snippet->source_name,
                'snippet' => Str::limit((string) $snippet->statement, 500, ''),
                'published_at' => $snippet->retrieved_at,
                'is_cited' => true,
                'sort_order' => $order++,
            ]);
        }

        foreach ($context->sourceItems as $item) {
            $url = (string) ($item->url ?? '');
            $key = $url !== '' ? $url : 'item:'.$item->getKey();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            DraftSource::query()->create([
                'article_draft_id' => $draft->getKey(),
                'source_item_id' => $item->getKey(),
                'title' => Str::limit((string) $item->title, 250, ''),
                'url' => $url !== '' ? $url : null,
                'publisher' => $this->publisherOf($item),
                'snippet' => Str::limit((string) ($item->summary ?? ''), 500, ''),
                'published_at' => $item->published_at,
                // Im Text verlinkte Quellen sind zitiert; die uebrigen bleiben
                // als Anlass des Themas dokumentiert, ohne in der
                // Quellenzeile zu erscheinen.
                'is_cited' => $url !== '' && in_array($url, $externalLinks, true),
                'sort_order' => $order++,
            ]);
        }
    }

    private function publisherOf(SourceItem $item): ?string
    {
        $host = $item->url !== null ? parse_url((string) $item->url, PHP_URL_HOST) : null;

        return is_string($host) ? Str::of($host)->after('www.')->toString() : (string) $item->source_key;
    }

    /**
     * Laenge ausserhalb des Korridors ist kein Abbruchgrund — darueber
     * entscheidet das Qualitaetsgate (#15). Sie wird aber protokolliert, damit
     * ein systematisch zu kurzer Styleguide auffaellt. Gemessen wird gegen
     * denselben Korridor wie im Gate: den Zielkorridor der Suchintention plus
     * content.quality.word_tolerance (§4.2).
     */
    private function warnOnLength(ArticleDraft $draft, GenerationContext $context, int $words): void
    {
        $min = (int) ($context->targetWords['min'] ?? 900);
        $max = max($min, (int) ($context->targetWords['max'] ?? 1800));

        $tolerance = (array) config('content.quality.word_tolerance', []);
        $tolMin = (int) round($min * (1.0 - (float) ($tolerance['under'] ?? 0.10)));
        $tolMax = (int) round($max * (1.0 + (float) ($tolerance['over'] ?? 0.25)));

        if ($words >= $tolMin && $words <= $tolMax) {
            return;
        }

        Log::warning('Artikelentwurf ausserhalb des Laengenkorridors.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'intent' => $context->intent,
            'words' => $words,
            'corridor' => [$min, $max],
            'tolerated' => [$tolMin, $tolMax],
        ]);
    }

    /**
     * Regionales Thema ohne regionalen Beleg: Kandidat auf bundesweit
     * zuruecksetzen und denselben Kandidaten national erzeugen. Der Zuschnitt
     * kann danach nicht erneut greifen, eine Schleife ist also ausgeschlossen.
     */
    private function downgradeToNational(
        TopicCandidate $topic,
        ArticleDraft $draft,
        MissingRegionalFactsException $exception,
    ): void {
        $reason = trim((string) $topic->region_reason.' '
            .'Kein regionaler Faktenbeleg zur Generierungszeit, deshalb bundesweit erzeugt.');

        $topic->forceFill([
            'region_scope' => RegionScopeResolver::SCOPE_NATIONAL,
            'region_code' => null,
            'region_reason' => Str::limit($reason, 500, ''),
        ])->save();

        $this->markFailed($draft, $exception->reason(), $exception->getMessage());

        Log::warning('Regionaler Entwurf abgebrochen, Thema faellt auf bundesweit zurueck.', [
            'tenant_id' => $this->tenantId,
            'topic_candidate_id' => $topic->getKey(),
            'reason' => $exception->reason(),
        ]);

        $this->dispatchNext($topic->getKey());
    }

    /**
     * Schema- oder Budgetfehler: Entwurf auf `failed`, Reserve-Kandidat ziehen.
     */
    private function failAndRetry(TopicCandidate $topic, ArticleDraft $draft, \Throwable $exception): void
    {
        $reason = $exception instanceof BudgetExceededException ? 'budget_exceeded' : 'llm_schema';

        $this->markFailed($draft, $reason, $exception->getMessage());

        Log::error('Artikelgenerierung fehlgeschlagen.', [
            'tenant_id' => $this->tenantId,
            'topic_candidate_id' => $topic->getKey(),
            'draft_id' => $draft->getKey(),
            'attempt' => $this->currentAttempt,
            'reason' => $reason,
            'exception' => $exception->getMessage(),
        ]);

        // Bei erschoepftem Budget waere jeder Nachrueck-Kandidat derselbe
        // Fehler. Der Slot bleibt heute leer.
        if ($exception instanceof BudgetExceededException) {
            return;
        }

        $reserve = $this->reserveCandidate($topic);

        if ($reserve === null) {
            Log::warning('Kein Reserve-Kandidat fuer den Slot vorhanden.', [
                'tenant_id' => $this->tenantId,
                'slot' => $this->slot,
            ]);

            return;
        }

        $reserve->forceFill([
            'status' => TopicStatus::SELECTED,
            'selected_for_date' => $topic->selected_for_date ?? now()->toDateString(),
        ])->save();

        $this->dispatchNext((int) $reserve->getKey());
    }

    /**
     * Der beste Reserve-Kandidat des Tages, ohne den gescheiterten.
     */
    private function reserveCandidate(TopicCandidate $topic): ?TopicCandidate
    {
        $date = $topic->selected_for_date ?? now();

        return TopicCandidate::query()
            ->reserveFor($date)
            ->whereKeyNot($topic->getKey())
            ->first();
    }

    /**
     * Naechster Versuch fuer denselben Slot, solange das Tageskontingent
     * reicht.
     */
    private function dispatchNext(int $topicCandidateId): void
    {
        $max = max(1, (int) config('content.generation.max_attempts_per_slot', 3));

        if ($this->currentAttempt >= $max) {
            Log::warning('Generierungsversuche fuer den Slot erschoepft.', [
                'tenant_id' => $this->tenantId,
                'slot' => $this->slot,
                'attempts' => $this->currentAttempt,
            ]);

            return;
        }

        self::dispatch($this->tenantId, null, $topicCandidateId, $this->slot, $this->currentAttempt + 1);
    }

    private function markFailed(ArticleDraft $draft, string $reason, string $message): void
    {
        $draft->forceFill([
            'status' => DraftStatus::FAILED,
            'attempt' => $this->currentAttempt,
            'generation_cost_usd' => LlmUsageLog::costForReference('article_drafts', (int) $draft->getKey()),
            'quality_report_json' => [
                'blocking_issues' => [__('Generierung abgebrochen').": {$reason}"],
                'generation' => [
                    'failed_reason' => $reason,
                    'message' => Str::limit($message, 500, ''),
                    'attempt' => $this->currentAttempt,
                    'slot' => $this->slot,
                ],
            ],
        ])->save();
    }

    /**
     * Slug-Kollision mit einem bestehenden Artikel zum selben Hauptkeyword.
     * Ein Suffix waere hier falsch: das Thema ist schon abgedeckt.
     *
     * @param  array{slug: string, duplicate_of: ?int}  $slug
     */
    private function rejectAsDuplicate(TopicCandidate $topic, ArticleDraft $draft, array $slug): void
    {
        $topic->forceFill([
            'status' => TopicStatus::REJECTED,
            'rejection_reason' => "Bestehender Artikel {$slug['slug']} deckt dasselbe Hauptkeyword ab.",
        ])->save();

        $this->markFailed($draft, 'duplicate_slug', "Kollision mit posts.id {$slug['duplicate_of']}.");

        Log::warning('Entwurf verworfen: bestehender Artikel zum selben Hauptkeyword.', [
            'tenant_id' => $this->tenantId,
            'topic_candidate_id' => $topic->getKey(),
            'slug' => $slug['slug'],
            'article_id' => $slug['duplicate_of'],
        ]);

        $reserve = $this->reserveCandidate($topic);

        if ($reserve === null) {
            return;
        }

        $reserve->forceFill([
            'status' => TopicStatus::SELECTED,
            'selected_for_date' => $topic->selected_for_date ?? now()->toDateString(),
        ])->save();

        $this->dispatchNext((int) $reserve->getKey());
    }
}
