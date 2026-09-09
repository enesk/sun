<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\DraftStatus;
use App\Content\Generation\ContextAssembler;
use App\Content\Generation\FixSectionsStep;
use App\Content\Generation\GenerationContext;
use App\Content\Generation\HtmlAssembler;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Services\RefreshSelector;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refresh-Loop: abrutschende Artikel aktualisieren statt neu schreiben (#24).
 *
 * Der Job nimmt sich die Kandidaten des RefreshSelector — markierte Artikel
 * aus dem Metrik-Collector (#23) und solche mit abgelaufenen Belegen —, holt
 * das heutige Suchergebnisbild und die aktuelle Faktenlage und laesst genau
 * die betroffenen Abschnitte ueberarbeiten (FixSectionsStep im Modus
 * `refresh`, Vorlage `refresh_update`).
 *
 * Das Ergebnis ist eine neue Fassung des Entwurfs mit `parent_draft_id` auf
 * die bisherige. Sie traegt denselben `article_id`, denselben Titel und
 * denselben Slug: die URL aendert sich nie, und die Pruefflaeche (#20) kann
 * beide Fassungen gegenueberstellen. Danach laeuft das Qualitaetsgate (#15)
 * ueber die neue Fassung wie ueber jeden anderen Entwurf; besteht sie, geht
 * sie ueber den Publisher (#21) als Aktualisierung desselben Artikels live.
 *
 * Drei Dinge, die den Loop davon abhalten, sich selbst zu fressen:
 *
 *  - Das Tageskontingent (config('content.refresh.max_per_tenant_per_day'),
 *    Vorgabe 3) wird vor jedem einzelnen Kandidaten neu geprueft, nicht nur
 *    zu Beginn.
 *  - Refreshes laufen ausserhalb des Tagesziels von zwei neuen Artikeln: sie
 *    haengen an keinem Slot, reihen keinen Reserve-Kandidaten nach und
 *    zaehlen in der Uebersicht (#19) nicht mit.
 *  - Findet ein Lauf nichts zu aendern, vermerkt er das am Artikel
 *    (`refresh_reason_json.last_attempt_at`). Der Kandidat ist damit fuer die
 *    Sperrfrist aus dem Rennen, statt am naechsten Tag erneut Geld zu kosten.
 *    Dieselbe Marke traegt auch die neue Fassung (#93) — die Sperrfrist gilt
 *    dem Artikel, nicht der einzelnen Fassung.
 */
class RefreshArticleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Wie beim Generator: ein Queue-Retry wuerde dieselben Abschnitte ein
     * zweites Mal bezahlen. Was scheitert, faellt auf den naechsten Tageslauf.
     */
    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  int|null  $draftId  Eine bestimmte Fassung aktualisieren
     *                             (Handbetrieb). Ohne Angabe waehlt der
     *                             Selektor bis zum Tageskontingent aus.
     */
    public function __construct(
        public int $tenantId,
        public ?int $draftId = null,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.llm', 'content-llm'));
    }

    public function uniqueId(): string
    {
        return "content-refresh:{$this->tenantId}:".($this->draftId ?? 'auto');
    }

    public function uniqueFor(): int
    {
        return 7200;
    }

    public function handle(
        RefreshSelector $selector,
        ContextAssembler $assembler,
        FixSectionsStep $fixStep,
        HtmlAssembler $html,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $selector, $assembler, $fixStep, $html): void {
            $remaining = $selector->remainingToday();

            if ($remaining <= 0) {
                Log::info('Refresh-Loop: Tageskontingent des Mandanten ausgeschoepft.', [
                    'tenant_id' => $this->tenantId,
                    'limit' => (int) config('content.refresh.max_per_tenant_per_day', 3),
                ]);

                return;
            }

            $candidates = $this->draftId !== null
                ? array_filter([$selector->candidateFor($this->draftId)])
                : $selector->candidates($remaining);

            if ($candidates === []) {
                Log::info('Refresh-Loop: kein Kandidat.', ['tenant_id' => $this->tenantId]);

                return;
            }

            foreach ($candidates as $candidate) {
                // Das Kontingent wird vor jedem Kandidaten neu gelesen: ein
                // paralleler Lauf oder ein Handstart kann es aufgebraucht
                // haben, waehrend dieser Job schon lief.
                if ($selector->remainingToday() <= 0) {
                    Log::info('Refresh-Loop: Tageskontingent waehrend des Laufs erreicht.', [
                        'tenant_id' => $this->tenantId,
                    ]);

                    return;
                }

                try {
                    $this->refresh($tenant, $selector, $assembler, $fixStep, $html, $candidate);
                } catch (Throwable $exception) {
                    Log::error('Refresh-Loop: Aktualisierung fehlgeschlagen.', [
                        'tenant_id' => $this->tenantId,
                        'draft_id' => $candidate['draft']->getKey(),
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Eine Aktualisierung
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{draft: ArticleDraft, reasons: array<int, array<string, mixed>>, stale_snippets: \Illuminate\Support\Collection<int, \App\Content\Models\FactSnippet>}  $candidate
     */
    private function refresh(
        Tenant $tenant,
        RefreshSelector $selector,
        ContextAssembler $assembler,
        FixSectionsStep $fixStep,
        HtmlAssembler $html,
        array $candidate,
    ): void {
        $parent = $candidate['draft'];
        $topic = $parent->topicCandidate;

        if ($topic === null) {
            // Ohne Thema gibt es weder Styleguide noch Faktenliste noch
            // Linkziele. Eine Aktualisierung ohne all das waere ein
            // Freitextlauf — die faellt aus.
            $this->markAttempted($parent, 'ohne Themenkandidat');

            return;
        }

        $sections = $selector->split($parent);
        $targets = $selector->sectionsToRefresh($sections, $candidate['reasons'], $candidate['stale_snippets']);

        if ($targets === []) {
            $this->markAttempted($parent, 'kein betroffener Abschnitt gefunden');

            return;
        }

        // Frisches Suchergebnisbild: mit dem Stand des Erstentwurfs waere die
        // Aktualisierung sinnlos.
        $context = $assembler->assemble($tenant, $topic, (string) $parent->region_scope, freshSerp: true);

        $child = $this->createChild($parent);

        [$sections, $changedIds, $notes] = $this->rewrite($context, $child, $fixStep, $html, $sections, $targets);

        if ($changedIds === []) {
            // Nichts hat sich geaendert: die neue Fassung waere eine Kopie.
            // Bezahlt ist der Lauf trotzdem — die Summe wandert an den
            // Artikel, sonst verschwindet sie mit der geloeschten Fassung.
            $spent = LlmUsageLog::costForReference('article_drafts', (int) $child->getKey());
            $child->delete();
            $this->markAttempted($parent, 'Modell hat nichts geaendert', $spent);

            return;
        }

        $body = FixSectionsStep::join($sections);

        $outline = (array) ($child->outline_json ?? []);
        $outline['word_count'] = $html->wordCount($body);
        $outline['internal_links'] = $html->links($body, internal: true);
        $outline['external_links'] = $html->links($body, internal: false);

        $child->forceFill([
            'body_html' => $body,
            'outline_json' => $outline,
            // Der Aenderungshinweis nennt nur die Abschnitte, die wirklich
            // anders sind — nicht die, die der Lauf bloss angefasst hat. Das
            // Qualitaetsgate leitet daraus ab, was es ueberarbeiten darf (#96).
            'changelog_json' => $this->changelog($parent, $candidate['reasons'], $changedIds, $notes),
            // Was die Aktualisierung gekostet hat (#94). Ohne diese Zeile
            // bliebe die Spalte auf 0 stehen und der Tagesbericht wuerde jede
            // Aktualisierung als kostenlos ausweisen. Das Qualitaetsgate zieht
            // den Wert am Ende seines Laufs noch einmal nach.
            'generation_cost_usd' => LlmUsageLog::costForReference('article_drafts', (int) $child->getKey()),
        ])->save();

        $child->transitionTo(DraftStatus::GENERATED);

        // Die Marke gehoert jetzt der neuen Fassung: die alte ist abgearbeitet.
        $parent->forceFill([
            'needs_refresh' => false,
            'refresh_reason_json' => array_replace((array) ($parent->refresh_reason_json ?? []), [
                'refreshed_by_draft_id' => (int) $child->getKey(),
                'last_attempt_at' => CarbonImmutable::now()->toIso8601String(),
            ]),
        ])->save();

        Log::info('Refresh-Loop: neue Fassung erzeugt.', [
            'tenant_id' => $this->tenantId,
            'parent_draft_id' => (int) $parent->getKey(),
            'draft_id' => (int) $child->getKey(),
            'article_id' => (int) $parent->article_id,
            'sections' => $changedIds,
            'cost_usd' => (float) $child->generation_cost_usd,
        ]);

        QualityCheckJob::dispatch($this->tenantId, (int) $child->getKey());
    }

    /**
     * Die neue Fassung. Sie erbt alles, was gleich bleiben soll — Titel,
     * Slug, Artikelbezug, Bilder, Kurzantwort, FAQ —, und faengt bei der
     * Bewertung von vorn an.
     */
    private function createChild(ArticleDraft $parent): ArticleDraft
    {
        $child = $parent->replicate([
            'quality_score',
            'quality_report_json',
            'publication_json',
            'scheduled_for',
            'published_at',
            'withdrawn_at',
            'withdrawn_reason',
            'withdrawn_by',
        ]);

        $child->forceFill([
            'parent_draft_id' => (int) $parent->getKey(),
            'status' => DraftStatus::GENERATING->value,
            'attempt' => 0,
            'quality_score' => null,
            'quality_report_json' => null,
            'publication_json' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'needs_refresh' => false,
            'needs_refresh_at' => null,
            // Die neue Fassung erbt die Sperrmarke des Laufs (#93): ohne sie
            // stuende sie ab dem Folgetag wieder als Kandidat da, sobald der
            // Collector `needs_refresh` erneut setzt.
            'refresh_reason_json' => ['last_attempt_at' => CarbonImmutable::now()->toIso8601String()],
            'generation_cost_usd' => 0,
        ])->save();

        // Die Quellenliste des Artikels gilt weiter; sie haengt am Entwurf,
        // nicht am Artikel, und ohne sie stuende die neue Fassung im
        // Faktencheck ohne Belege da.
        foreach ($parent->sources()->get() as $source) {
            $source->replicate()
                ->forceFill(['article_draft_id' => (int) $child->getKey()])
                ->save();
        }

        return $child;
    }

    /**
     * Ueberarbeitet die betroffenen Abschnitte. Ein Abschnitt, der leer
     * zurueckkommt oder das Modell nichts kostet, bleibt wie er war.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     * @param  array<string, array<int, string>>  $targets
     * @return array{0: array<int, array{id: string, heading: string, summary: string, body: string}>, 1: array<int, string>, 2: array<int, string>}
     */
    private function rewrite(
        GenerationContext $context,
        ArticleDraft $child,
        FixSectionsStep $fixStep,
        HtmlAssembler $html,
        array $sections,
        array $targets,
    ): array {
        $internalUrls = array_map(
            static fn (array $target): string => (string) $target['url'],
            $context->linkTargets,
        );
        $externalUrls = $child->sources()
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url) && str_starts_with($url, 'http'))
            ->values()
            ->all();

        $changedIds = [];
        $notes = [];

        foreach ($sections as $index => $section) {
            $instructions = $targets[$section['id']] ?? [];

            if ($instructions === []) {
                continue;
            }

            try {
                $revised = $fixStep->run($context, $child, $section, $instructions, FixSectionsStep::MODE_REFRESH);
            } catch (BudgetExceededException|LlmSchemaException $exception) {
                Log::warning('Refresh-Loop: Abschnitt nicht aktualisierbar.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => (int) $child->getKey(),
                    'section' => $section['id'],
                    'exception' => $exception->getMessage(),
                ]);

                break;
            }

            $revised['body'] = $html->sanitize($revised['body'], $internalUrls, $externalUrls);

            if (trim($revised['body']) === '') {
                continue;
            }

            // Ein Abschnitt, der Wort fuer Wort derselbe ist, zaehlt nicht als
            // Aenderung — sonst entstuende eine Fassung ohne Unterschied.
            if ($this->isUnchanged($section, $revised)) {
                continue;
            }

            if ($revised['change_note'] !== '') {
                $notes[] = $revised['change_note'];
            }

            $sections[$index] = [
                'id' => $revised['id'],
                'heading' => $revised['heading'],
                'summary' => $revised['summary'],
                'body' => $revised['body'],
            ];
            $changedIds[] = (string) $section['id'];
        }

        return [$sections, $changedIds, array_values(array_unique($notes))];
    }

    /**
     * @param  array{id: string, heading: string, summary: string, body: string}  $before
     * @param  array{id: string, heading: string, summary: string, body: string, change_note: string}  $after
     */
    private function isUnchanged(array $before, array $after): bool
    {
        $normalize = static fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return $normalize($before['heading']) === $normalize($after['heading'])
            && $normalize($before['summary']) === $normalize($after['summary'])
            && $normalize($before['body']) === $normalize($after['body']);
    }

    /*
    |--------------------------------------------------------------------------
    | Aenderungshinweis
    |--------------------------------------------------------------------------
    */

    /**
     * Die Liste der Aenderungshinweise der neuen Fassung: die geerbten der
     * Elternfassung plus der neue Eintrag.
     *
     * `at` bleibt leer — das Datum setzt der Publisher beim Livegang. Ein
     * Hinweis „Aktualisiert am ..." mit dem Datum der Erzeugung waere falsch,
     * wenn die Fassung erst nach einer Pruefung erscheint.
     *
     * @param  array<int, array<string, mixed>>  $reasons
     * @param  array<int, string>  $sectionIds
     * @param  array<int, string>  $notes
     * @return array<int, array<string, mixed>>
     */
    private function changelog(ArticleDraft $parent, array $reasons, array $sectionIds, array $notes): array
    {
        $entries = $parent->changelogEntries();

        $entries[] = [
            'at' => null,
            'summary' => $notes !== []
                ? implode(' ', array_slice($notes, 0, 3))
                : $this->fallbackSummary($reasons),
            'reasons' => $this->reasonLabels($reasons),
            'sections' => array_values($sectionIds),
        ];

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reasons
     */
    private function fallbackSummary(array $reasons): string
    {
        return RefreshSelector::hasPerformanceReason($reasons)
            ? (string) __('Inhalte überarbeitet und auf den aktuellen Stand gebracht.')
            : (string) __('Zahlen und Fristen auf den aktuellen Stand gebracht.');
    }

    /**
     * Die Gruende im Klartext, ohne Dopplung — sie stehen als Zusatz am
     * Aenderungshinweis und im Protokoll.
     *
     * @param  array<int, array<string, mixed>>  $reasons
     * @return array<int, string>
     */
    private function reasonLabels(array $reasons): array
    {
        $labels = [];

        foreach ($reasons as $reason) {
            $labels[] = ($reason['type'] ?? '') === RefreshSelector::REASON_STALE_FACT
                ? (string) __('Beleg abgelaufen: :label', ['label' => (string) ($reason['label'] ?? $reason['fact_key'] ?? '')])
                : (string) __('Sichtbarkeit rückläufig');
        }

        return array_values(array_unique(array_filter($labels)));
    }

    /**
     * Vermerkt einen Lauf ohne Ergebnis. Der Artikel ist damit fuer die
     * Sperrfrist aus dem Rennen (RefreshSelector).
     *
     * @param  float  $spentUsd  Was der abgebrochene Lauf gekostet hat. Nur
     *                           gesetzt, wenn schon eine verworfene Fassung
     *                           bezahlt wurde.
     */
    private function markAttempted(ArticleDraft $draft, string $reason, float $spentUsd = 0.0): void
    {
        $draft->forceFill([
            'needs_refresh' => false,
            'refresh_reason_json' => array_replace((array) ($draft->refresh_reason_json ?? []), [
                'last_attempt_at' => CarbonImmutable::now()->toIso8601String(),
                'last_attempt_result' => $reason,
                'last_attempt_cost_usd' => round($spentUsd, 4),
            ]),
        ])->save();

        Log::info('Refresh-Loop: Kandidat uebersprungen.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => (int) $draft->getKey(),
            'reason' => $reason,
            'cost_usd' => round($spentUsd, 4),
        ]);
    }
}
