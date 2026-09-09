<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Jobs\RefreshArticleJob;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Taeglicher Refresh-Lauf (#24).
 *
 * Reiht je Mandant einen RefreshArticleJob ein. Der Job sucht sich seine
 * Kandidaten selbst (RefreshSelector) und haelt das Tageslimit von drei
 * Aktualisierungen je Mandant ein — dieses Kommando zaehlt nichts nach.
 *
 * Mit --draft laesst sich eine einzelne Fassung aktualisieren; die
 * Ausschluesse (Mindestalter, Sperrfrist, laufende Aktualisierung) gelten
 * auch dort, damit Hand- und Tageslauf dasselbe tun.
 */
class ContentRefreshRun extends Command
{
    protected $signature = 'content:refresh:run
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--draft= : Nur diese Entwurfs-ID aktualisieren (setzt --tenant voraus)}
        {--sync : Jobs sofort ausfuehren statt einzureihen}';

    protected $description = 'Aktualisiert abrutschende oder faktisch veraltete Ratgeber (#24)';

    public function handle(): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $draftId = $this->option('draft') !== null ? (int) $this->option('draft') : null;

        if ($draftId !== null && $this->option('tenant') === null) {
            $this->error('--draft braucht --tenant: Entwurfs-IDs sind je Mandantendatenbank vergeben.');

            return self::FAILURE;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            // Vor dem Go-Live ist kein Portal freigeschaltet (#26). Das ist
            // kein Fehler, sonst meldete der Scheduler jeden Tag Alarm.
            if ($this->option('tenant') === null) {
                $this->warn('Kein Portal ist für die Content-Pipeline freigeschaltet (content:rollout).');

                return self::SUCCESS;
            }

            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $sync
                ? RefreshArticleJob::dispatchSync((int) $tenant->getKey(), $draftId)
                : RefreshArticleJob::dispatch((int) $tenant->getKey(), $draftId);

            $this->line("[{$tenant->name}] Refresh-Lauf ".($sync ? 'ausgefuehrt' : 'eingereiht').'.');
        }

        return self::SUCCESS;
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            // Ohne --tenant arbeitet der Befehl nur auf freigeschalteten
            // Portalen (#26). Ein einzeln benanntes Portal laeuft weiter,
            // damit sich ein Portal vor dem Go-Live pruefen laesst.
            return app(TenantRollout::class)->activeTenants();
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
