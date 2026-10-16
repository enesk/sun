<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Haelt die Portalauswahl der Werbe-Seiten im Admin-Panel (Ticket #10).
 *
 * Das Admin-Panel kennt keinen Portalkontext, die Werbe-Einstellungen liegen
 * aber je Portal (`ad_settings`, eine Zeile je Tenant). Die Auswahl steht
 * deshalb in der Session und wird geteilt: wer hier ein Portal waehlt, sieht es
 * auch im Werbeplatz-Editor (#7) wieder. Muster:
 * {@see \App\Turnstile\Support\TurnstileAdminPortal}.
 *
 * Fehlt eine Auswahl, gilt das erste Portal nach Name. Es gibt kein "alle
 * Portale": Freigabe, Publisher-ID und `ads.txt` gelten je Domain.
 */
final class AdsAdminPortal
{
    public const SESSION_KEY = 'ads.admin.tenant_id';

    /** Auswahlliste der Portale im Konfigurationspanel (#8). */
    public const NAMES_CACHE_KEY = 'ads:tenant-names';

    private const NAMES_CACHE_TTL = 300;

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

        $first = Tenant::query()->orderBy('name')->value('id');

        return $first === null ? null : (int) $first;
    }

    public static function current(): ?Tenant
    {
        $id = self::currentId();

        return $id === null ? null : Tenant::query()->find($id);
    }

    /**
     * Dieselbe Liste, aber gecacht — das Multi-Select der Portale im
     * Konfigurationspanel (#8) fragt sie bei jedem Tastendruck der Suche
     * erneut ab, und die Namen stehen in der zentralen Datenbank. Fuenf
     * Minuten: ein neu angelegtes Portal steht spaetestens dann drin, und
     * {@see forgetNames()} holt es sofort.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return Cache::remember(
            self::NAMES_CACHE_KEY,
            self::NAMES_CACHE_TTL,
            fn (): array => self::options(),
        );
    }

    public static function forgetNames(): void
    {
        Cache::forget(self::NAMES_CACHE_KEY);
    }

    /**
     * Name und Domain, damit zwei aehnlich benannte Portale unterscheidbar
     * bleiben — die Domain ist bei `ads.txt` ohnehin die eigentliche Einheit.
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
}
