<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Jobs\LearningJob;
use App\Content\Models\TenantContentSetting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Woechentliche Lernschleife (#23).
 *
 * Reiht je Mandant einen LearningJob ein und zeigt mit --show die aktuelle
 * Rangliste aus tenant_content_settings.cluster_performance_json — die
 * Abnahme braucht sie ohne Umweg ueber die Oberflaeche.
 */
class ContentLearningRun extends Command
{
    protected $signature = 'content:learning:run
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--sync : Jobs sofort ausfuehren statt einzureihen}
        {--show : Rangliste nach dem Lauf ausgeben}';

    protected $description = 'Aggregiert die Performance je Cluster, Region und Branche und schreibt sie in die Einstellungen';

    public function handle(): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $sync
                ? LearningJob::dispatchSync((int) $tenant->getKey())
                : LearningJob::dispatch((int) $tenant->getKey());

            $this->line("[{$tenant->name}] Lernschleife ".($sync ? 'ausgefuehrt' : 'eingereiht').'.');

            if ($sync && $this->option('show')) {
                $this->show($tenant);
            }
        }

        return self::SUCCESS;
    }

    private function show(Tenant $tenant): void
    {
        $performance = $tenant->run(
            static fn (): array => (array) (TenantContentSetting::current()->cluster_performance_json ?? []),
        );

        if ($performance === []) {
            $this->warn('  Keine Auswertung vorhanden.');

            return;
        }

        $this->line(sprintf(
            '  Fenster: %d Tage, Artikel: %d, Klicks je Artikel: %s',
            (int) ($performance['window_days'] ?? 0),
            (int) ($performance['articles'] ?? 0),
            (string) ($performance['baseline']['clicks_per_article'] ?? '0'),
        ));

        foreach (['clusters' => 'Cluster', 'regions' => 'Regionen'] as $key => $label) {
            $rows = array_slice((array) ($performance[$key] ?? []), 0, 10);

            if ($rows === []) {
                continue;
            }

            $this->line("  {$label}:");

            foreach ($rows as $row) {
                $this->line(sprintf(
                    '   %2d. %-40s Artikel %3d, Klicks/Artikel %6s, Score %s',
                    (int) ($row['rank'] ?? 0),
                    (string) ($row['name'] ?? ($row['scope'] ?? '').' '.($row['code'] ?? '')),
                    (int) ($row['articles'] ?? 0),
                    (string) ($row['clicks_per_article'] ?? '0'),
                    $row['score'] === null ? '-' : (string) $row['score'],
                ));
            }
        }
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            return Tenant::all();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
