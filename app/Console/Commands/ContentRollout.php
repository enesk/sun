<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Services\SearchConsoleProperty;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Rollout-Schalter der Content-Pipeline (#26).
 *
 * Der Go-Live laeuft gestaffelt: Woche 1 drei Portale, Woche 2 alle. Dieser
 * Befehl ist der einzige vorgesehene Weg, ein Portal freizuschalten oder
 * wieder herauszunehmen — ohne Deploy, ohne Aenderung am Scheduler.
 *
 *   php artisan content:rollout                          Zustand aller Portale
 *   php artisan content:rollout --activate=7 --activate=sanitaer.test
 *   php artisan content:rollout --deactivate=7
 *   php artisan content:rollout --activate-all
 *   php artisan content:rollout --activate=7 --threshold=85
 */
class ContentRollout extends Command
{
    protected $signature = 'content:rollout
        {--activate=* : Portal freischalten (ID, UUID oder Domain)}
        {--deactivate=* : Portal herausnehmen (ID, UUID oder Domain)}
        {--activate-all : Alle Portale freischalten (Woche 2 des Rollouts)}
        {--deactivate-all : Alle Portale herausnehmen (Notbremse)}
        {--threshold= : Auto-Live-Schwelle der freigeschalteten Portale setzen (50-100)}';

    protected $description = 'Zeigt und setzt den Rollout-Schalter der Ratgeber-Pipeline je Portal';

    public function handle(TenantRollout $rollout, SearchConsoleProperty $searchConsole): int
    {
        $changed = 0;
        $threshold = $this->threshold();

        if ($threshold === false) {
            $this->error('--threshold erwartet eine Zahl zwischen 50 und 100.');

            return self::FAILURE;
        }

        if ($this->option('deactivate-all')) {
            /** @var Tenant $tenant */
            foreach (Tenant::all() as $tenant) {
                $changed += $this->apply($rollout, $tenant, false) ? 1 : 0;
            }
        }

        if ($this->option('activate-all')) {
            /** @var Tenant $tenant */
            foreach (Tenant::all() as $tenant) {
                $changed += $this->apply($rollout, $tenant, true) ? 1 : 0;
            }
        }

        foreach ((array) $this->option('deactivate') as $needle) {
            $tenant = $this->resolve((string) $needle);

            if ($tenant === null) {
                $this->error("Portal nicht gefunden: {$needle}");

                return self::FAILURE;
            }

            $changed += $this->apply($rollout, $tenant, false) ? 1 : 0;
        }

        foreach ((array) $this->option('activate') as $needle) {
            $tenant = $this->resolve((string) $needle);

            if ($tenant === null) {
                $this->error("Portal nicht gefunden: {$needle}");

                return self::FAILURE;
            }

            $changed += $this->apply($rollout, $tenant, true) ? 1 : 0;
        }

        if ($threshold !== null) {
            /** @var Tenant $tenant */
            foreach ($rollout->activeTenants() as $tenant) {
                $rollout->setAutoPublishThreshold($tenant, $threshold);
                $this->line(sprintf('[%s] Auto-Live-Schwelle auf %d gesetzt.', (string) $tenant->name, $threshold));
                $changed++;
            }
        }

        $status = $rollout->status();

        // Zustand der Search-Console-Property je Portal (#116): eine gepflegte
        // Property allein sagt nichts darueber, ob das Dienstkonto sie lesen
        // darf. Die Spalte zeigt denselben Befund wie das Content-Panel.
        $searchConsoleStates = [];

        foreach ($searchConsole->overview() as $row) {
            $searchConsoleStates[(int) $row['tenant']->getKey()] = (string) $row['state']['label'];
        }

        $this->table(
            ['ID', 'Portal', 'Domain', 'Aktiv', 'Artikel/Tag', 'Auto-Live ab', 'YMYL', 'GSC-Property', 'Search Console'],
            array_map(static function (array $row) use ($searchConsoleStates): array {
                /** @var Tenant $tenant */
                $tenant = $row['tenant'];

                return [
                    (int) $tenant->getKey(),
                    (string) $tenant->name,
                    (string) $tenant->domain,
                    $row['active'] ? 'ja' : '—',
                    $row['articles_per_day'] ?: '—',
                    $row['threshold'] ?: '—',
                    $row['ymyl'] ? 'ja' : '—',
                    $row['gsc_property'] ?? '—',
                    $searchConsoleStates[(int) $tenant->getKey()] ?? '—',
                ];
            }, $status),
        );

        $active = count(array_filter($status, static fn (array $row): bool => $row['active']));

        $this->info(sprintf('%d von %d Portalen freigeschaltet, %d Aenderung(en).', $active, count($status), $changed));

        return self::SUCCESS;
    }

    /**
     * @return int|null|false false = unbrauchbare Eingabe
     */
    private function threshold(): int|null|false
    {
        $value = $this->option('threshold');

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 50 || (int) $value > 100) {
            return false;
        }

        return (int) $value;
    }

    private function apply(TenantRollout $rollout, Tenant $tenant, bool $active): bool
    {
        $ok = $active ? $rollout->activate($tenant) : $rollout->deactivate($tenant);

        if (! $ok) {
            $this->error(sprintf('[%s] Schalter nicht schreibbar — siehe Log.', (string) $tenant->name));

            return false;
        }

        $this->line(sprintf('[%s] %s', (string) $tenant->name, $active ? 'freigeschaltet' : 'herausgenommen'));

        return true;
    }

    private function resolve(string $needle): ?Tenant
    {
        if (is_numeric($needle)) {
            return Tenant::query()->find((int) $needle);
        }

        return Tenant::query()
            ->where('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();
    }
}
