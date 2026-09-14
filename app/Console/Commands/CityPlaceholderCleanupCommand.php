<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\City;
use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Entfernt Orte ohne echten Namen aus den Tenant-Datenbanken (#4).
 *
 * Ursache: Die SQL-Dumps des Python-Scrapers tragen fehlende Orte als Text
 * "None". Der Tenant-Import (CompanyImporter -> CityResolver) hat daraus einen
 * Ort "None" (Slug "none") angelegt und alle Firmen ohne Ort daran gehaengt.
 * Der Ort erschien dann als meistbesetzte Stadt in der Startseiten-Suchmaske
 * ("Finden Sie Unternehmen in None"), in Ortslisten und als Stadtseite.
 * Der Import ist seitdem abgesichert (City::isPlaceholderName()); dieses
 * Kommando raeumt den Bestand auf.
 *
 * Regeln:
 *
 * 1. Geschrieben wird ausschliesslich mit --write, Vorgabe ist der Trockenlauf.
 * 2. Firmen und Stellenanzeigen bleiben erhalten und verlieren nur die
 *    Ortszuordnung (city_id = null) — genau das hatten sie in der Quelle.
 * 3. Danach wird der Platzhalter-Ort geloescht und die Ortslisten-Caches des
 *    Tenants werden verworfen.
 */
class CityPlaceholderCleanupCommand extends Command
{
    protected $signature = 'cities:placeholder:cleanup
        {--write : Aenderungen tatsaechlich schreiben (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}';

    protected $description = 'Entfernt Importreste wie den Ort "None" und loest die Firmen davon';

    /**
     * Cache-Schluessel mit Ortslisten, die den Platzhalter enthalten koennen.
     * Schluessel mit IDs (Kategorie, verwandte Staedte) laufen nach 1 h aus.
     */
    private const CACHE_KEYS = [
        'portal.cities.hero',
        'portal.cities.sidebar',
        'portal.cities.public.index.top20',
        'portal.jobs.cities.sidebar',
        'portal.jobs.company_cities.sidebar',
        'portal.stats',
        'sun-v2.home.cities',
        'sun-v2.cities.index.v3',
    ];

    public function handle(): int
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

        $rows = [];
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $write, &$rows): void {
                    // Tenant ohne migrierte Portaltabellen
                    if (! Schema::hasTable('cities')) {
                        return;
                    }

                    $cities = City::query()->get(['id', 'name', 'slug'])
                        ->filter(fn (City $city): bool => City::isPlaceholderName($city->name));

                    foreach ($cities as $city) {
                        $companies = DB::table('companies')->where('city_id', $city->id)->count();
                        $jobs = Schema::hasTable('jobs') ? DB::table('jobs')->where('city_id', $city->id)->count() : 0;

                        if ($write) {
                            DB::transaction(function () use ($city): void {
                                DB::table('companies')->where('city_id', $city->id)->update(['city_id' => null]);

                                if (Schema::hasTable('jobs')) {
                                    DB::table('jobs')->where('city_id', $city->id)->update(['city_id' => null]);
                                }

                                $city->delete();
                            });
                        }

                        $rows[] = [
                            $tenant->name,
                            "\"{$city->name}\" (#{$city->id}, /{$city->slug})",
                            $companies,
                            $jobs,
                            $write ? 'entfernt' : 'würde entfernt',
                        ];
                    }

                    if ($write && $cities->isNotEmpty()) {
                        foreach (self::CACHE_KEYS as $key) {
                            TenantCache::forget($key);
                        }
                    }
                });
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        if ($rows === []) {
            $this->info('Keine Platzhalter-Orte gefunden.');

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Tenant', 'Ort', 'Firmen', 'Stellen', 'Ergebnis'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
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
