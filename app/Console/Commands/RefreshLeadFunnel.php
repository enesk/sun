<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Dto\Leads\FunnelDefinition;
use App\Models\Tenant;
use App\Services\Leads\FunnelDefinitionClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Leert den Funnel-Cache je Portal und laedt neu (#25), damit eine Aenderung
 * im Leadsystem sofort im Anfrage-Dialog steht.
 *
 * Laeuft je Portal in dessen Kontext ($tenant->run()): der file-Store liegt
 * je Tenant in einem eigenen Verzeichnis, aus dem zentralen Kontext waere der
 * Eintrag nicht erreichbar.
 */
class RefreshLeadFunnel extends Command
{
    protected $signature = 'leads:funnel:refresh
        {--tenant=* : Portale (ID, UUID oder Domain); ohne Angabe alle mit Funnel-Token}
        {--token= : Funnel-Token statt des am Portal gepflegten (nur zusammen mit --tenant)}';

    protected $description = 'Leert den Funnel-Cache der Portale und laedt die Funnels neu aus dem Leadsystem';

    public function handle(FunnelDefinitionClient $client): int
    {
        $token = trim((string) $this->option('token'));
        $filter = array_values(array_filter((array) $this->option('tenant'), static fn ($v): bool => (string) $v !== ''));

        if ($token !== '' && $filter === []) {
            $this->error('--token geht nur zusammen mit --tenant.');

            return self::FAILURE;
        }

        $tenants = $this->tenants($filter);

        if ($tenants->isEmpty()) {
            $this->warn('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            $portalToken = $token !== '' ? $token : $this->tenantToken($tenant);

            if ($portalToken === null) {
                $rows[] = [$tenant->id, $tenant->domain, '—', 'kein Funnel-Token'];

                continue;
            }

            /** @var FunnelDefinition|null $definition */
            $definition = $tenant->run(fn () => $client->refresh($portalToken));

            $rows[] = [
                $tenant->id,
                $tenant->domain,
                $definition?->version ?? '—',
                $this->describe($definition),
            ];
        }

        $this->table(['ID', 'Domain', 'Version', 'Ergebnis'], $rows);

        return self::SUCCESS;
    }

    private function describe(?FunnelDefinition $definition): string
    {
        if ($definition === null) {
            return 'kein Dialog (nicht veroeffentlicht oder nicht erreichbar, siehe Log)';
        }

        $steps = count($definition->steps);
        $age = $definition->fetchedAt->diffForHumans();

        return "{$steps} Schritte, Stand {$definition->fetchedAt->format('d.m.Y H:i')} ({$age})";
    }

    private function tenantToken(Tenant $tenant): ?string
    {
        // Tenant::leadFunnelToken() kommt mit #24.
        return method_exists($tenant, 'leadFunnelToken') ? $tenant->leadFunnelToken() : null;
    }

    /**
     * @param  list<string>  $filter
     * @return Collection<int, Tenant>
     */
    private function tenants(array $filter): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($filter === []) {
            return $tenants->filter(fn (Tenant $tenant): bool => $this->tenantToken($tenant) !== null)->values();
        }

        return $tenants->filter(static function (Tenant $tenant) use ($filter): bool {
            $domain = mb_strtolower((string) $tenant->domain);

            foreach ($filter as $needle) {
                $needle = mb_strtolower(trim((string) $needle));

                if ((string) $tenant->id === $needle || (string) $tenant->uuid === $needle || $domain === $needle) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
