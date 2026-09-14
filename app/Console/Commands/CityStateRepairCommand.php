<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Models\GeoRegion;
use App\Content\Sources\Support\StateCatalog;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\CityStateResolver;
use App\Support\ForeignCompanyDetector;
use App\Support\GermanState;
use App\Support\TenantCache;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Korrigiert Bundeslaender deutscher Orte, die kein Bundesland sind (#18).
 *
 * Ursache: Der Tenant-Import (CompanyImporter -> CityResolver) hat eine Stadt
 * mit dem Bundesland des ersten Place angelegt, der auf sie traf. War das ein
 * US-Betrieb mit gleicher fuenfstelliger PLZ (#16), hiess Beverstedt danach
 * "North Carolina"; alle spaeteren deutschen Betriebe fanden die Stadt ueber
 * Name + PLZ und aenderten nichts mehr. Der Import prueft das Bundesland seitdem
 * ueber CityStateResolver; dieses Kommando raeumt den Bestand auf.
 *
 * Regeln:
 *
 * 1. Geschrieben wird ausschliesslich mit --write, Vorgabe ist der Trockenlauf.
 * 2. Neues Bundesland: abweichende Schreibweise eines Bundeslands, sonst die
 *    klare Mehrheit der Orte mit gleicher PLZ bzw. gleichem PLZ-Bereich.
 * 3. Die PLZ entscheidet nur, wenn mindestens ein Betrieb des Orts nicht als
 *    auslaendisch erkannt wird (ForeignCompanyDetector). Haengen nur US-/FR-
 *    Betriebe am Ort, kann der Ort selbst auslaendisch sein — "Greece"
 *    (New York, PLZ 14615) oder "Volgelsheim" (Grand Est, PLZ 68600) laegen
 *    sonst in Brandenburg bzw. Hessen.
 * 4. Ohne klare Mehrheit (z. B. Orte in Suedtirol mit PLZ 39xxx) und bei
 *    Kollision mit einem gleichnamigen Ort im Ziel-Bundesland bleibt die Zeile
 *    unveraendert und wird nur gemeldet — Orte werden nie zusammengelegt.
 * 5. Leere Bundeslaender werden nicht angefasst.
 * 6. Portalorte in geo_regions (GeoRegionSeeder), deren state_code wegen des
 *    fremden Bundeslands leer blieb, bekommen den ISO-Code nachgetragen.
 */
class CityStateRepairCommand extends Command
{
    protected $signature = 'cities:state:repair
        {--write : Aenderungen tatsaechlich schreiben (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}
        {--details : Jeden Ort einzeln auflisten}';

    protected $description = 'Korrigiert Orte mit fremden Regionen (US/FR/IT) als Bundesland anhand der PLZ';

    /** Mindestanteil gueltiger Bundeslaender, damit ein Tenant als deutscher Bestand gilt. */
    private const MIN_GERMAN_SHARE = 0.5;

    /** Ortslisten, die nach Bundesland gliedern oder es anzeigen. */
    private const CACHE_KEYS = [
        'portal.cities.hero',
        'portal.cities.sidebar',
        'portal.cities.public.index.top20',
        'sun-v2.home.cities',
        'sun-v2.cities.index.v3',
    ];

    public function handle(CityStateResolver $resolver): int
    {
        $write = (bool) $this->option('write');

        if (! $write) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Zum Schreiben: --write');
            $this->newLine();
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine passenden Tenants gefunden.');

            return self::FAILURE;
        }

        $summary = [];
        $details = [];
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $write, $resolver, &$summary, &$details): void {
                    if (! Schema::hasTable('cities')) {
                        return;
                    }

                    $withState = City::query()->whereNotNull('administrative_area_level_1')->where('administrative_area_level_1', '!=', '')->count();
                    $german = City::query()->whereIn('administrative_area_level_1', GermanState::NAMES)->count();

                    if ($withState === $german) {
                        $summary[] = [$tenant->id, $tenant->name, 0, 0, 0, 0, 'sauber'];

                        return;
                    }

                    if ($german / $withState < self::MIN_GERMAN_SHARE) {
                        $summary[] = [$tenant->id, $tenant->name, $withState - $german, 0, 0, 0, 'übersprungen (kein deutscher Bestand)'];

                        return;
                    }

                    $counts = ['found' => 0, 'fixed' => 0, 'unresolved' => 0, 'conflict' => 0];

                    City::query()
                        ->whereNotNull('administrative_area_level_1')
                        ->where('administrative_area_level_1', '!=', '')
                        ->whereNotIn('administrative_area_level_1', GermanState::NAMES)
                        ->with('companies:id,city_id,tel,zipcode')
                        ->orderBy('id')
                        ->get()
                        ->each(function (City $city) use ($tenant, $write, $resolver, &$counts, &$details): void {
                            $counts['found']++;
                            $old = (string) $city->administrative_area_level_1;
                            $new = GermanState::canonical($old);
                            $result = $write ? 'korrigiert' : 'würde korrigiert';

                            if ($new === null && ! $this->hasGermanCompany($city)) {
                                $result = 'nur ausländische Firmen';
                            } elseif ($new === null) {
                                $new = $resolver->fromZipcode($city->zipcode, $city->id);
                                $result = $new === null ? 'PLZ nicht eindeutig' : $result;
                            }

                            $conflict = $new === null ? null : City::query()
                                ->where('name', $city->name)
                                ->where('administrative_area_level_1', $new)
                                ->whereKeyNot($city->id)
                                ->value('id');

                            if ($new === null) {
                                $counts['unresolved']++;
                            } elseif ($conflict !== null) {
                                $counts['conflict']++;
                                $result = "Konflikt mit Ort #{$conflict}";
                            } else {
                                $counts['fixed']++;

                                if ($write) {
                                    $city->update(['administrative_area_level_1' => $new]);
                                    TenantCache::forget("portal.cities.related.{$city->id}");
                                }
                            }

                            $details[] = [$tenant->id, $city->id, $city->name, $city->zipcode, $old, $new ?? '-', $city->companies->count(), $result];
                        });

                    if ($write && $counts['fixed'] > 0) {
                        foreach (self::CACHE_KEYS as $key) {
                            TenantCache::forget($key);
                        }
                    }

                    $geo = $this->syncGeoRegions($write);

                    $summary[] = [
                        $tenant->id,
                        $tenant->name,
                        $counts['found'],
                        $counts['fixed'],
                        $counts['unresolved'],
                        $counts['conflict'],
                        ($write ? 'geschrieben' : 'Trockenlauf').($geo > 0 ? ", geo_regions: {$geo}" : ''),
                    ];
                });
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        if ($this->option('details') && $details !== []) {
            $this->table(['Tenant', 'Ort', 'Name', 'PLZ', 'Bisher', 'Neu', 'Firmen', 'Ergebnis'], $details);
            $this->newLine();
        }

        $this->table(['Tenant', 'Name', 'Fremde Regionen', 'Korrigierbar', 'Offen (PLZ/nur Ausland)', 'Konflikte', 'Ergebnis'], $summary);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Traegt state_code bei Portalorten nach, deren Ort inzwischen ein gueltiges
     * Bundesland hat. Liefert die Anzahl (geschriebener) Zeilen.
     */
    private function syncGeoRegions(bool $write): int
    {
        if (! Schema::hasTable('geo_regions')) {
            return 0;
        }

        $synced = 0;

        GeoRegion::query()
            ->where('scope', GeoRegion::SCOPE_CITY)
            ->where('source', 'portal')
            ->whereNull('state_code')
            ->get()
            ->each(function (GeoRegion $region) use ($write, &$synced): void {
                $state = City::query()
                    ->where('slug', $region->code)
                    ->whereIn('administrative_area_level_1', GermanState::NAMES)
                    ->value('administrative_area_level_1');
                $iso = StateCatalog::fromText($state);

                if ($iso === null) {
                    return;
                }

                $synced++;

                if ($write) {
                    $region->update(['state_code' => $iso]);
                }
            });

        return $synced;
    }

    private function hasGermanCompany(City $city): bool
    {
        return $city->companies->contains(
            static fn (Company $company): bool => ForeignCompanyDetector::reason($company->tel, $company->zipcode) === null,
        );
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        /** @var array<int, string> $filter */
        $filter = array_filter((array) $this->option('tenant'), static fn ($v): bool => (string) $v !== '');

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->get();

        if ($filter === []) {
            return $tenants;
        }

        return $tenants->filter(static function (Tenant $tenant) use ($filter): bool {
            foreach ($filter as $needle) {
                if ((string) $tenant->id === (string) $needle
                    || (string) $tenant->uuid === (string) $needle
                    || mb_strtolower((string) $tenant->name) === mb_strtolower((string) $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
