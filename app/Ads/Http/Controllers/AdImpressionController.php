<?php

declare(strict_types=1);

namespace App\Ads\Http\Controllers;

use App\Ads\Support\AdImpressionRecorder;
use App\AntiSpam\Support\BotTraffic;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /_ads/impressions (Ticket #12, docs/ad-slots.md 14).
 *
 * Nimmt das gebuendelte Beacon aus resources/js/ads.js an: eine Liste der
 * Anker, die auf diesem Seitenaufruf zu mindestens 50 % fuer eine Sekunde
 * sichtbar waren.
 *
 * Bewusst OHNE 'web' (siehe routes/tenant.php): keine Session, kein CSRF und
 * damit auch kein Cookie, das die Antwort setzen koennte. Das Zaehlen braucht
 * keine Einwilligung, weil nichts Personenbezogenes uebertragen und nichts
 * davon gespeichert wird ({@see AdImpressionRecorder}) — ein Session-Cookie
 * im Spiel wuerde genau diese Aussage kaputt machen.
 *
 * Abgesichert ist das deshalb anders:
 *
 *   * Rate-Limit je IP an der Route (`throttle:60,1`); Laravel hasht die
 *     Adresse fuer den Schluessel, gespeichert wird sie nicht.
 *   * hoechstens `ad-slots.impressions.max_entries` Meldungen je Aufruf
 *   * Rumpfgroesse hart begrenzt
 *   * Portal ist immer der Tenant der Anfrage, nie ein Wert aus dem Rumpf
 *   * bekannte Bot-Kennungen zaehlen nicht ({@see BotTraffic})
 *
 * Geantwortet wird immer 204 — auch auf Unsinn. Der Browser wertet die
 * Antwort nicht aus (sendBeacon kennt kein Ergebnis), und eine Fehlermeldung
 * wuerde nur verraten, was durchkommt. Dasselbe Muster wie bei den
 * CSP-Berichten ({@see \App\Http\Controllers\Portal\CspReportController}).
 */
class AdImpressionController extends Controller
{
    /** Mehr als das kann ein ehrliches Beacon nicht sein (20 Meldungen ≈ 1,5 kB). */
    private const MAX_BODY_BYTES = 8_000;

    public function __invoke(Request $request, AdImpressionRecorder $recorder): Response
    {
        if (! AdImpressionRecorder::enabled()) {
            return response()->noContent();
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant || BotTraffic::isBot($request)) {
            return response()->noContent();
        }

        $body = $request->getContent();

        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $recorder->record((int) $tenant->getKey(), $this->entries($body));

        return response()->noContent();
    }

    /**
     * Die Meldungen aus dem Rumpf, ohne jede Pruefung des Inhalts — die macht
     * der Recorder gegen den Katalog.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(string $body): array
    {
        $payload = json_decode($body, true);

        if (! is_array($payload) || ! is_array($payload['slots'] ?? null)) {
            return [];
        }

        return array_values(array_filter($payload['slots'], 'is_array'));
    }
}
