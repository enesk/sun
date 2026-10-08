<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

/**
 * Codes, die in turnstile_verifications.error_codes_json landen (#8).
 *
 * Teil des Datenbestands und der Auswertung in #9/#12 — nie umbenennen, nur
 * ergaenzen. Sie stehen neben den Cloudflare-Codes aus
 * App\Turnstile\Dto\VerificationResult in derselben Spalte; die Praefixfreiheit
 * ist Absicht: `honeypot` und `too_fast` sind die beiden Codes, die das Ticket
 * namentlich verlangt.
 */
final class SpamCode
{
    /** Das unsichtbare Feld war gefuellt. */
    public const HONEYPOT = 'honeypot';

    /** Abgeschickt schneller als antispam.timing.min_seconds. */
    public const TOO_FAST = 'too_fast';

    /** Zeitstempel fehlte oder war nicht entschluesselbar (Feld entfernt). */
    public const NO_TIMESTAMP = 'no_timestamp';

    /** Mail-Domain steht auf der Wegwerf-Sperrliste. */
    public const DISPOSABLE_EMAIL = 'disposable_email';

    /** Mail-Domain hat keinen MX-Eintrag (nur bei disposable.mx_check). */
    public const NO_MX_RECORD = 'no_mx_record';

    /** Rate-Limit ueberschritten; der Teil nach dem Doppelpunkt ist das Limit. */
    public const RATE_LIMIT = 'rate_limited';

    private function __construct() {}
}
