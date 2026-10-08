<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\BotQuarantine;
use App\AntiSpam\Support\ScanReport;
use App\AntiSpam\Support\TenantSelection;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Ablauf der Quarantaene (#10): Soft-Delete nach 14 Tagen ohne Freigabe.
 *
 *   php artisan antispam:expire-quarantine --dry-run
 *   php artisan antispam:expire-quarantine
 *   php artisan antispam:expire-quarantine --days=30 --tenant=28
 *
 * Laeuft taeglich im Scheduler (routes/console.php). Betroffen ist genau, was
 * seit `antispam.suspected_bots.quarantine_days` markiert ist und nicht
 * freigegeben wurde — ein Datensatz mit `suspected_bot_cleared_at` wird nie
 * geloescht, auch nicht Monate spaeter.
 *
 * Geloescht wird ausschliesslich soft (`deleted_at`). Die Markierung bleibt
 * stehen: sie ist die Begruendung und wird gebraucht, wenn ein Datensatz doch
 * wiederhergestellt werden soll (`restore()`).
 *
 * Jeder Lauf schreibt einen Bericht nach storage/app/antispam/ und eine Zeile
 * ins Log — auch ein Lauf ohne Treffer, damit nachweisbar bleibt, dass die
 * Frist ueberwacht wird.
 */
class AntispamExpireQuarantine extends Command
{
    protected $signature = 'antispam:expire-quarantine
        {--tenant=* : Portale (ID, UUID, Name oder Domain); "*" oder ohne Angabe = alle}
        {--dry-run : Nur anzeigen, nichts löschen}
        {--days= : Frist in Tagen statt antispam.suspected_bots.quarantine_days}';

    protected $description = 'Soft-löscht Datensätze, die länger als die Frist in Quarantäne stehen und nicht freigegeben wurden';

    public function handle(BotQuarantine $quarantine): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = $this->days();

        if ($days === null) {
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $this->components->info(
            ($dryRun ? 'Trockenlauf — ' : '')
            ."Frist {$days} Tage, markiert vor dem {$cutoff->format('d.m.Y H:i')}."
        );

        $accounts = $this->expireAccounts($quarantine, $cutoff, $tenants, $dryRun);

        $rows = [];
        $listings = 0;
        $failed = false;

        foreach ($tenants as $tenant) {
            try {
                $count = $tenant->run(fn (): int => $this->expireListings($quarantine, $cutoff, $dryRun));
            } catch (Throwable $exception) {
                $failed = true;
                $this->components->error("{$tenant->name}: {$exception->getMessage()}");

                $rows[] = [$tenant->id, $tenant->name, 'Fehler'];

                continue;
            }

            $listings += $count;
            $rows[] = [$tenant->id, $tenant->name, (string) $count];
        }

        $this->table(['ID', 'Portal', $dryRun ? 'Einträge (würden)' : 'Einträge gelöscht'], $rows);

        $this->components->info(
            $dryRun
                ? "{$accounts} Konten und {$listings} Einträge würden soft-gelöscht."
                : "{$accounts} Konten und {$listings} Einträge soft-gelöscht."
        );

        $path = ScanReport::write($dryRun ? 'expire-quarantine-dry-run' : 'expire-quarantine', [
            'days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'accounts_deleted' => $accounts,
            'listings_deleted' => $listings,
            'portals' => array_map(
                static fn (array $row): array => ['tenant_id' => $row[0], 'tenant' => $row[1], 'listings' => $row[2]],
                $rows,
            ),
        ]);

        $this->components->info("Bericht: {$path}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Konten liegen zentral, nicht je Portal — deshalb eine Abfrage fuer alle.
     * Mit `--tenant` wird auf die Konten der gewaehlten Portale eingeschraenkt.
     *
     * @param  Collection<int, Tenant>  $tenants
     */
    private function expireAccounts(BotQuarantine $quarantine, Carbon $cutoff, Collection $tenants, bool $dryRun): int
    {
        if (! Schema::hasColumn('users', 'suspected_bot_at')) {
            $this->components->error('Spalten der Quarantäne fehlen in users — migrate ausstehend.');

            return 0;
        }

        $query = User::query()
            ->whereNotNull('suspected_bot_at')
            ->whereNull('suspected_bot_cleared_at')
            ->where('suspected_bot_at', '<=', $cutoff);

        if ($this->isFiltered()) {
            $ids = $tenants->map(static fn (Tenant $tenant): int => (int) $tenant->getKey())->all();
            $query->whereHas('tenants', static fn ($relation) => $relation->whereIn('tenants.id', $ids));
        }

        $users = $query->orderBy('id')->get();

        if (! $dryRun) {
            $users->each(fn (User $user) => $quarantine->softDelete($user));
        }

        return $users->count();
    }

    private function expireListings(BotQuarantine $quarantine, Carbon $cutoff, bool $dryRun): int
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'suspected_bot_at')) {
            throw new RuntimeException('Spalten der Quarantäne fehlen — tenants:migrate ausstehend.');
        }

        $companies = Company::query()
            ->whereNotNull('suspected_bot_at')
            ->whereNull('suspected_bot_cleared_at')
            ->where('suspected_bot_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        if (! $dryRun) {
            $companies->each(fn (Company $company) => $quarantine->softDelete($company));
        }

        return $companies->count();
    }

    private function days(): ?int
    {
        $option = $this->option('days');

        if ($option === null || $option === '') {
            return BotQuarantine::quarantineDays();
        }

        if (! is_numeric($option) || (int) $option < 1) {
            $this->components->error('--days erwartet eine ganze Zahl ab 1.');

            return null;
        }

        return (int) $option;
    }

    private function isFiltered(): bool
    {
        return TenantSelection::filter((array) $this->option('tenant')) !== [];
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        return TenantSelection::resolve((array) $this->option('tenant'));
    }
}
