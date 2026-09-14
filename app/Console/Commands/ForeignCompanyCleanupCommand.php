<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\ForeignCompanyDetector;
use App\Support\TenantCache;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deaktiviert Betriebe ausserhalb Deutschlands in den Tenant-Portalen (#16).
 *
 * Ursache: Die Places-Suche lief ohne Regionsvorgabe, und weder GetCompanies
 * noch der Dump-Import haben das Land geprueft. So kamen z. B. "Gary's
 * Electric Service LLC" (Oklahoma) oder Schweizer und franzoesische Betriebe
 * ins Elektriker-Portal; PhoneNumber::toE164() gibt deren Nummern +49.
 * Erkennung siehe App\Support\ForeignCompanyDetector.
 *
 * Regeln:
 *
 * 1. Geschrieben wird ausschliesslich mit --write, Vorgabe ist der Trockenlauf.
 * 2. Betriebe werden nur deaktiviert (is_active = false), nicht geloescht —
 *    Bewertungen, Fotos und Slugs bleiben, der Schritt ist umkehrbar.
 * 3. Hinweise (NL-/CZ-Schreibweise der Rufnummer) werden nur angezeigt.
 * 4. Tenants, deren Bestand ueberwiegend keine deutschen PLZ traegt, werden
 *    uebersprungen — dort waere jeder Betrieb ein Treffer.
 */
class ForeignCompanyCleanupCommand extends Command
{
    protected $signature = 'companies:foreign:cleanup
        {--write : Betriebe tatsaechlich deaktivieren (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}
        {--details : Jeden Treffer und Hinweis einzeln auflisten}';

    protected $description = 'Findet und deaktiviert ausländische Betriebe (US/CH/FR/AT …) in deutschen Portalen';

    /** Mindestanteil deutscher PLZ, damit ein Tenant als deutscher Bestand gilt. */
    private const MIN_GERMAN_SHARE = 0.5;

    /** Ortslisten und Zaehler, die aktive Betriebe enthalten. */
    private const CACHE_KEYS = [
        'portal.cities.hero',
        'portal.cities.sidebar',
        'portal.cities.public.index.top20',
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

        $summary = [];
        $details = [];
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $write, &$summary, &$details): void {
                    if (! Schema::hasTable('companies')) {
                        return;
                    }

                    $withZip = DB::table('companies')->whereNotNull('zipcode')->where('zipcode', '!=', '')->count();
                    $germanZip = DB::table('companies')->whereRaw("zipcode REGEXP '^[0-9]{5}$'")->count();

                    if ($withZip > 0 && $germanZip / $withZip < self::MIN_GERMAN_SHARE) {
                        $summary[] = [$tenant->id, $tenant->name, '-', '-', '-', 'übersprungen (kein deutscher Bestand)'];

                        return;
                    }

                    $hits = [];
                    $reasons = [];
                    $hints = 0;

                    DB::table('companies')
                        ->select(['id', 'name', 'tel', 'zipcode', 'is_active'])
                        ->orderBy('id')
                        ->chunk(1000, function (Collection $companies) use ($tenant, &$hits, &$reasons, &$hints, &$details): void {
                            foreach ($companies as $company) {
                                $reason = ForeignCompanyDetector::reason($company->tel, $company->zipcode);
                                $hint = $reason === null ? ForeignCompanyDetector::hint($company->tel) : null;

                                if ($reason === null && $hint === null) {
                                    continue;
                                }

                                if ($reason !== null) {
                                    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;

                                    if ($company->is_active) {
                                        $hits[] = $company->id;
                                    }
                                } else {
                                    $hints++;
                                }

                                $details[] = [
                                    $tenant->id,
                                    $company->id,
                                    $company->name,
                                    $company->tel,
                                    $company->zipcode,
                                    ForeignCompanyDetector::LABELS[$reason ?? $hint],
                                    $company->is_active ? 'aktiv' : 'inaktiv',
                                ];
                            }
                        });

                    if ($write && $hits !== []) {
                        foreach (array_chunk($hits, 500) as $ids) {
                            DB::table('companies')->whereIn('id', $ids)->update(['is_active' => false, 'updated_at' => now()]);
                        }

                        foreach (self::CACHE_KEYS as $key) {
                            TenantCache::forget($key);
                        }
                    }

                    $summary[] = [
                        $tenant->id,
                        $tenant->name,
                        collect($reasons)->map(fn (int $n, string $r): string => ForeignCompanyDetector::LABELS[$r].": {$n}")->implode(', ') ?: '0',
                        count($hits),
                        $hints,
                        $write ? 'deaktiviert' : 'würde deaktiviert',
                    ];
                });
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        if ($this->option('details') && $details !== []) {
            $this->table(['Tenant', 'Firma', 'Name', 'Telefon', 'PLZ', 'Grund', 'Status'], $details);
            $this->newLine();
        }

        $this->table(['Tenant', 'Name', 'Treffer je Grund', 'Aktive Treffer', 'Hinweise', 'Ergebnis'], $summary);

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
