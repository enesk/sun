<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\Turnstile\Models\TurnstileVerification;

/**
 * Herkunft der laufenden Anfrage fuer users und companies (#8).
 *
 * docs/turnstile.md §1.4: `users` hat bisher weder IP noch User-Agent
 * mitgeschrieben, die Erkennungsregeln R9 und R11 aus §7 waren deshalb nicht
 * messbar. Diese Klasse liefert die beiden Werte in der Form, in der sie in
 * die Spalten gehoeren.
 *
 * Die IP ist IMMER der HMAC-SHA256 aus TurnstileVerification::hashIp() —
 * derselbe Hash wie in turnstile_verifications, damit sich ein Konto mit
 * seinen Pruefversuchen verbinden laesst, ohne eine IP zu speichern. Der
 * User-Agent wird im Klartext gespeichert (er benennt keine Person) und auf
 * die Spaltenbreite von 255 Zeichen gekuerzt.
 *
 * Ausserhalb einer HTTP-Anfrage (Artisan, Queue, Seeder) sind beide Werte
 * null; die Spalten sind nullable.
 */
final class RequestOrigin
{
    /**
     * @return array{registration_ip_hash: string|null, registration_user_agent: string|null}
     */
    public static function forUser(): array
    {
        return [
            'registration_ip_hash' => self::ipHash(),
            'registration_user_agent' => self::userAgent(),
        ];
    }

    /**
     * @return array{created_ip_hash: string|null, created_user_agent: string|null}
     */
    public static function forCompany(): array
    {
        return [
            'created_ip_hash' => self::ipHash(),
            'created_user_agent' => self::userAgent(),
        ];
    }

    public static function enabled(): bool
    {
        return AntiSpamConfig::bool('origin_logging.enabled', true);
    }

    public static function ipHash(): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        return TurnstileVerification::hashIp(request()?->ip());
    }

    public static function userAgent(): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        return TurnstileVerification::trimUserAgent(request()?->userAgent());
    }
}
