<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Loest eine Referenz (tenant_id, company_id) aus der Central-DB in das
 * Firmenmodell der Tenant-DB auf (SUN-RQ, Ticket #3).
 *
 * Einzige Stelle im Anfragen-Modul, die den Tenant-Kontext wechselt. Der
 * Aufrufer — Panel (#7), Auto-Regel (#9), Loeschpipeline (#8) — sieht davon
 * nichts und bekommt entweder ein Company-Objekt oder null.
 *
 * `Tenant::run()` ist dabei bewusst gegenueber `tenancy()->initialize()`
 * bevorzugt: es stellt den vorher aktiven Tenant hinterher wieder her. Ein
 * Aufruf aus einer Portal-Anfrage heraus (Self-Service-Formular, #4) laesst
 * den eigenen Kontext damit unberuehrt.
 *
 * Die Tenant-Objekte werden je Prozess gemerkt, nicht die Betriebe: eine
 * Inbox-Liste mit 50 Zeilen aus 13 Portalen kostet so 13 Abfragen in der
 * Central-DB statt 50, waehrend die Profildaten immer frisch sind.
 */
class TenantCompanyResolver
{
    /** @var array<int, Tenant|null> */
    private array $tenants = [];

    /**
     * Betrieb im Kontext seines Portals. null, wenn Portal oder Betrieb
     * fehlen oder der Eintrag dort geloescht wurde.
     */
    public function resolve(?int $tenantId, ?int $companyId, bool $withTrashed = false): ?Company
    {
        if ($tenantId === null || $companyId === null) {
            return null;
        }

        return $this->runFor($tenantId, function () use ($companyId, $withTrashed): ?Company {
            $query = Company::query();

            if ($withTrashed) {
                $query->withTrashed();
            }

            return $query->find($companyId);
        });
    }

    /**
     * Beliebige Arbeit im Kontext eines Portals ausfuehren — der Weg fuer
     * Ein-Klick-Aktionen (#7) und die Loeschpipeline (#8).
     *
     * Ist das Portal unbekannt oder seine Datenbank nicht erreichbar, gibt
     * der Aufruf null zurueck und schreibt eine Logzeile; die Inbox soll
     * nicht an einem einzelnen kaputten Portal haengenbleiben.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn|null
     */
    public function runFor(int $tenantId, callable $callback): mixed
    {
        $tenant = $this->tenant($tenantId);

        if ($tenant === null) {
            return null;
        }

        try {
            return $tenant->run($callback);
        } catch (Throwable $e) {
            Log::warning('profile-requests: Tenant-Kontext nicht nutzbar', [
                'tenant_id' => $tenantId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function tenant(int $tenantId): ?Tenant
    {
        if (! array_key_exists($tenantId, $this->tenants)) {
            $this->tenants[$tenantId] = Tenant::find($tenantId);
        }

        return $this->tenants[$tenantId];
    }

    /** Nach einem Portalwechsel in einem langen Lauf (#5, #9). */
    public function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->tenants = [];

            return;
        }

        unset($this->tenants[$tenantId]);
    }
}
