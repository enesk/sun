<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Models\GeoRegion;
use App\Content\Sources\Support\StateCatalog;
use App\Models\Portal\City;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seedet die Geo-Liste der Themenfindung (#12) je Mandant.
 *
 * Drei Quellen in dieser Reihenfolge:
 *   1. die 16 Bundeslaender aus config('content.regions.states') — der
 *      ISO-3166-2-Code ist dort schon fuehrend (#11),
 *   2. die 400 Staedte ab 30.000 Einwohnern aus database/data/geo_cities_de.php,
 *   3. die Orte, die das Portal selbst in `cities` fuehrt, in (2) fehlen und
 *      genug gelistete Betriebe haben, um je ein regionaler Beleg zu werden
 *      (source = 'portal', ohne Einwohnerzahl).
 *
 * Punkt 3 ist bewusst begrenzt: die Portal-Ortstabelle fuehrt jede deutsche
 * Gemeinde, also rund 10.000 Zeilen je Mandant. In der Geo-Liste haben davon
 * nur die etwas verloren, in denen ueberhaupt Betriebe gelistet sind — alle
 * anderen wuerden die Ortserkennung verlangsamen, ohne je einen Zuschnitt zu
 * belegen.
 *
 * Idempotent ueber (scope, code): ein zweiter Lauf aktualisiert Name,
 * Einwohnerzahl und Koordinaten, legt aber nichts doppelt an. Der Slug ist
 * zugleich der region_code einer Stadt und damit Bestandteil von URLs — er
 * wird deshalb nie nachtraeglich geaendert.
 */
class GeoRegionSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command?->warn('Keine Mandanten vorhanden, geo_regions bleibt leer.');

            return;
        }

        $states = $this->states();
        $cities = $this->cities();

        $touched = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($states, $cities, &$touched): void {
                if (! Schema::hasTable('geo_regions')) {
                    return;
                }

                foreach ($states as $row) {
                    $this->store($row);
                    $touched++;
                }

                foreach ($cities as $row) {
                    $this->store($row);
                    $touched++;
                }

                // Ergaenzungen werden bei jedem Lauf neu bestimmt: ein Ort,
                // der seine Betriebe verloren hat, gehoert nicht mehr hinein.
                GeoRegion::query()->where('source', 'portal')->delete();

                $touched += $this->addPortalCities($cities);
            });
        }

        $this->command?->info(
            "Geo-Regionen: {$touched} Eintraege ueber {$tenants->count()} Mandanten geschrieben ("
            .count($states).' Bundeslaender, '.count($cities).' Staedte je Mandant plus Portalorte).',
        );
    }

    /**
     * Die 16 Bundeslaender. Code = ISO-3166-2, Slug = Kleinschreibung des
     * Namens, wie ihn die Ratgeber-URLs verwenden.
     *
     * @return array<int, array<string, mixed>>
     */
    private function states(): array
    {
        $states = [];

        foreach ((array) config('content.regions.states', []) as $iso => $state) {
            $name = (string) ($state['name'] ?? $iso);

            $states[] = [
                'scope' => GeoRegion::SCOPE_STATE,
                'code' => (string) $iso,
                'name' => $name,
                'slug' => Str::slug($name),
                'state_code' => (string) $iso,
                'population' => null,
                'source' => 'seed',
            ];
        }

        return $states;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cities(): array
    {
        $path = database_path('data/geo_cities_de.php');

        if (! is_readable($path)) {
            $this->command?->warn("Staedteliste fehlt: {$path}");

            return [];
        }

        $cities = [];

        /** @var array<string, array<int, array{0: string, 1: int}>> $data */
        $data = require $path;

        foreach ($data as $stateCode => $entries) {
            foreach ($entries as [$name, $population]) {
                if ((int) $population < GeoRegion::MIN_POPULATION) {
                    continue;
                }

                $cities[] = [
                    'scope' => GeoRegion::SCOPE_CITY,
                    'code' => Str::limit(Str::slug($name), 64, ''),
                    'name' => $name,
                    'slug' => Str::limit(Str::slug($name), 64, ''),
                    'state_code' => $stateCode,
                    'population' => (int) $population,
                    'source' => 'seed',
                ];
            }
        }

        return $cities;
    }

    /**
     * Orte des Portals, die nicht in der amtlichen Liste stehen. Sie sind fuer
     * die Regionserkennung wichtig, weil das Portal dort Betriebe fuehrt —
     * ohne Einwohnerzahl, damit sie in Ranglisten hinten stehen.
     *
     * @param  array<int, array<string, mixed>>  $known
     */
    private function addPortalCities(array $known): int
    {
        if (! Schema::connection((new City)->getConnectionName())->hasTable('cities')) {
            return 0;
        }

        $seen = array_flip(array_column($known, 'code'));
        $added = 0;

        $minCompanies = max(1, (int) config('content.topics.region.min_companies', 5));

        City::query()
            ->select(['name', 'slug', 'administrative_area_level_1', 'latitude', 'longitude'])
            ->when(
                Schema::connection((new City)->getConnectionName())->hasTable('companies'),
                fn ($query) => $query
                    ->withCount(['companies' => fn ($inner) => $inner->where('is_active', true)])
                    ->having('companies_count', '>=', $minCompanies),
            )
            ->cursor()
            ->each(function (City $city) use (&$seen, &$added): void {
                $name = trim((string) $city->name);

                if ($name === '') {
                    return;
                }

                $code = Str::limit((string) ($city->slug ?: Str::slug($name)), 64, '');

                if ($code === '' || isset($seen[$code])) {
                    return;
                }

                $seen[$code] = true;
                $added++;

                $this->store([
                    'scope' => GeoRegion::SCOPE_CITY,
                    'code' => $code,
                    'name' => $name,
                    'slug' => $code,
                    'state_code' => $this->stateCodeOf((string) $city->administrative_area_level_1),
                    'population' => null,
                    'latitude' => $city->latitude !== null ? (float) $city->latitude : null,
                    'longitude' => $city->longitude !== null ? (float) $city->longitude : null,
                    'source' => 'portal',
                ]);
            });

        return $added;
    }

    /**
     * Die Portal-Tabelle fuehrt das Bundesland als Klarnamen; die Geo-Liste
     * braucht den ISO-Code.
     */
    private function stateCodeOf(string $name): ?string
    {
        if (trim($name) === '') {
            return null;
        }

        return StateCatalog::fromText($name);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function store(array $row): void
    {
        GeoRegion::query()->updateOrCreate(
            ['scope' => $row['scope'], 'code' => $row['code']],
            [
                'name' => $row['name'],
                'slug' => $row['slug'],
                'state_code' => $row['state_code'] ?? null,
                'population' => $row['population'] ?? null,
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
                'source' => $row['source'] ?? 'seed',
                'is_active' => true,
            ],
        );
    }
}
