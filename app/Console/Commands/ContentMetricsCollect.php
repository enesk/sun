<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Jobs\CollectArticleMetricsJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Taeglicher Metrik-Collector (#23).
 *
 * Reiht je Mandant einen CollectArticleMetricsJob ein. Der Job verdichtet die
 * Search-Console-Rohzeilen des juengsten Fensters (#9) zu Tageswerten je
 * Ratgeber, holt — falls freigeschaltet — die AdSense-Ertraege dazu und
 * markiert abrutschende Artikel mit needs_refresh.
 */
class ContentMetricsCollect extends Command
{
    protected $signature = 'content:metrics:collect
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--sync : Jobs sofort ausfuehren statt einzureihen}';

    protected $description = 'Sammelt Search-Console- und AdSense-Werte je veroeffentlichtem Ratgeber';

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
                ? CollectArticleMetricsJob::dispatchSync((int) $tenant->getKey())
                : CollectArticleMetricsJob::dispatch((int) $tenant->getKey());

            $this->line("[{$tenant->name}] Metrik-Collector ".($sync ? 'ausgefuehrt' : 'eingereiht').'.');
        }

        return self::SUCCESS;
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
