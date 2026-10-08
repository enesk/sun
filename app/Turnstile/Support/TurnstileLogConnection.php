<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Richtet die Verbindung `tenant` auf ein Portal, ohne die Standardverbindung
 * zu wechseln (#9).
 *
 * Das Admin-Panel laeuft auf der zentralen Domain und damit ohne Tenancy. Das
 * Verifikations-Log liegt aber je Portal in der Tenant-DB
 * (App\Turnstile\Models\TurnstileVerification mit dem Trait TenantConnection,
 * also hart auf der Verbindung `tenant`). `tenancy()->initialize()` waere hier
 * das falsche Mittel: es wuerde `database.default` umstellen und damit das
 * ganze Admin-Panel samt Benutzern, Cache und Sitzung auf die Portal-Datenbank
 * ziehen.
 *
 * Darum nur die eine Verbindung registrieren — Zugangsdaten kommen aus
 * `$tenant->database()->connection()`, derselben Quelle, aus der auch
 * stancl/tenancy beim Initialisieren schoepft. Alles andere im Panel bleibt auf
 * `central`.
 *
 * Eine Filament-Tabelle fuehrt ihre Abfrage erst beim Rendern aus, lange nach
 * dem Bauen des Builders. Deshalb ist nicht `Tenant::run()` das Mittel, sondern
 * dieses Umhaengen: die Verbindung muss noch stehen, wenn paginate() laeuft.
 */
final class TurnstileLogConnection
{
    public const NAME = 'tenant';

    private static ?int $pointedAt = null;

    /**
     * @throws LogicException wenn gerade ein echter Portalkontext laeuft — dann
     *                        wuerde das Umhaengen die laufende Anfrage treffen
     */
    public static function point(Tenant $tenant): void
    {
        $tenantId = (int) $tenant->getKey();

        if (function_exists('tenancy') && tenancy()->initialized) {
            if ((int) tenant()?->getKey() === $tenantId) {
                return;
            }

            throw new LogicException(
                'Turnstile: die Verbindung "tenant" kann nicht umgehaengt werden, '
                .'solange ein anderes Portal initialisiert ist.'
            );
        }

        // Die Konfiguration mitpruefen und nicht nur den Merker: laeuft im
        // selben Prozess ein Tenant::run() an, raeumt dessen tenancy()->end()
        // die Verbindung `tenant` wieder ab (#10). Der Merker allein wuerde
        // dann "steht schon" melden und die naechste Abfrage lief ins Leere.
        if (self::$pointedAt === $tenantId && config('database.connections.'.self::NAME) !== null) {
            return;
        }

        config(['database.connections.'.self::NAME => $tenant->database()->connection()]);
        DB::purge(self::NAME);

        self::$pointedAt = $tenantId;
    }

    /** Nach der Auswertung wieder abraeumen, damit keine fremde DB offen bleibt. */
    public static function forget(): void
    {
        if (self::$pointedAt === null) {
            return;
        }

        self::$pointedAt = null;

        try {
            DB::purge(self::NAME);
        } catch (Throwable) {
            // Eine nie geoeffnete Verbindung muss nicht geschlossen werden.
        }

        config(['database.connections.'.self::NAME => null]);
    }

    /** Auf welches Portal zeigt die Verbindung gerade? */
    public static function pointedAt(): ?int
    {
        return self::$pointedAt;
    }
}
