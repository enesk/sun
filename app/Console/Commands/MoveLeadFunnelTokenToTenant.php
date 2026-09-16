<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Einmaliger Nachzug zu #24: haengt den bisherigen Installations-Token
 * LEADS_ELEKTRIKER_FUNNEL_TOKEN an den Elektriker-Tenant.
 *
 * Der Tenant wird nie geraten, er kommt immer ueber --tenant. Der Token kommt
 * immer ueber --token: die Config liest die alte Variable nicht mehr, und bei
 * gecachter Config liefert env() ohnehin null.
 *
 * Vorgabe ist der Trockenlauf, geschrieben wird nur mit --apply. Ein bereits
 * gepflegter, abweichender Token bleibt stehen, ausser mit --force.
 */
class MoveLeadFunnelTokenToTenant extends Command
{
    protected $signature = 'tenants:move-lead-funnel-token
        {--tenant= : Elektriker-Tenant (ID, UUID oder Domain)}
        {--token= : Funnel-Token, Wert von LEADS_ELEKTRIKER_FUNNEL_TOKEN aus der .env}
        {--apply : Token tatsaechlich schreiben}
        {--force : Einen abweichenden, bereits gepflegten Token ueberschreiben}';

    protected $description = 'Haengt den bisherigen LEADS_ELEKTRIKER_FUNNEL_TOKEN einmalig an den Elektriker-Tenant (#24)';

    public function handle(): int
    {
        $needle = trim((string) $this->option('tenant'));

        if ($needle === '') {
            $this->error('--tenant fehlt (ID, UUID oder Domain des Elektriker-Portals).');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->where('id', $needle)
            ->orWhere('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();

        if ($tenant === null) {
            $this->error("Kein Tenant zu \"{$needle}\" gefunden.");

            return self::FAILURE;
        }

        $token = trim((string) $this->option('token'));

        if ($token === '') {
            $this->error('--token fehlt (Wert von LEADS_ELEKTRIKER_FUNNEL_TOKEN aus der .env).');

            return self::FAILURE;
        }

        $current = $tenant->leadFunnelToken();
        $label = "{$tenant->name} (ID {$tenant->id}, {$tenant->domain})";

        if ($current === $token) {
            $this->info("{$label}: Token ist bereits gesetzt, nichts zu tun.");

            return self::SUCCESS;
        }

        if ($current !== null && ! $this->option('force')) {
            $this->warn("{$label}: abweichender Token bereits gepflegt, bleibt stehen. Ersetzen nur mit --force.");

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->comment("Trockenlauf: {$label} bekäme den Token {$token}. Zum Schreiben: --apply");

            return self::SUCCESS;
        }

        $tenant->setAttribute(Tenant::LEAD_FUNNEL_TOKEN, $token);
        $tenant->save();

        $this->info("{$label}: Token gesetzt. LEADS_ELEKTRIKER_FUNNEL_TOKEN kann jetzt aus der .env entfernt werden.");

        return self::SUCCESS;
    }
}
