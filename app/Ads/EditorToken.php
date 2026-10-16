<?php

declare(strict_types=1);

namespace App\Ads;

use App\Ads\Support\AdSlotCatalog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Der signierte Token des Editor-Modus (SUN-AD-005, Ticket #6).
 *
 * Erzeugt wird er im Panel (#7) fuer genau eine Portalseite, geprueft wird er
 * von {@see \App\Ads\Http\Middleware\AdEditorMode}. Mehr als die Signatur gibt
 * es nicht: der Portal-Teil kennt den angemeldeten Admin nicht — er laeuft auf
 * der Tenant-Domain, das Panel auf der zentralen.
 *
 * Warum keine Session und kein `signed`-Middleware von Laravel:
 *
 * * **Session.** Das Panel bettet das Portal cross-site ein. Laravels
 *   Sitzungs-Cookie ist `SameSite=lax` und wird in einem fremden iframe gar
 *   nicht mitgeschickt — ein Session-Flag koennte dort niemals ankommen.
 * * **`URL::temporarySignedRoute`.** Die Signatur gilt dort fuer eine
 *   *benannte* Route samt vollstaendiger Query. Die Vorschau zeigt aber
 *   beliebige Portalseiten (`/`, `/firmen/muster-gmbh`, `/ratgeber/…`), und
 *   eine Suchseite bringt eigene Parameter mit. Darum signiert diese Klasse
 *   nur, was sie binden will: Host, Pfad und Ablauf.
 *
 * Gebunden ist der Token an **Host und Pfad**: ein Token fuer
 * `fliesenleger.io/firmen/muster` oeffnet keine andere Seite und kein anderes
 * Portal. Die uebrige Query bleibt frei, damit die Vorschau der Suchseite
 * filtern kann, ohne die Signatur zu brechen; sie veraendert nur Inhalte, nie
 * Rechte — der Modus ist lesend (GET) und schreibt nichts.
 */
final class EditorToken
{
    /** Query-Parameter der iframe-Adresse. */
    public const EXPIRES_PARAM = 'ad_editor_expires';

    public const SIGNATURE_PARAM = 'ad_editor_signature';

    /** Seitentyp der Vorschau — Information fuer das Markup, nicht Teil der Signatur. */
    public const PAGE_TYPE_PARAM = 'ad_editor_page_type';

    /**
     * Adresse fuer den iframe des Panels.
     *
     * @param  Tenant  $tenant  Portal, dessen Domain die Vorschau zeigt
     * @param  string  $path  Pfad der Portalseite, mit oder ohne fuehrenden Schraegstrich
     * @param  array<string, string|int>  $query  zusaetzliche Parameter der Seite (Suche, Seitenzahl)
     */
    public static function url(Tenant $tenant, string $path = '/', ?string $pageType = null, array $query = []): string
    {
        $domain = (string) $tenant->getAttribute('domain');

        if ($domain === '') {
            throw new RuntimeException("Portal {$tenant->getKey()} hat keine Domain — ohne sie gibt es keine Vorschau-Adresse.");
        }

        $path = self::normalizePath($path);
        $expires = Carbon::now()->addMinutes(self::minutes())->getTimestamp();

        $parameters = $query + [
            self::EXPIRES_PARAM => $expires,
            self::SIGNATURE_PARAM => self::signature($domain, $path, $expires),
        ];

        if ($pageType !== null) {
            $parameters[self::PAGE_TYPE_PARAM] = $pageType;
        }

        return 'https://'.$domain.$path.'?'.http_build_query($parameters);
    }

    /**
     * Traegt diese Anfrage einen gueltigen, nicht abgelaufenen Token?
     *
     * Falsch heisst immer „normale Seite" — nie 403: eine abgelaufene Adresse
     * im Verlauf darf keine Fehlerseite zeigen (Ticket #6, Akzeptanz 5).
     */
    public static function valid(Request $request): bool
    {
        $signature = $request->query(self::SIGNATURE_PARAM);
        $expires = $request->query(self::EXPIRES_PARAM);

        if (! is_string($signature) || ! is_numeric($expires)) {
            return false;
        }

        if ((int) $expires < Carbon::now()->getTimestamp()) {
            return false;
        }

        $erwartet = self::signature(
            $request->getHost(),
            self::normalizePath($request->getPathInfo()),
            (int) $expires,
        );

        return hash_equals($erwartet, $signature);
    }

    /** Steht ueberhaupt ein Token in der Adresse? Fuer die Unterscheidung „kein Token" / „fauler Token". */
    public static function present(Request $request): bool
    {
        return $request->query(self::SIGNATURE_PARAM) !== null;
    }

    /**
     * Seitentyp der Vorschau. Bewusst nicht signiert: er sagt dem Panel nur,
     * welche Slot-Liste es zeigen soll. Ein unbekannter Wert wird verworfen.
     */
    public static function pageType(Request $request): ?string
    {
        $wert = $request->query(self::PAGE_TYPE_PARAM);

        return is_string($wert) && $wert !== '' ? $wert : null;
    }

    /** Die Herkunft, mit der die postMessage-Bruecke spricht (config/ad-slots.php). */
    public static function origin(): string
    {
        $wert = (string) (config('ad-slots.editor_origin') ?? '');

        $teile = parse_url($wert);

        if (! isset($teile['scheme'], $teile['host'])) {
            return '';
        }

        $port = isset($teile['port']) ? ':'.$teile['port'] : '';

        return $teile['scheme'].'://'.$teile['host'].$port;
    }

    /** Katalogversion fuer die Bruecke: eine abweichende Fassung laedt das Panel neu. */
    public static function catalogVersion(): int
    {
        return AdSlotCatalog::version();
    }

    public static function minutes(): int
    {
        return max(1, (int) config('ad-slots.editor_token_minutes', 30));
    }

    private static function signature(string $host, string $path, int $expires): string
    {
        return hash_hmac('sha256', $host.'|'.$path.'|'.$expires, self::key());
    }

    /**
     * Derselbe Schluessel, mit dem Laravel seine signierten URLs rechnet
     * (APP_KEY, roh wie in der Konfiguration).
     */
    private static function key(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY fehlt — ohne ihn gibt es keinen Editor-Token.');
        }

        return $key;
    }

    /** Pfad mit genau einem fuehrenden Schraegstrich, ohne Schlusszeichen (ausser "/"). */
    private static function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
