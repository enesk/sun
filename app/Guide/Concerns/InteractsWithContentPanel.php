<?php

declare(strict_types=1);

namespace App\Guide\Concerns;

use App\Guide\Enums\GuideRole;
use App\Models\Tenant;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Zugang zum Content-Panel fuer App\Models\User.
 *
 * Angemeldet wird ueber den SaaSykit-Login (Guard 'web'). Wer hinein darf und
 * was er dort sieht, entscheidet die Rolle aus users.guide_role
 * (App\Guide\Enums\GuideRole, #14):
 *
 * - owner:  alles, inklusive Einstellungen und Kosten.
 * - editor: Themen und Pruefung, keine Einstellungen, keine Kostenzahlen.
 *
 * Administratoren ohne eigene Rolle gelten als owner, damit niemand
 * ausgesperrt wird, der heute hineinkommt. Gesperrte Konten kommen nie hinein.
 */
trait InteractsWithContentPanel
{
    public function contentRole(): ?GuideRole
    {
        if ($this->is_blocked) {
            return null;
        }

        $role = GuideRole::tryFrom((string) $this->guide_role);

        if ($role !== null) {
            return $role;
        }

        return $this->is_admin ? GuideRole::OWNER : null;
    }

    public function canAccessContentPanel(): bool
    {
        return $this->contentRole() !== null;
    }

    /**
     * Budget, Tageslauf, Prompts, Quellen-Konfiguration.
     */
    public function canManageContentSettings(): bool
    {
        return (bool) $this->contentRole()?->canManageSettings();
    }

    /**
     * Kostenzahlen (Uebersicht, Verlauf, Leistungsansicht).
     */
    public function canSeeContentCosts(): bool
    {
        return (bool) $this->contentRole()?->canSeeCosts();
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
