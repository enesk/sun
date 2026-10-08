<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Verschluesselter Zeitstempel fuer die Mindest-Ausfuellzeit (#8).
 *
 * Beim Rendern des Formulars legt {@see self::issue()} den Unix-Zeitstempel
 * verschluesselt in ein Hidden-Feld, beim Abschicken liest
 * {@see self::secondsSince()} ihn zurueck. Verschluesselt, nicht nur signiert,
 * weil Crypt beides leistet und der Wert dann auch nicht lesbar ist — wichtig
 * ist die Unversehrtheit: ein Bot soll sich keine 10 Sekunden eintragen
 * koennen.
 *
 * Das Feld heisst immer gleich. Anders als beim Honeypot bringt ein
 * Zufallsname hier nichts: der Wert ist ohne App-Key nicht faelschbar.
 */
final class TimingToken
{
    public const FIELD = 'antispam_t';

    public static function issue(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /**
     * Sekunden seit dem Rendern, oder null wenn der Token fehlt, nicht
     * entschluesselbar ist oder in der Zukunft liegt (manipuliert).
     */
    public static function secondsSince(mixed $token): ?int
    {
        if (! is_scalar($token) || trim((string) $token) === '') {
            return null;
        }

        try {
            $issuedAt = (int) Crypt::decryptString(trim((string) $token));
        } catch (DecryptException) {
            return null;
        }

        if ($issuedAt <= 0) {
            return null;
        }

        $seconds = now()->timestamp - $issuedAt;

        return $seconds < 0 ? null : $seconds;
    }

    /**
     * Zu schnell abgeschickt? Ein abgelaufener Token (Tab stand eine Nacht
     * offen) gilt NICHT als Treffer — sonst sperrt die Regel Menschen aus.
     * Ein fehlender Token zaehlt nur, wenn `timing.require_token` gilt; dann
     * hat jemand das Feld entfernt.
     */
    public static function tooFast(mixed $token): bool
    {
        $seconds = self::secondsSince($token);

        if ($seconds === null) {
            return AntiSpamConfig::bool('timing.require_token', true);
        }

        if ($seconds > AntiSpamConfig::int('timing.max_seconds', 86400)) {
            return false;
        }

        return $seconds < AntiSpamConfig::int('timing.min_seconds', 3);
    }

    /** Code fuer das Log: fehlender Token ist etwas anderes als zu schnell. */
    public static function code(mixed $token): string
    {
        return self::secondsSince($token) === null ? SpamCode::NO_TIMESTAMP : SpamCode::TOO_FAST;
    }
}
