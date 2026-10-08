<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Turnstile\Config\TurnstileConfigResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Prueft ein Secret gegen Cloudflare, ohne ein Formular abzuschicken (#9, #14).
 *
 * Geschickt wird ein erfundener Token. Die erwartete Antwort ist
 * `success=false` mit `invalid-input-response` — Cloudflare beanstandet dann nur
 * den Token, das Secret gilt also und der Endpunkt ist erreichbar.
 * `invalid-input-secret` heisst umgekehrt "falscher Schluessel". Antwortet
 * Cloudflare mit `success=true`, liegt ein "besteht immer"-Testschluessel vor:
 * genau der Zustand, der in Produktion nichts schuetzt.
 *
 * Bewusst ohne {@see \App\Turnstile\Services\TurnstileVerifier}: der verifiziert
 * echte Tokens aus einem Formular und darf laut docs/turnstile.md §2 nur von
 * der Rule aufgerufen werden. Dies hier ist eine Diagnose der Schluessel.
 *
 * Es wird nie ein Secret ausgegeben, geloggt oder in eine Meldung geschrieben.
 */
final class SiteverifyProbe
{
    /** Secret gilt, Endpunkt erreichbar. */
    public const STATE_OK = 'ok';

    /** Cloudflare kennt dieses Secret nicht. */
    public const STATE_REJECTED = 'rejected';

    /** Es ist gar kein Secret hinterlegt. */
    public const STATE_MISSING = 'missing';

    /** Cloudflare-Testschluessel: nimmt jeden Token an. */
    public const STATE_TEST_KEY = 'test_key';

    /** Netzwerkfehler, Zeitueberschreitung, unlesbare Antwort. */
    public const STATE_UNREACHABLE = 'unreachable';

    /** Geantwortet, aber mit unerwarteten Fehlercodes. */
    public const STATE_UNCLEAR = 'unclear';

    public static function check(?string $secretKey): SiteverifyProbeResult
    {
        $secretKey = trim((string) $secretKey);

        if ($secretKey === '') {
            return new SiteverifyProbeResult(
                self::STATE_MISSING,
                'fehlt',
                __('Es ist kein Secret hinterlegt — weder beim Portal noch in der Serverkonfiguration.'),
            );
        }

        try {
            $antwort = Http::asForm()
                // Bewusst grosszuegiger als turnstile.timeout_seconds: hier
                // haengt keine Anfrage eines Besuchers dahinter.
                ->timeout(10)
                ->post((string) config('turnstile.siteverify_url'), [
                    'secret' => $secretKey,
                    'response' => 'probe-ungueltig',
                ])
                ->json();
        } catch (Throwable $exception) {
            return new SiteverifyProbeResult(
                self::STATE_UNREACHABLE,
                'nicht erreichbar: '.$exception->getMessage(),
                __('Cloudflare ist nicht erreichbar: :grund', ['grund' => $exception->getMessage()]),
            );
        }

        $codes = is_array($antwort) && is_array($antwort['error-codes'] ?? null)
            ? array_map('strval', $antwort['error-codes'])
            : [];

        if (is_array($antwort) && ($antwort['success'] ?? null) === true) {
            return new SiteverifyProbeResult(
                self::STATE_TEST_KEY,
                'nimmt jeden Token an (Testschluessel)',
                self::isTestKey($secretKey)
                    ? __('Hier liegt ein Cloudflare-Testschlüssel: er nimmt jeden Token an und schützt nichts.')
                    : __('Cloudflare nimmt sogar einen erfundenen Token an — dieses Secret schützt nichts.'),
            );
        }

        // Cloudflare beanstandet nur den erfundenen Token: das Secret gilt.
        if (in_array('invalid-input-response', $codes, true)) {
            return new SiteverifyProbeResult(
                self::STATE_OK,
                'ok',
                __('Verbindung in Ordnung: Cloudflare ist erreichbar und beanstandet nur den Testtoken (invalid-input-response).'),
            );
        }

        if (in_array('invalid-input-secret', $codes, true)) {
            return new SiteverifyProbeResult(
                self::STATE_REJECTED,
                'abgelehnt',
                __('Cloudflare lehnt das Secret ab (invalid-input-secret). Der Schlüssel passt nicht zum Widget.'),
            );
        }

        return new SiteverifyProbeResult(
            self::STATE_UNCLEAR,
            'unklar: '.implode(',', $codes),
            __('Unerwartete Antwort von Cloudflare: :codes', ['codes' => $codes === [] ? '—' : implode(', ', $codes)]),
        );
    }

    private static function isTestKey(string $secretKey): bool
    {
        return in_array($secretKey, TurnstileConfigResolver::TEST_SECRET_KEYS, true);
    }
}
