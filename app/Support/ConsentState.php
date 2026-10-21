<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Die gespeicherte Einwilligung als Consent-Mode-Signale, serverseitig (#4).
 *
 * Zweck: beim Folgeaufruf steht der Consent-DEFAULT im <head> schon auf dem,
 * was der Besucher entschieden hat. Ohne das laeuft jeder Seitenaufruf bis zum
 * 'consent update' aus dem Bundle (resources/js/analytics.js, #3) cookielos,
 * obwohl zugestimmt wurde.
 *
 * Gelesen werden ausschliesslich die Cookies des Einwilligungs-Hinweises, und
 * zwar nur lesend — Name, Laufzeit und Format bleiben unberuehrt:
 *
 *   cookie_consent_given=1        es gibt eine Entscheidung
 *   cookie_consent_statistics=1|0 Statistik
 *   cookie_consent_marketing=1|0  Werbung
 *
 * Sie sind Klartext (der Browser schreibt sie mit `document.cookie`) und
 * stehen darum in der Ausnahmeliste von EncryptCookies (bootstrap/app.php,
 * #21). Das Ersatz-Cookie `cookie_consent_preferences` (JSON, nur bei
 * gesperrtem localStorage) steht NICHT in der Liste und ist serverseitig
 * folglich immer null — es wird hier bewusst nicht gelesen, sonst sieht der
 * Server mal eine Entscheidung und mal nicht. In diesem Fall bleibt der
 * Default 'denied' und das Bundle zieht per 'consent update' nach.
 *
 * Fail closed: alles, was nicht genau '1' ist — fehlendes Cookie, '0', 'true',
 * Array, manipulierter Inhalt —, gilt als Ablehnung. Es gibt keinen Pfad, auf
 * dem ein ungueltiger Wert 'granted' ergibt, und keinen, der wirft.
 *
 * `security_storage` ist immer 'granted': es deckt Sicherheitsfunktionen ab
 * und haengt an keiner Kategorie.
 *
 * Das Mapping ist dasselbe wie in resources/js/analytics.js (erlaubnisse()) —
 * beide Seiten muessen dasselbe sagen, sonst feuert das Bundle beim
 * Folgeaufruf ein Update, das nichts aendert.
 */
final class ConsentState
{
    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    private function __construct(
        private readonly bool $statistics,
        private readonly bool $marketing,
    ) {}

    public static function fromRequest(Request $request): self
    {
        if (self::flag($request, 'cookie_consent_given') === false) {
            return new self(false, false);
        }

        return new self(
            self::flag($request, 'cookie_consent_statistics'),
            self::flag($request, 'cookie_consent_marketing'),
        );
    }

    /** Alle Signale fuer `gtag('consent', 'default', …)`. */
    public function signals(): array
    {
        return [
            'analytics_storage' => self::value($this->statistics),
            'ad_storage' => self::value($this->marketing),
            'ad_user_data' => self::value($this->marketing),
            'ad_personalization' => self::value($this->marketing),
            // Der Cookie-Hinweis kennt keine eigene Kategorie 'Funktional'.
            // Was es dort an funktionalen Cookies gibt, ist notwendig und
            // steht nicht zur Wahl. Bei 'Alle akzeptieren' sind beide
            // Kategorien an, damit steht functionality_storage wie gefordert
            // auf 'granted'; wer nur eine Kategorie waehlt, willigt ebenfalls
            // ueber das Notwendige hinaus ein. Gleiche Regel wie im Bundle.
            'functionality_storage' => self::value($this->statistics || $this->marketing),
            'security_storage' => self::GRANTED,
        ];
    }

    /** Liegt ueberhaupt eine Zustimmung vor? Nur fuer Kommentare/Diagnose. */
    public function anyGranted(): bool
    {
        return $this->statistics || $this->marketing;
    }

    private static function flag(Request $request, string $cookie): bool
    {
        $wert = $request->cookie($cookie);

        return is_string($wert) && $wert === '1';
    }

    private static function value(bool $granted): string
    {
        return $granted ? self::GRANTED : self::DENIED;
    }
}
