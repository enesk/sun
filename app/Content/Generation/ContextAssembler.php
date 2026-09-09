<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\Central\PromptTemplate;
use App\Content\Models\FactSnippet;
use App\Content\Models\GeoRegion;
use App\Content\Models\SeasonalTopic;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\InternalLinkResolver;
use App\Content\Services\PortalDataProvider;
use App\Content\Services\RegionScopeResolver;
use App\Content\Services\SerpInsightDto;
use App\Content\Services\SerpInsightService;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Support\BranchResolver;
use App\Models\Portal\City;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stellt den Kontext eines Artikelentwurfs zusammen (#14).
 *
 * Alles, was Geld kostet oder eine Abfrage braucht, passiert hier genau
 * einmal: Suchergebnisbild (#10), Faktenschnipsel (#11), Portal-Eigendaten,
 * Saison- und Nachrichtenanlass, interne Linkziele und der Branchen-Styleguide
 * (#13). Die Generierungsstufen bekommen danach nur noch das fertige
 * GenerationContext-Objekt.
 *
 * Keine dieser Quellen darf den Entwurf verhindern. Ein ausgefallener
 * SERP-Anbieter kostet Nebenfragen, kein Artikel; fehlende Portalzahlen
 * kosten den Regionalblock, nicht den Text. Nur der Regionalzuschnitt
 * ohne regionale Fakten ist ein echter Abbruchgrund — den entscheidet aber
 * der RegionalBlockStep, nicht der Assembler.
 *
 * Muss im Tenant-Kontext laufen.
 */
class ContextAssembler
{
    public function __construct(
        private readonly SerpInsightService $serp,
        private readonly PortalDataProvider $portal,
        private readonly InternalLinkResolver $links,
    ) {}

    /**
     * @param  bool  $freshSerp  Suchergebnisbild neu abrufen statt aus dem
     *                           Zwischenspeicher. Der Refresh-Loop (#24)
     *                           braucht den heutigen Stand — mit dem des
     *                           Erstentwurfs waere die Aktualisierung sinnlos.
     */
    public function assemble(
        Tenant $tenant,
        TopicCandidate $topic,
        ?string $forceScope = null,
        bool $freshSerp = false,
    ): GenerationContext {
        $settings = TenantContentSetting::current();
        $branch = BranchResolver::resolve($tenant) ?? 'default';
        $branchLabel = BranchResolver::label($branch) ?? 'Handwerk und Dienstleistung';

        $scope = $forceScope ?? (string) $topic->region_scope;
        $code = $scope === RegionScopeResolver::SCOPE_NATIONAL ? null : $topic->region_code;
        $intent = $this->intent($topic);

        $factSnippets = $this->factSnippets($topic, $scope, $code);

        return new GenerationContext(
            tenantId: (int) $tenant->getKey(),
            tenantName: (string) $tenant->name,
            branch: $branch,
            branchLabel: $branchLabel,
            styleguide: $this->styleguide($branch, (int) $tenant->getKey()),
            settings: $settings,
            topic: $topic,
            intent: $intent,
            primaryKeyword: (string) $topic->primary_keyword,
            secondaryKeywords: $this->secondaryKeywords($topic),
            regionScope: $scope,
            regionCode: $code,
            regionName: $this->regionName($scope, $code),
            serp: $this->serpInsight($topic, $scope, $code, $freshSerp),
            factSnippets: $factSnippets,
            sourceItems: $this->sourceItems($topic),
            portalData: $this->portalData($scope, $code),
            seasonalHook: $this->seasonalHook($topic),
            newsHook: $this->newsHook($topic),
            linkTargets: $this->links->forTopic($topic, $branchLabel),
            targetWords: $this->targetWords($intent),
        );
    }

    /**
     * Der Branchen-Styleguide (#13) als Klartext. Fehlt er, bleibt der
     * System-Prompt ohne Verbotsliste — der Artikel entsteht trotzdem, wird
     * aber im Qualitaetsgate strenger geprueft.
     */
    private function styleguide(string $branch, int $tenantId): string
    {
        $template = PromptTemplate::query()
            ->resolve("styleguide_{$branch}", $tenantId)
            ->first();

        if ($template === null && $branch !== 'default') {
            $template = PromptTemplate::query()->resolve('styleguide_default', $tenantId)->first();
        }

        if ($template === null) {
            Log::warning('Kein Branchen-Styleguide gefunden.', ['branch' => $branch, 'tenant_id' => $tenantId]);

            return 'Kein Branchen-Styleguide hinterlegt. Halten Sie sich an die allgemeinen '
                .'Redaktionsstandards und vermeiden Sie jede nicht belegte Angabe.';
        }

        return (string) $template->user_prompt;
    }

    private function intent(TopicCandidate $topic): string
    {
        $intent = (string) ($topic->intent ?: 'informational');

        // YMYL-Themen bleiben informational, auch wenn die Themenfindung etwas
        // anderes vorgeschlagen hat (#12).
        return $topic->informational_only ? 'informational' : $intent;
    }

    /**
     * @return array<int, string>
     */
    private function secondaryKeywords(TopicCandidate $topic): array
    {
        return array_values(array_filter(array_map(
            static fn ($keyword): string => is_string($keyword) ? trim($keyword) : '',
            (array) ($topic->secondary_keywords_json ?? []),
        )));
    }

    private function regionName(string $scope, ?string $code): string
    {
        if ($scope === RegionScopeResolver::SCOPE_NATIONAL || $code === null || trim($code) === '') {
            return 'Deutschland';
        }

        if ($scope === RegionScopeResolver::SCOPE_STATE) {
            return StateCatalog::name($code) ?? $code;
        }

        $region = GeoRegion::findByCode(RegionScopeResolver::SCOPE_CITY, $code);

        if ($region !== null) {
            return (string) $region->name;
        }

        $city = City::query()->where('slug', $code)->first(['id', 'name']);

        return $city !== null ? (string) $city->name : Str::headline($code);
    }

    /**
     * Faktenschnipsel fuer den Artikel: passend zur Region, noch gueltig,
     * eigene Themenschnipsel zuerst.
     *
     * Bewusst ohne Filter auf `article_draft_id`: die von #11 geschriebenen
     * Schnipsel sind ein gemeinsamer Bestand, kein Verbrauchsmaterial. Derselbe
     * Foerdersatz belegt einen bundesweiten und einen bayerischen Ratgeber, und
     * ein zweiter Generierungsversuch braucht dieselben Fakten wie der erste.
     * Welche Schnipsel ein Entwurf verwendet hat, steht in
     * `outline_json.fact_snippet_ids` und in `draft_sources`.
     *
     * @return Collection<int, FactSnippet>
     */
    private function factSnippets(TopicCandidate $topic, string $scope, ?string $code): Collection
    {
        $limit = max(4, (int) config('content.generation.max_fact_snippets', 14));

        return FactSnippet::query()
            ->stillValid()
            ->forRegion($scope, $code)
            ->orderByRaw('topic_candidate_id = ? DESC', [$topic->getKey()])
            ->orderByRaw('region_scope <> ? DESC', [RegionScopeResolver::SCOPE_NATIONAL])
            ->orderByDesc('retrieved_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Die Rohsignale hinter dem Thema — sie werden spaeter als draft_sources
     * verknuepft.
     *
     * @return Collection<int, SourceItem>
     */
    private function sourceItems(TopicCandidate $topic): Collection
    {
        $ids = array_map('intval', (array) ($topic->source_item_ids_json ?? []));

        if ($ids === []) {
            return new Collection;
        }

        return SourceItem::query()
            ->whereIn('id', $ids)
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * Portal-Eigendaten der Region. Bei bundesweiten Themen gibt es keine —
     * eine Deutschlandzahl waere kein Alleinstellungsmerkmal.
     *
     * @return array<string, mixed>|null
     */
    private function portalData(string $scope, ?string $code): ?array
    {
        if ($scope === RegionScopeResolver::SCOPE_NATIONAL || $code === null || trim($code) === '') {
            return null;
        }

        try {
            if ($scope === RegionScopeResolver::SCOPE_STATE) {
                return $this->portal->forState($code);
            }

            $city = City::query()->where('slug', $code)->orWhere('name', $code)->first();

            return $city === null ? null : $this->portal->forCity($city);
        } catch (\Throwable $exception) {
            Log::warning('Portal-Eigendaten fuer den Generator nicht ermittelbar.', [
                'scope' => $scope,
                'code' => $code,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Das Suchergebnisbild zum Hauptkeyword. Faellt der Anbieter aus, laeuft
     * der Artikel ohne PAA-Fragen und ohne Wettbewerber-Gliederung weiter.
     */
    private function serpInsight(TopicCandidate $topic, string $scope, ?string $code, bool $fresh = false): ?SerpInsightDto
    {
        try {
            return $this->serp->for(
                (string) $topic->primary_keyword,
                $scope === RegionScopeResolver::SCOPE_STATE ? $code : null,
                $fresh,
            );
        } catch (\Throwable $exception) {
            Log::warning('SERP-Bild fuer den Generator nicht verfuegbar.', [
                'keyword' => $topic->primary_keyword,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Saisonanlass aus dem Kalender (#13), sofern eines der Keywords passt.
     */
    private function seasonalHook(TopicCandidate $topic): ?string
    {
        $keywords = array_map(
            'mb_strtolower',
            array_merge([(string) $topic->primary_keyword], $this->secondaryKeywords($topic)),
        );

        $entries = SeasonalTopic::query()
            ->active()
            ->forMonth((int) Carbon::now()->format('n'))
            ->orderByDesc('weight')
            ->limit(20)
            ->get();

        foreach ($entries as $entry) {
            $haystack = array_map('mb_strtolower', array_merge(
                [(string) $entry->primary_keyword, (string) $entry->title],
                array_filter((array) ($entry->keywords_json ?? []), 'is_string'),
            ));

            foreach ($keywords as $keyword) {
                foreach ($haystack as $candidate) {
                    if ($keyword !== '' && $candidate !== '' && str_contains($candidate, $keyword)) {
                        return (string) $entry->title;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Nachrichtenanlass: das juengste Nachrichtensignal hinter dem Thema.
     */
    private function newsHook(TopicCandidate $topic): ?string
    {
        $item = $this->sourceItems($topic)
            ->filter(fn (SourceItem $item): bool => str_contains((string) $item->source_key, 'news'))
            ->sortByDesc('published_at')
            ->first();

        if ($item === null) {
            return null;
        }

        $date = $item->published_at?->format('d.m.Y');

        return trim(($date !== null ? "{$date}: " : '').(string) $item->title);
    }

    /**
     * Ziellaenge nach Suchintention. Der Korridor gilt unveraendert — er ist
     * die einzige Zahlenquelle fuer Artikellaengen (§4.2), eine zweite
     * Klammer um ihn herum gibt es nicht mehr. Fehlt die Intention, gilt
     * 'informational'.
     *
     * @return array{min: int, max: int}
     */
    private function targetWords(string $intent): array
    {
        $ranges = (array) config('content.generation.target_words', []);
        $range = (array) ($ranges[$intent] ?? $ranges['informational'] ?? [1300, 1800]);

        $min = (int) ($range[0] ?? 1300);
        $max = (int) ($range[1] ?? 1800);

        return ['min' => $min, 'max' => max($min, $max)];
    }
}
