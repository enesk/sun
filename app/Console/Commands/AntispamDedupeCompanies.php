<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\BotQuarantine;
use App\AntiSpam\Support\ScanReport;
use App\AntiSpam\Support\TenantSelection;
use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Bestandsdubletten der Selbsteintragung aufraeumen (#16).
 *
 *   php artisan antispam:dedupe-companies --tenant=28
 *   php artisan antispam:dedupe-companies --tenant=28 --apply
 *
 * Eine Dublette ist hier genau das, was CompanySignup ab #16 beim Anlegen
 * verhindert: derselbe `user_id`, derselbe slug-normalisierte `name`, dieselbe
 * `zipcode`. Zwei Betriebe desselben Inhabers an verschiedenen Standorten
 * sind also keine Dublette, zwei Schreibweisen desselben Betriebs schon.
 *
 * Der AELTESTE Eintrag der Gruppe bleibt (kleinste ID), die uebrigen werden
 * soft-geloescht — hart geloescht wird nie, `restore()` holt jeden Eintrag
 * zurueck. Ohne `--apply` zeigt der Lauf nur, was er tun wuerde; das ist die
 * Vorgabe, weil der Befund aus der Produktion kommt.
 *
 * Eintraege mit eigenen Daten werden ausgelassen: wer eine Beschreibung, ein
 * Logo, Bewertungen oder Premium hat, ist offenkundig gepflegt und nicht die
 * Kopie eines Doppelklicks. Solche Gruppen stehen im Bericht mit dem Grund
 * `gepflegt` und gehen in die Sichtung (#10), nicht in den Loeschlauf.
 */
class AntispamDedupeCompanies extends Command
{
    protected $signature = 'antispam:dedupe-companies
        {--tenant=* : Portale (ID, UUID, Name oder Domain); "*" oder ohne Angabe = alle}
        {--apply : Wirklich soft-löschen; ohne diesen Schalter nur anzeigen}';

    protected $description = 'Findet mehrfach eingetragene Betriebe desselben Nutzers (Name + PLZ) und soft-löscht die jüngeren';

    public function handle(BotQuarantine $quarantine): int
    {
        $apply = (bool) $this->option('apply');
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $this->components->info($apply ? 'Dubletten werden soft-gelöscht.' : 'Trockenlauf — es wird nichts gelöscht.');

        $rows = [];
        $portale = [];
        $geloescht = 0;
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $gruppen = $tenant->run(fn (): array => $this->groups());
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->error("{$tenant->name}: {$exception->getMessage()}");

                continue;
            }

            if ($gruppen === []) {
                continue;
            }

            foreach ($gruppen as $gruppe) {
                $rows[] = [
                    $tenant->id,
                    $tenant->name,
                    $gruppe['user_id'],
                    $gruppe['name'],
                    $gruppe['zipcode'],
                    (string) $gruppe['keep'],
                    $gruppe['drop'] === [] ? $gruppe['reason'] : implode(', ', $gruppe['drop']),
                ];
            }

            if ($apply) {
                try {
                    $geloescht += $tenant->run(fn (): int => $this->delete($quarantine, $gruppen));
                } catch (Throwable $exception) {
                    $failed = true;
                    $this->components->error("{$tenant->name}: {$exception->getMessage()}");

                    continue;
                }
            } else {
                $geloescht += array_sum(array_map(static fn (array $gruppe): int => count($gruppe['drop']), $gruppen));
            }

            $portale[] = [
                'tenant_id' => (int) $tenant->id,
                'tenant' => (string) $tenant->name,
                // Nur IDs in den Bericht, keine Namen — so haelt es ScanReport.
                'groups' => array_map(static fn (array $gruppe): array => [
                    'user_id' => $gruppe['user_id'],
                    'keep' => $gruppe['keep'],
                    'drop' => $gruppe['drop'],
                    'reason' => $gruppe['reason'],
                ], $gruppen),
            ];
        }

        if ($rows === []) {
            $this->components->info('Keine Dubletten gefunden.');
        } else {
            $this->table(['ID', 'Portal', 'Nutzer', 'Betrieb', 'PLZ', 'bleibt', $apply ? 'gelöscht' : 'würden'], $rows);
            $this->components->info($apply
                ? "{$geloescht} Einträge soft-gelöscht."
                : "{$geloescht} Einträge würden soft-gelöscht. Mit --apply ausführen.");
        }

        $path = ScanReport::write($apply ? 'dedupe-companies' : 'dedupe-companies-dry-run', [
            'deleted' => $geloescht,
            'portals' => $portale,
        ]);

        $this->components->info("Bericht: {$path}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Dublettengruppen des aktuellen Portals.
     *
     * @return list<array{user_id: int, name: string, zipcode: string, keep: int, drop: list<int>, reason: string}>
     */
    private function groups(): array
    {
        if (! Schema::hasTable('companies')) {
            throw new RuntimeException('Tabelle companies fehlt — tenants:migrate ausstehend.');
        }

        $gruppen = [];

        Company::query()
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Company $company): string => implode('|', [
                (int) $company->user_id,
                Str::slug((string) $company->name, '-', 'de'),
                (string) $company->zipcode,
            ]))
            ->each(function (Collection $eintraege) use (&$gruppen): void {
                if ($eintraege->count() < 2) {
                    return;
                }

                /** @var Company $keep */
                $keep = $eintraege->first();
                $rest = $eintraege->slice(1);
                $gepflegt = $rest->filter(fn (Company $company): bool => $this->isMaintained($company));

                $gruppen[] = [
                    'user_id' => (int) $keep->user_id,
                    'name' => (string) $keep->name,
                    'zipcode' => (string) $keep->zipcode,
                    'keep' => (int) $keep->id,
                    'drop' => $gepflegt->isEmpty()
                        ? $rest->map(static fn (Company $company): int => (int) $company->id)->values()->all()
                        : [],
                    'reason' => $gepflegt->isEmpty()
                        ? 'dublette'
                        : 'gepflegt: '.$gepflegt->map(static fn (Company $company): int => (int) $company->id)->implode(', '),
                ];
            });

        return $gruppen;
    }

    /**
     * Hat der Eintrag eigene Daten? Dann ist er keine Kopie eines
     * Doppelklicks und geht in die Sichtung statt in den Loeschlauf.
     */
    private function isMaintained(Company $company): bool
    {
        return filled($company->description)
            || filled($company->logo_path)
            || (int) $company->rating_count > 0
            || (bool) $company->is_premium
            || (bool) $company->is_verified;
    }

    /**
     * @param  list<array{keep: int, drop: list<int>}>  $gruppen
     */
    private function delete(BotQuarantine $quarantine, array $gruppen): int
    {
        $ids = array_merge(...array_map(static fn (array $gruppe): array => $gruppe['drop'], $gruppen));

        if ($ids === []) {
            return 0;
        }

        $companies = Company::query()->whereIn('id', $ids)->get();
        $companies->each(fn (Company $company) => $quarantine->softDelete($company));

        return $companies->count();
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        return TenantSelection::resolve((array) $this->option('tenant'));
    }
}
