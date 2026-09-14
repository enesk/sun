<?php

declare(strict_types=1);

namespace App\Content\Concerns;

use App\Models\Tenant;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Zugang zum Content-Panel fuer App\Models\User.
 *
 * Seit dem Umbau auf den SaaSykit-Login gibt es keine eigene Benutzertabelle
 * und keine eigenen Rollen mehr: das Panel steht genau den Administratoren
 * offen (users.is_admin), und ein Administrator darf dort alles — Produktion,
 * Pruefung, Einstellungen, Kosten und jedes Portal.
 *
 * Die Methoden bleiben trotzdem einzeln stehen, weil die Oberflaechen sie
 * getrennt abfragen (Einstellungen, Kostenzahlen, Portalauswahl) und eine
 * feinere Abstufung damit spaeter an einer Stelle nachrüstbar ist.
 */
trait InteractsWithContentPanel
{
    public function canAccessContentPanel(): bool
    {
        return (bool) $this->is_admin && ! $this->is_blocked;
    }

    /**
     * Budget, Provider-Zugaenge, Prompts, Quellen-Konfiguration.
     */
    public function canManageContentSettings(): bool
    {
        return $this->canAccessContentPanel();
    }

    /**
     * Kostenzahlen der Leistungsansicht (#25).
     */
    public function canSeeContentCosts(): bool
    {
        return $this->canAccessContentPanel();
    }

    public function canAccessContentTenant(int $tenantId): bool
    {
        return $this->canAccessContentPanel();
    }

    /**
     * Die Portale, die dieser Account im Portalumschalter sieht.
     */
    public function accessibleContentTenants(): TenantCollection
    {
        if (! $this->canAccessContentPanel()) {
            return new TenantCollection;
        }

        return Tenant::query()->orderBy('name')->get();
    }
}
