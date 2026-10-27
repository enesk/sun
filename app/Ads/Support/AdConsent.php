<?php

declare(strict_types=1);

namespace App\Ads\Support;

/**
 * Einwilligung in Werbe-Cookies, serverseitig (SUN-AD-009, Ticket #9).
 *
 * Eine Stelle fuer die drei Fragen, die Komponente und Provider stellen:
 * liegt eine Einwilligung vor, ist sie ausdruecklich verweigert, und was
 * passiert dann mit dem reservierten Platz. Namen und Politik stehen in
 * `config('ad-slots.consent')`, nicht hier.
 *
 * Gelesen wird ausschliesslich `request()->cookie()`. Das Cookie schreibt der
 * Einwilligungs-Hinweis im Browser (`document.cookie`), ist also Klartext und
 * steht darum in der Ausnahmeliste von EncryptCookies (bootstrap/app.php,
 * #21). Einen Rueckfall auf `$_COOKIE` gibt es bewusst nicht: zwei Lesewege
 * fuer denselben Wert verdecken genau den Fehler, den die Ausnahme behebt
 * (tests/Feature/Ads/ConsentCookieTest.php).
 *
 * Drei Zustaende, nicht zwei — der Unterschied entscheidet ueber das Layout:
 *
 *   granted  Einwilligung liegt vor: es wird ausgespielt.
 *   pending  Keine Entscheidung. Der Platz wird reserviert, weil die
 *            Einwilligung noch im selben Besuch kommen kann und der
 *            Container dann nicht wachsen darf (CLS, Akzeptanz 2).
 *   denied   Ausdruecklich abgelehnt. Jetzt gilt `on_denied`.
 */
final class AdConsent
{
    public const STATE_GRANTED = 'granted';

    public const STATE_PENDING = 'pending';

    public const STATE_DENIED = 'denied';

    /** Was mit dem reservierten Platz passiert, wenn abgelehnt wurde. */
    public const ON_DENIED_COLLAPSE = 'collapse';

    public const ON_DENIED_RESERVE = 'reserve';

    /**
     * Trifft der Server ueberhaupt eine Einwilligungsentscheidung?
     *
     * `false` (Vorgabe seit 27.10.2026, Entscheidung Enes): **nein**. Die
     * Einwilligung holt allein Googles zertifiziertes CMP ("Datenschutz &
     * Messaging"), das mit dem AdSense-Tag geladen wird. Es entscheidet
     * selbst, ob und welche Anzeige es setzt, und setzt Cookies erst nach
     * Zustimmung — das ist Googles vorgesehener Weg.
     *
     * Der Server haelt den Platz dann nicht mehr zurueck: das Fragment geht
     * immer raus, Google liefert personalisiert, eingeschraenkt oder gar
     * nicht. Vorher lagen zwei Abfragen hintereinander (unsere und Googles),
     * und unsere war die Bremse — Googles CMP meldet 88 % Zustimmung, bei den
     * Portalen kamen rund 50 % an.
     *
     * `true` stellt das alte Verhalten wieder her, ohne Deploy: ein Wert in
     * der .env genuegt. Gebraucht wird das auf Portalen **ohne** AdSense,
     * denn dort laedt kein Google-Tag und erscheint kein CMP.
     */
    public static function serverGate(): bool
    {
        return (bool) config('ad-slots.consent.server_gate', false);
    }

    public static function cookieName(): string
    {
        return (string) config('ad-slots.consent.cookie', 'cookie_consent_marketing');
    }

    /** Ereignis, mit dem resources/js/consent-adapter.js die Zustimmung meldet. */
    public static function eventName(): string
    {
        return (string) config('ad-slots.consent.event', 'consent:marketing');
    }

    /** @return self::STATE_* */
    public static function state(): string
    {
        // Ohne Server-Gate faellt die Entscheidung bei Google, nicht hier.
        // `granted` heisst an dieser Stelle deshalb nicht "der Besucher hat
        // zugestimmt", sondern "der Server haelt nichts zurueck".
        if (! self::serverGate()) {
            return self::STATE_GRANTED;
        }

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

    /**
     * Klappt der reservierte Container bei ausdruecklicher Ablehnung ein?
     * Gilt nur fuer `denied`, nie fuer `pending`.
     */
    public static function collapseWhenDenied(): bool
    {
        return config('ad-slots.consent.on_denied', self::ON_DENIED_COLLAPSE) === self::ON_DENIED_COLLAPSE;
    }

    /**
     * Der Wert, der als `data-ad-on-denied` an den Container geht: so kennt
     * resources/js/ads.js die Politik, ohne sie ein zweites Mal zu fuehren
     * (Widerruf im laufenden Besuch, Akzeptanz 3).
     */
    public static function onDeniedAttribute(): string
    {
        return self::collapseWhenDenied() ? self::ON_DENIED_COLLAPSE : self::ON_DENIED_RESERVE;
    }
}
