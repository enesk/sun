<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Session;

/**
 * Haelt die Portalauswahl der Turnstile-Seiten im Admin-Panel (#9).
 *
 * Das Admin-Panel kennt keinen Portalkontext — die Einstellungen liegen
 * zentral, das Verifikations-Log je Portal. Beide Seiten brauchen deshalb genau
 * eine Auswahl, und zwar dieselbe: wer auf der Einstellungsseite ein Portal
 * waehlt, sieht in den Sicherheitspruefungen dasselbe Portal.
 *
 * Fehlt eine Auswahl, gilt das erste Portal nach Name. Es gibt bewusst kein
 * "alle Portale": das Log liegt je Portal in einer eigenen Datenbank, eine
 * gemeinsame Tabelle waere nur zusammenkopiert. Netzweit summiert allein das
 * Kennzahlen-Widget ({@see TurnstileStats}).
 */
final class TurnstileAdminPortal
{
    public const SESSION_KEY = 'turnstile.admin.tenant_id';

    public static function select(?int $tenantId): void
    {
        if ($tenantId === null || ! Tenant::query()->whereKey($tenantId)->exists()) {
            Session::forget(self::SESSION_KEY);

            return;
        }

        Session::put(self::SESSION_KEY, $tenantId);
    }

    public static function currentId(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        if ($id !== null && Tenant::query()->whereKey((int) $id)->exists()) {
            return (int) $id;
        }

        return self::firstId();
    }

    public static function current(): ?Tenant
    {
        $id = self::currentId();

        return $id === null ? null : Tenant::query()->find($id);
    }

    /**
     * Auswahlliste: Name und Domain, damit zwei aehnlich benannte Portale
     * unterscheidbar bleiben.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'domain'])
            ->mapWithKeys(fn (Tenant $tenant): array => [
                (int) $tenant->getKey() => trim((string) $tenant->name).($tenant->domain !== null ? " ({$tenant->domain})" : ''),
            ])
            ->all();
    }

    private static function firstId(): ?int
    {
        $id = Tenant::query()->orderBy('name')->value('id');

        return $id === null ? null : (int) $id;
    }
}
