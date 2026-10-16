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
 * Anker dieses Seitenaufrufs, je Anker bis zu zwei Meldungen — `delivered`
 * (Fragment eingesetzt) und `viewable` (zu mindestens 50 % fuer eine Sekunde
 * sichtbar). Erst beide ergeben die Viewability-Quote (#47).
 *
 * Bewusst OHNE 'web' (siehe routes/tenant.php): keine Session, kein CSRF und
 * damit auch kein Cookie, das die Antwort setzen koennte. Das Zaehlen braucht
 * keine Einwilligung, weil nichts Personenbezogenes uebertragen und nichts
 * davon gespeichert wird ({@see AdImpressionRecorder}) — ein Session-Cookie
 * im Spiel wuerde genau diese Aussage kaputt machen.
 *
 * Abgesichert ist das deshalb anders:
 *
 *   * Rate-Limit an der Route (`throttle:60,1`); Laravel hasht die Adresse
 *     fuer den Schluessel, gespeichert wird sie nicht. Seit #43 wirkt es je
 *     Besucher statt je Cloudflare-Edge (docs/echte-besucher-ip.md).
 *   * hoechstens `ad-slots.impressions.max_entries` Meldungen je Aufruf
 *   * Rumpfgroesse hart begrenzt
 *   * Portal ist immer der Tenant der Anfrage, nie ein Wert aus dem Rumpf
 *   * bekannte Bot-Kennungen zaehlen nicht ({@see BotTraffic})
 *   * Herkunftstest ({@see sameOrigin()}): die Meldung muss von der
 *     Portal-Domain selbst kommen. Ohne CSRF koennte sonst eine fremde Seite
 *     per Formular-POST mit `text/plain` zaehlen — ohne Preflight, CORS
 *     verhindert das nicht (#42, docs/ad-slots-security-review.md 13.2).
 *   * die gemeldete `placement_id` muss es auf diesem Portal und diesem Anker
 *     geben ({@see AdImpressionRecorder::normalize()})
 *
 * Geantwortet wird immer 204 — auch auf Unsinn. Der Browser wertet die
 * Antwort nicht aus (sendBeacon kennt kein Ergebnis), und eine Fehlermeldung
 * wuerde nur verraten, was durchkommt. Dasselbe Muster wie bei den
 * CSP-Berichten ({@see \App\Http\Controllers\Portal\CspReportController}).
 */
class AdImpressionController extends Controller
{
    /** Mehr als das kann ein ehrliches Beacon nicht sein (40 Meldungen ≈ 3 kB). */
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

        if (! $this->sameOrigin($request)) {
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
     * Kommt die Meldung von der Portal-Domain selbst?
     *
     * Zwei Angaben, in dieser Reihenfolge:
     *
     *   * `Sec-Fetch-Site` — vom Browser gesetzt und von der Seite nicht
     *     beschreibbar. Nur `same-origin` zaehlt; `cross-site`, `same-site`
     *     (andere Subdomain) und `none` nicht.
     *   * `Origin` — Rueckfall fuer Browser ohne Fetch-Metadata. Verglichen
     *     wird nur der Host, nicht das Schema: am Ursprung endet TLS bei
     *     Cloudflare, der Request kommt dort als `http` an.
     *
     * Fehlen beide, wird verworfen: ein Browser schickt zu einem POST immer
     * einen `Origin`, ein Beacon ohne beides ist kein Seitenaufruf. Die
     * Antwort bleibt in jedem Fall `204`.
     */
    private function sameOrigin(Request $request): bool
    {
        $site = strtolower(trim((string) $request->headers->get('Sec-Fetch-Site')));

        if ($site !== '') {
            return $site === 'same-origin';
        }

        $origin = trim((string) $request->headers->get('Origin'));

        if ($origin === '') {
            return false;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, $request->getHost()) === 0;
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
