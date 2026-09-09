<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Generation\FixSectionsStep;
use App\Content\Models\ArticleDraft;
use App\Content\Models\FactSnippet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Waehlt aus, welche veroeffentlichten Ratgeber aktualisiert werden — und
 * welche Abschnitte davon (#24).
 *
 * Zwei Eingangsgroessen, beide ohne Modellaufruf:
 *
 *  1. `needs_refresh`: der Metrik-Collector (#23) setzt die Marke, wenn ein
 *     Artikel ueber 14 Tage fuenf Plaetze verliert oder seine CTR um 40
 *     Prozent einbricht. Die Begruendung steht in `refresh_reason_json`.
 *  2. Abgelaufene Belege: mindestens einer der Faktenschnipsel, auf die der
 *     Artikel sich stuetzt (`outline_json.fact_snippet_ids`), hat sein
 *     `valid_until` ueberschritten. Eine Foerderhoehe von gestern ist der
 *     haeufigere Grund fuer eine Aktualisierung als ein Rankingverlust.
 *
 * Drei Ausschluesse, die Dauerlaeufe verhindern:
 *
 *  - Mindestalter: ein frisch veroeffentlichter Artikel steht noch in der
 *    Indexierung, jede Bewegung dort ist Rauschen.
 *  - Sperrfrist: nach einer Aktualisierung ist derselbe Artikel fuer
 *    `cooldown_days` Tage aus dem Rennen. Sonst liefe er taeglich erneut,
 *    denn der Collector vergleicht ein gleitendes Fenster. Die Frist haengt
 *    am Artikel, nicht an der einzelnen Fassung (#93).
 *  - Laufende Aktualisierung: haengt am Artikel schon eine Fassung, die noch
 *    nicht live ist, wird keine zweite erzeugt.
 *
 * Die Auswahl der Abschnitte ist der eigentliche Kern: aktualisiert wird
 * nicht der Artikel, sondern der Absatz, in dem die veraltete Zahl steht.
 * Titel, Slug und alle uebrigen Abschnitte bleiben unangetastet — die URL
 * aendert sich nie, und bestandene Abschnitte behalten ihre Bewertung.
 *
 * Alle Methoden laufen im Tenant-Kontext.
 */
class RefreshSelector
{
    /** Grund: Positionsverlust oder CTR-Einbruch (Metrik-Collector, #23). */
    public const REASON_PERFORMANCE = 'performance';

    /** Grund: ein Beleg des Artikels ist abgelaufen. */
    public const REASON_STALE_FACT = 'stale_fact';

    /**
     * Wie viele Aktualisierungen der Mandant heute noch machen darf.
     */
    public function remainingToday(?CarbonImmutable $day = null): int
    {
        $limit = max(0, (int) config('content.refresh.max_per_tenant_per_day', 3));
        $used = ArticleDraft::query()->refreshesOn($day ?? CarbonImmutable::now())->count();

        return max(0, $limit - $used);
    }

    /**
     * Die dringendsten Kandidaten, hoechstens `$limit` Stueck.
     *
     * Sortierung: erst der Leistungsabfall (dort verliert das Portal
     * Sichtbarkeit), dann die Zahl der abgelaufenen Belege, dann das Alter
     * der Fassung.
     *
     * @return array<int, array{draft: ArticleDraft, reasons: array<int, array<string, mixed>>, stale_snippets: Collection<int, FactSnippet>}>
     */
    public function candidates(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $stale = $this->staleSnippets();
        $drafts = $this->pool($stale->keys()->all());
        $candidates = [];

        foreach ($drafts as $draft) {
            $found = $this->reasonsFor($draft, $stale);

            if ($found['reasons'] === []) {
                continue;
            }

            $candidates[] = ['draft' => $draft] + $found;
        }

        usort($candidates, static function (array $a, array $b): int {
            $weight = static fn (array $entry): int => $entry['stale_snippets']->count()
                + (self::hasPerformanceReason($entry['reasons']) ? 100 : 0);

            return $weight($b) <=> $weight($a)
                ?: ($a['draft']->published_at?->getTimestamp() ?? 0) <=> ($b['draft']->published_at?->getTimestamp() ?? 0);
        });

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Ein bestimmter Entwurf als Kandidat — fuer den Handbetrieb aus dem
     * Kommando. Die Ausschluesse (Sperrfrist, Mindestalter, laufende
     * Aktualisierung) gelten auch hier; nur so bleibt der Handlauf mit dem
     * Tageslauf deckungsgleich.
     *
     * @return array{draft: ArticleDraft, reasons: array<int, array<string, mixed>>, stale_snippets: Collection<int, FactSnippet>}|null
     */
    public function candidateFor(int $draftId): ?array
    {
        $stale = $this->staleSnippets();

        /** @var ArticleDraft|null $draft */
        $draft = $this->baseQuery()->whereKey($draftId)->first();

        if ($draft === null) {
            return null;
        }

        $found = $this->reasonsFor($draft, $stale);

        return $found['reasons'] === [] ? null : ['draft' => $draft] + $found;
    }

    /*
    |--------------------------------------------------------------------------
    | Abschnittsauswahl
    |--------------------------------------------------------------------------
    */

    /**
     * Welche Abschnitte ueberarbeitet werden und warum.
     *
     * Ein abgelaufener Beleg trifft den Abschnitt, in dem sein Wert im Text
     * steht — dieselbe Zuordnung, die das Qualitaetsgate (#15) fuer unbelegte
     * Zahlen benutzt. Findet sich der Wert nirgends, faellt der Beleg heraus:
     * der Artikel nennt ihn nicht, also ist nichts zu aktualisieren.
     *
     * Beim Leistungsabfall gibt es keine solche Spur. Dort wird der erste
     * inhaltliche Abschnitt ueberarbeitet: er beantwortet die Suchintention,
     * und genau daran misst die Suchmaschine den Artikel.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     * @param  array<int, array<string, mixed>>  $reasons
     * @param  Collection<int, FactSnippet>  $staleSnippets
     * @return array<string, array<int, string>> Abschnittskennung => Arbeitsanweisungen
     */
    public function sectionsToRefresh(array $sections, array $reasons, Collection $staleSnippets): array
    {
        $byId = [];

        foreach ($staleSnippets as $snippet) {
            $section = $this->sectionContaining($sections, $snippet);

            if ($section === null) {
                continue;
            }

            $byId[$section][] = __('Der Beleg „:label" (:value, Stand :period) ist seit :until nicht mehr gültig. Prüfen Sie die Angabe an der aktuellen Faktenliste: übernehmen Sie den neuen Wert, oder streichen Sie die Zahl, wenn dort keiner steht.', [
                'label' => $snippet->displayLabel(),
                'value' => trim((string) $snippet->value.' '.(string) $snippet->unit),
                'period' => (string) ($snippet->period ?: '—'),
                'until' => $this->validUntil($snippet)?->format('d.m.Y') ?? '—',
            ]);
        }

        if (self::hasPerformanceReason($reasons)) {
            $lead = $this->leadSection($sections);

            if ($lead !== null) {
                $byId[$lead][] = __('Dieser Artikel verliert an Sichtbarkeit (:detail). Beantworten Sie die Suchintention hier deutlicher und aktueller: schärfen Sie den Einstieg, ergänzen Sie belegte Angaben aus der Faktenliste und streichen Sie, was überholt ist.', [
                    'detail' => $this->performanceDetail($reasons),
                ]);
            }
        }

        $max = max(1, (int) config('content.refresh.max_sections', 4));

        return array_slice(
            array_map(
                static fn (array $instructions): array => array_values(array_unique($instructions)),
                $byId,
            ),
            0,
            $max,
            preserve_keys: true,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Kandidatensuche
    |--------------------------------------------------------------------------
    */

    /**
     * Grundmenge: live stehende Fassungen, die alt genug sind, keine laufende
     * Aktualisierung haben und nicht in der Sperrfrist stehen.
     */
    private function baseQuery(): Builder
    {
        $now = CarbonImmutable::now();
        $minAge = $now->subDays(max(0, (int) config('content.refresh.min_age_days', 30)));
        $cooldown = $now->subDays(max(0, (int) config('content.refresh.cooldown_days', 30)));
        $table = (new ArticleDraft)->getTable();

        return ArticleDraft::query()
            ->published()
            ->whereNotNull('article_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $minAge)
            // Eine Aktualisierung, die noch nicht live ist, oder eine, die
            // gerade erst live ging: in beiden Faellen ist der Artikel vorerst
            // aus dem Rennen.
            //
            // Die Frage gilt dem Artikel, nicht der Fassung (#93): nach einer
            // Aktualisierung ist die Kindfassung die veroeffentlichte, und die
            // hat selbst keine Kinder. Ein Ausschluss ueber die Beziehung
            // `refreshes` griffe damit ab dem Folgetag ins Leere. Gefragt ist
            // deshalb, ob zu demselben `article_id` irgendeine Fassung mit
            // Elternbezug innerhalb der Sperrfrist entstanden ist.
            ->whereNotExists(function (QueryBuilder $query) use ($table, $cooldown): void {
                $query->selectRaw('1')
                    ->from($table.' as refresh_versions')
                    ->whereColumn('refresh_versions.article_id', $table.'.article_id')
                    ->whereNotNull('refresh_versions.parent_draft_id')
                    ->where('refresh_versions.created_at', '>=', $cooldown);
            })
            // Ein Lauf, der nichts zu tun fand, vermerkt sich hier. Ohne die
            // Marke liefe derselbe Artikel am naechsten Tag erneut an.
            ->where(function (Builder $query) use ($cooldown): void {
                $query->whereNull('refresh_reason_json->last_attempt_at')
                    ->orWhere('refresh_reason_json->last_attempt_at', '<', $cooldown->toIso8601String());
            });
    }

    /**
     * Vorauswahl: markierte Artikel und solche mit abgelaufenen Belegen.
     *
     * @param  array<int, int>  $staleIds
     * @return Collection<int, ArticleDraft>
     */
    private function pool(array $staleIds): Collection
    {
        $pool = max(1, (int) config('content.refresh.candidate_pool', 100));

        /** @var Collection<int, ArticleDraft> $drafts */
        $drafts = $this->baseQuery()
            ->where(function (Builder $query) use ($staleIds): void {
                $query->where('needs_refresh', true);

                if ($staleIds !== []) {
                    // JSON_OVERLAPS statt einer OR-Kette ueber jede
                    // Beleg-ID: die Liste wird mit dem Bestand laenger.
                    //
                    // Bewusst als whereRaw: whereJsonOverlaps() schiebt einen
                    // Pfad als dritten Parameter in JSON_OVERLAPS, und die
                    // Funktion nimmt genau zwei ("Incorrect parameter count",
                    // MySQL 1582). Der Pfad muss deshalb in JSON_EXTRACT.
                    $query->orWhereRaw(
                        'json_overlaps(json_extract(`outline_json`, ?), ?)',
                        ['$."fact_snippet_ids"', json_encode(array_values(array_map('intval', $staleIds)))],
                    );
                }
            })
            ->orderBy('published_at')
            ->limit($pool)
            ->get();

        return $drafts;
    }

    /**
     * Ablaufdatum des Belegs als Datumsobjekt.
     */
    private function validUntil(FactSnippet $snippet): ?CarbonImmutable
    {
        $value = $snippet->valid_until;

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /**
     * Abgelaufene Faktenschnipsel des Mandanten, nach ID.
     *
     * @return Collection<int, FactSnippet>
     */
    private function staleSnippets(): Collection
    {
        return FactSnippet::query()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', CarbonImmutable::now())
            ->get()
            ->keyBy(fn (FactSnippet $snippet): int => (int) $snippet->getKey());
    }

    /**
     * @param  Collection<int, FactSnippet>  $stale
     * @return array{reasons: array<int, array<string, mixed>>, stale_snippets: Collection<int, FactSnippet>}
     */
    private function reasonsFor(ArticleDraft $draft, Collection $stale): array
    {
        $reasons = [];

        if ((bool) $draft->needs_refresh) {
            foreach ((array) (($draft->refresh_reason_json ?? [])['reasons'] ?? []) as $entry) {
                $reasons[] = [
                    'type' => self::REASON_PERFORMANCE,
                    'detail' => (string) ($entry['type'] ?? 'unbekannt'),
                    'from' => $entry['from'] ?? null,
                    'to' => $entry['to'] ?? null,
                ];
            }

            // Marke ohne Begruendung: der Collector hat sie gesetzt, das
            // Protokoll ist aber verloren gegangen. Der Grund bleibt gueltig.
            if ($reasons === []) {
                $reasons[] = ['type' => self::REASON_PERFORMANCE, 'detail' => 'unbekannt', 'from' => null, 'to' => null];
            }
        }

        $staleForDraft = $stale->only($draft->factSnippetIds())->values();

        foreach ($staleForDraft as $snippet) {
            $reasons[] = [
                'type' => self::REASON_STALE_FACT,
                'fact_snippet_id' => (int) $snippet->getKey(),
                'fact_key' => (string) $snippet->fact_key,
                'label' => $snippet->displayLabel(),
                'valid_until' => $this->validUntil($snippet)?->toDateString(),
            ];
        }

        return ['reasons' => $reasons, 'stale_snippets' => $staleForDraft];
    }

    /*
    |--------------------------------------------------------------------------
    | Hilfsmittel
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, array<string, mixed>>  $reasons
     */
    public static function hasPerformanceReason(array $reasons): bool
    {
        foreach ($reasons as $reason) {
            if (($reason['type'] ?? '') === self::REASON_PERFORMANCE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Der Abschnitt, in dem der Wert des Belegs im Fliesstext vorkommt.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     */
    private function sectionContaining(array $sections, FactSnippet $snippet): ?string
    {
        $value = trim((string) $snippet->value);

        if ($value === '') {
            return null;
        }

        foreach ($sections as $section) {
            $text = strip_tags($section['summary'].' '.$section['body']);

            if (str_contains($text, $value)) {
                return (string) $section['id'];
            }
        }

        return null;
    }

    /**
     * Der erste Abschnitt mit eigener Ueberschrift. `s0` — der Text vor der
     * ersten H2 — bleibt aussen vor: er ist in aller Regel leer, und der
     * FixSectionsStep koennte dort keine Ueberschrift zurueckgeben.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     */
    private function leadSection(array $sections): ?string
    {
        foreach ($sections as $section) {
            if (trim((string) $section['heading']) !== '' && trim((string) $section['body']) !== '') {
                return (string) $section['id'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reasons
     */
    private function performanceDetail(array $reasons): string
    {
        foreach ($reasons as $reason) {
            if (($reason['type'] ?? '') !== self::REASON_PERFORMANCE) {
                continue;
            }

            return match ((string) ($reason['detail'] ?? '')) {
                'position_drop' => __('Position von :from auf :to gefallen', [
                    'from' => $reason['from'] ?? '?',
                    'to' => $reason['to'] ?? '?',
                ]),
                'ctr_drop' => __('Klickrate deutlich eingebrochen'),
                default => __('Sichtbarkeit rückläufig'),
            };
        }

        return __('Sichtbarkeit rückläufig');
    }

    /**
     * Bequemer Einstieg fuer den Job: die Abschnitte einer Fassung.
     *
     * @return array<int, array{id: string, heading: string, summary: string, body: string}>
     */
    public function split(ArticleDraft $draft): array
    {
        return FixSectionsStep::split((string) $draft->body_html);
    }
}
