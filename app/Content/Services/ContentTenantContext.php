<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Haelt die Portalauswahl des Content-Panels.
 *
 * Das Panel selbst laeuft im Central-Kontext; einzelne Seiten arbeiten aber
 * auf Tenant-Tabellen (article_drafts, topic_candidates, ...). Die Auswahl
 * steht in der Session und wird pro Anfrage von InitializeContentTenant in
 * einen echten Tenancy-Kontext uebersetzt.
 *
 * `null` bedeutet "Alle Portale": kein Tenancy-Kontext, netzwerkweite Sicht
 * ueber die Central-Tabellen bzw. ueber Tenant::run() je Portal.
 */
class ContentTenantContext
{
    public const SESSION_KEY = 'content.tenant_id';

    public function selectedId(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    public function selected(): ?Tenant
    {
        $id = $this->selectedId();

        if ($id === null) {
            return null;
        }

        return Tenant::query()->find($id);
    }

    public function isNetworkWide(): bool
    {
        return $this->selectedId() === null;
    }

    /**
     * Setzt die Auswahl. `null` schaltet auf "Alle Portale".
     * Ein Portal ausserhalb der Zuordnung des Accounts wird stillschweigend
     * auf "Alle Portale" zurueckgesetzt statt einen Fehler zu werfen.
     */
    public function select(?int $tenantId): void
    {
        if ($tenantId === null || ! $this->isAllowed($tenantId)) {
            Session::forget(self::SESSION_KEY);
            $this->apply();

            return;
        }

        Session::put(self::SESSION_KEY, $tenantId);
        $this->apply();
    }

    /**
     * Uebersetzt die Auswahl in den Tenancy-Kontext der laufenden Anfrage.
     */
    public function apply(): void
    {
        $tenant = $this->selected();

        if ($tenant === null || ! $this->isAllowed((int) $tenant->getKey())) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            return;
        }

        tenancy()->initialize($tenant);
    }

    public function isAllowed(int $tenantId): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->canAccessContentTenant($tenantId)
            && Tenant::query()->whereKey($tenantId)->exists();
    }

    public function available(): TenantCollection
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return new TenantCollection;
        }

        return $user->accessibleContentTenants();
    }

    private function user(): ?User
    {
        $user = Auth::guard(config('content.panel.guard', 'web'))->user();

        return $user instanceof User && $user->canAccessContentPanel() ? $user : null;
    }
}
