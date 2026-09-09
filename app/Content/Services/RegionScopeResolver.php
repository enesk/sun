<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\GeoRegion;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Sources\Support\StateCatalog;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Entscheidet den Regionszuschnitt eines Themas — mit Beleg (#12).
 *
 * Regionale Ratgeber sind der Grund, warum die Pipeline ueberhaupt lohnt, und
 * zugleich das groesste Risiko: 400 Staedte x ein Thema ergibt 400
 * Doorway-Seiten, die Google zu Recht abstraft. Deshalb gilt hier die
 * Umkehrung der Beweislast — 'national' ist die Vorgabe, und ein Zuschnitt auf
 * Bundesland oder Stadt entsteht nur, wenn mindestens ein regionaler Faktor
 * belegt ist:
 *
 *   - eine Foerderung oder eine Landesregelung mit Regionsbezug,
 *   - regionale Berichterstattung zum Thema,
 *   - regionale Suchnachfrage (Trends, Search Console),
 *   - amtliche Regionalstatistik,
 *   - Portal-Eigendaten mit mindestens config('content.topics.region.min_companies')
 *     gelisteten Betrieben in der Region.
 *
 * Jede Entscheidung wird in topic_candidates.region_reason im Klartext
 * protokolliert und die Belege selbst in region_evidence_json; ohne Beleg
 * steht dort, was geprueft wurde und warum es national bleibt.
 *
 * Abgrenzung zu App\Content\Sources\Support\RegionResolver: der erkennt Orte
 * in freiem Text (Connectoren, #9/#11). Dieser Dienst hier entscheidet, ob ein
 * erkannter Ort ueberhaupt einen eigenen Zuschnitt rechtfertigt. Er heisst
 * bewusst anders, damit die beiden Klassen nicht namensgleich in denselben
 * Dateien importiert werden.
 */
class RegionScopeResolver
{
    public const SCOPE_NATIONAL = 'national';

    public const SCOPE_STATE = 'state';

    public const SCOPE_CITY = 'city';

    /**
     * Suchbegriffe je Zuschnitt, im Schluessel mit dem Mandanten versehen:
     * ein Queue-Worker arbeitet nacheinander mehrere Portale ab, und die
     * Ortsliste ist Tenant-Daten.
     *
     * @var array<string, array<string, array{scope: string, code: string, name: string, state_code: ?string}>>
     */
    private array $needleCache = [];

    /**
     * Regionszuschnitt eines Themas.
     *
     * @param  string  $text  Titel und Keywords des Kandidaten
     * @param  Collection<int, SourceItem>  $sourceItems  Rohsignale hinter dem Thema
     * @return array{scope: string, code: ?string, name: ?string, reason: string, evidence: array<int, array<string, mixed>>}
     */
    public function decide(
        string $text,
        Collection $sourceItems,
        TenantContentSetting $settings,
        ?string $proposedScope = null,
        ?string $proposedCode = null,
    ): array {
        $allowed = $this->allowedScopes($settings);
        $checked = [];

        foreach ($this->candidates($text, $proposedScope, $proposedCode) as $candidate) {
            if (! in_array($candidate['scope'], $allowed, true)) {
                $checked[] = "{$candidate['name']} (Zuschnitt vom Mandanten nicht freigegeben)";

                continue;
            }

            $evidence = $this->evidenceFor($candidate, $sourceItems);

            if ($evidence !== []) {
                return [
                    'scope' => $candidate['scope'],
                    'code' => $candidate['code'],
                    'name' => $candidate['name'],
                    'reason' => $this->reasonFor($candidate, $evidence),
                    'evidence' => $evidence,
                ];
            }

            $checked[] = "{$candidate['name']} (kein regionaler Faktor belegt)";
        }

        return [
            'scope' => self::SCOPE_NATIONAL,
            'code' => null,
            'name' => null,
            'reason' => $checked === []
                ? 'Bundesweit: im Thema und in den Quellen steht kein Ortsbezug.'
                : 'Bundesweit: geprueft wurde '.implode(', ', $checked).'.',
            'evidence' => [],
        ];
    }

    /**
     * Moegliche Zuschnitte, beste Vermutung zuerst: der Vorschlag des Modells,
     * danach was in Titel und Keywords selbst steht. Stadt schlaegt Bundesland,
     * weil der engere Zuschnitt der aussagekraeftigere ist.
     *
     * @return array<int, array{scope: string, code: string, name: string, state_code: ?string}>
     */
    private function candidates(string $text, ?string $proposedScope, ?string $proposedCode): array
    {
        $candidates = [];

        $proposed = $this->fromProposal($proposedScope, $proposedCode);

        if ($proposed !== null) {
            $candidates[$proposed['scope'].':'.$proposed['code']] = $proposed;
        }

        foreach ($this->detect($text) as $detected) {
            $candidates[$detected['scope'].':'.$detected['code']] ??= $detected;
        }

        return array_values($candidates);
    }

    /**
     * @return array{scope: string, code: string, name: string, state_code: ?string}|null
     */
    private function fromProposal(?string $scope, ?string $code): ?array
    {
        if ($scope === null || $code === null || $scope === self::SCOPE_NATIONAL || trim($code) === '') {
            return null;
        }

        $region = GeoRegion::findByCode($scope, trim($code));

        if ($region === null && $scope === self::SCOPE_STATE) {
            // Das Modell nennt Bundeslaender gern im Klartext statt als ISO-Code.
            $iso = StateCatalog::fromText($code);
            $region = $iso !== null ? GeoRegion::findByCode(self::SCOPE_STATE, $iso) : null;
        }

        if ($region === null) {
            return null;
        }

        return [
            'scope' => (string) $region->scope,
            'code' => (string) $region->code,
            'name' => (string) $region->name,
            'state_code' => $region->stateCode(),
        ];
    }

    /**
     * Orte, die im Text selbst stehen. Gematcht wird an Wortgrenzen auf der
     * gleichen Normalform wie im RegionResolver der Connectoren (Umlaute
     * ausgeschrieben), damit "waermepumpe koeln" auch "Köln" findet.
     *
     * @return array<int, array{scope: string, code: string, name: string, state_code: ?string}>
     */
    private function detect(string $text): array
    {
        $haystack = $this->normalize($text);

        if ($haystack === '') {
            return [];
        }

        $found = [];

        foreach ([self::SCOPE_CITY, self::SCOPE_STATE] as $scope) {
            foreach ($this->needles($scope) as $needle => $region) {
                if ($this->contains($haystack, $needle)) {
                    $found[] = $region;
                }
            }
        }

        return $found;
    }

    /**
     * Suchbegriffe eines Zuschnitts, laengste zuerst — sonst gewinnt
     * "Frankfurt" gegen "Frankfurt am Main".
     *
     * @return array<string, array{scope: string, code: string, name: string, state_code: ?string}>
     */
    private function needles(string $scope): array
    {
        $cacheKey = (tenant()?->getTenantKey() ?? 'central').':'.$scope;

        if (isset($this->needleCache[$cacheKey])) {
            return $this->needleCache[$cacheKey];
        }

        $needles = [];

        GeoRegion::query()
            ->where('scope', $scope)
            ->where('is_active', true)
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->cursor()
            ->each(function (GeoRegion $region) use (&$needles, $scope): void {
                $name = trim((string) $region->name);

                // Sehr kurze Ortsnamen ("Aue", "Ulm") erzeugen zu viele
                // Fehltreffer in Keyword-Ketten.
                if (mb_strlen($name) < 4) {
                    return;
                }

                $needle = $this->normalize($name);

                if ($needle === '' || isset($needles[$needle])) {
                    return;
                }

                $needles[$needle] = [
                    'scope' => $scope,
                    'code' => (string) $region->code,
                    'name' => $name,
                    'state_code' => $region->stateCode(),
                ];
            });

        return $this->needleCache[$cacheKey] = $needles;
    }

    /**
     * Belege fuer einen Zuschnitt: passende Rohsignale plus, bei genug
     * gelisteten Betrieben, die Portal-Eigendaten.
     *
     * @param  array{scope: string, code: string, name: string, state_code: ?string}  $candidate
     * @param  Collection<int, SourceItem>  $sourceItems
     * @return array<int, array<string, mixed>>
     */
    private function evidenceFor(array $candidate, Collection $sourceItems): array
    {
        $factors = (array) config('content.topics.region.evidence_source_keys', []);
        $maxAge = CarbonImmutable::now()->subDays(
            max(1, (int) config('content.topics.region.evidence_max_age_days', 45)),
        );

        $evidence = [];

        foreach ($sourceItems as $item) {
            $factor = $factors[(string) $item->source_key] ?? null;

            if ($factor === null || ! $this->itemMatches($item, $candidate)) {
                continue;
            }

            if ($item->fetched_at !== null && $item->fetched_at->lt($maxAge)) {
                continue;
            }

            $evidence[] = [
                'factor' => $factor,
                'source_key' => (string) $item->source_key,
                'source_item_id' => (int) $item->getKey(),
                'detail' => (string) $item->title,
            ];
        }

        $companies = $this->companyCount($candidate);
        $minCompanies = max(1, (int) config('content.topics.region.min_companies', 5));

        if ($companies >= $minCompanies) {
            $evidence[] = [
                'factor' => 'Portal-Eigendaten',
                'source_key' => 'portal_data',
                'source_item_id' => null,
                'detail' => "{$companies} gelistete Betriebe in {$candidate['name']}",
            ];
        }

        return $evidence;
    }

    /**
     * Gehoert ein Rohsignal zur geprueften Region? Ein Stadtsignal belegt auch
     * das Bundesland, in dem die Stadt liegt — umgekehrt nicht.
     *
     * @param  array{scope: string, code: string, name: string, state_code: ?string}  $candidate
     */
    private function itemMatches(SourceItem $item, array $candidate): bool
    {
        $scope = (string) $item->region_scope;
        $code = $item->region_code !== null ? (string) $item->region_code : null;

        if ($code === null || $scope === self::SCOPE_NATIONAL) {
            return false;
        }

        if ($scope === $candidate['scope'] && $code === $candidate['code']) {
            return true;
        }

        if ($candidate['scope'] === self::SCOPE_STATE && $scope === self::SCOPE_CITY) {
            $city = GeoRegion::findByCode(self::SCOPE_CITY, $code);

            return $city?->state_code === $candidate['code'];
        }

        return false;
    }

    /**
     * Gelistete Betriebe in der Region. Eine Zaehlabfrage je geprueftem
     * Zuschnitt; die Unterabfrage auf `cities` bleibt indexgestuetzt.
     *
     * @param  array{scope: string, code: string, name: string, state_code: ?string}  $candidate
     */
    private function companyCount(array $candidate): int
    {
        $connection = (new Company)->getConnectionName();

        if (! Schema::connection($connection)->hasTable('companies') || ! Schema::connection($connection)->hasTable('cities')) {
            return 0;
        }

        $cities = City::query()->select('id');

        if ($candidate['scope'] === self::SCOPE_CITY) {
            $cities->where('slug', $candidate['code']);
        } else {
            $stateName = StateCatalog::name($candidate['code']) ?? $candidate['name'];
            $cities->where('administrative_area_level_1', $stateName);
        }

        return (int) Company::query()
            ->where('is_active', true)
            ->whereIn('city_id', $cities)
            ->count();
    }

    /**
     * @param  array{scope: string, code: string, name: string, state_code: ?string}  $candidate
     * @param  array<int, array<string, mixed>>  $evidence
     */
    private function reasonFor(array $candidate, array $evidence): string
    {
        $label = $candidate['scope'] === self::SCOPE_CITY ? 'Stadt' : 'Bundesland';
        $factors = array_values(array_unique(array_map(
            static fn (array $entry): string => (string) $entry['factor'],
            $evidence,
        )));

        return "{$label} {$candidate['name']} ({$candidate['code']}): belegt durch ".implode(', ', $factors).'.';
    }

    /**
     * @return array<int, string>
     */
    private function allowedScopes(TenantContentSetting $settings): array
    {
        $allowed = $settings->allowed_region_scopes_json;

        if (! is_array($allowed) || $allowed === []) {
            return [self::SCOPE_NATIONAL, self::SCOPE_STATE, self::SCOPE_CITY];
        }

        return array_values(array_map(static fn ($scope): string => (string) $scope, $allowed));
    }

    private function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return (bool) preg_match('/(?:^|\s)'.preg_quote($needle, '/').'(?:$|\s)/u', $haystack);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
