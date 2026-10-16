<?php

declare(strict_types=1);

namespace App\Analytics;

/**
 * Einwilligung fuer das fremde Tracking-HTML aus der Verwaltung
 * (`config('app.tracking_scripts')`), serverseitig (Ticket #28).
 *
 * Der Block gab bisher ungefragt aus, was in der Verwaltung eingetragen ist —
 * ein Pixel, ein Tag-Manager-Behaelter oder ein Werbe-Schnipsel lief damit vor
 * jeder Entscheidung des Besuchers. Das Nonce aus
 * {@see \App\Services\Security\CspNonce} loest nur die CSP-Frage, nicht die
 * Einwilligungs-Frage.
 *
 * Kategorie
 * ---------
 * Was dort steht, weiss nur der Mensch, der es eintraegt: je nach Inhalt ist
 * es Statistik oder Marketing. Darum `config('app.tracking_scripts_consent')`
 * (Verwaltung → Analytics & Cookies, env `TRACKING_SCRIPTS_CONSENT`) mit der
 * strengeren Vorgabe `marketing` — ein unbekannter Schnipsel laeuft lieber zu
 * selten als zu frueh. Ein Wert, der keine bekannte Kategorie ist, faellt auf
 * `marketing` zurueck.
 *
 * Zustand
 * -------
 * Gelesen wird dasselbe Klartext-Cookie wie bei der Werbung
 * ({@see \App\Ads\Support\AdConsent}), nur je Kategorie; es steht in der
 * Ausnahmeliste von EncryptCookies (bootstrap/app.php, #21), ohne die liefert
 * `request()->cookie()` immer null.
 *
 *   granted  Entscheidung liegt vor: der Block wird direkt ausgegeben.
 *   pending  Keine Entscheidung: der Block liegt inert in einem
 *            <template data-consent-script="…"> und wird von
 *            resources/js/consent-scripts.js nachgezogen, wenn die
 *            Einwilligung im laufenden Besuch kommt.
 *   denied   Ausdruecklich abgelehnt: es wird gar nichts ausgegeben.
 */
final class TrackingScriptsConsent
{
    public const STATE_GRANTED = 'granted';

    public const STATE_PENDING = 'pending';

    public const STATE_DENIED = 'denied';

    /** Die Kategorien, die resources/js/consent-adapter.js kennt. */
    public const CATEGORIES = ['statistics', 'marketing'];

    public const DEFAULT_CATEGORY = 'marketing';

    /** @return value-of<self::CATEGORIES> */
    public static function category(): string
    {
        $category = (string) config('app.tracking_scripts_consent', self::DEFAULT_CATEGORY);

        return in_array($category, self::CATEGORIES, true) ? $category : self::DEFAULT_CATEGORY;
    }

    public static function cookieName(): string
    {
        return 'cookie_consent_'.self::category();
    }

    /** @return self::STATE_* */
    public static function state(): string
    {
        return match (request()->cookie(self::cookieName())) {
            '1' => self::STATE_GRANTED,
            '0' => self::STATE_DENIED,
            default => self::STATE_PENDING,
        };
    }

    public static function granted(): bool
    {
        return self::state() === self::STATE_GRANTED;
    }

    public static function denied(): bool
    {
        return self::state() === self::STATE_DENIED;
    }
}
