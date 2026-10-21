<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\CspNonce;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt den CSP-Header fuer alle Portale (#11, #21).
 *
 * Eine Stelle, keine Policy je Tenant — die Quellenliste steht in
 * config/csp.php. Drei Herkuenfte leitet diese Klasse ab, damit eine
 * Adressaenderung nur an einer Stelle wirkt: Turnstile aus
 * config/turnstile.php, das Leadsystem aus config/leads.php und der
 * Vite-Dev-Server aus dem laufenden `npm run dev`.
 *
 * Inline-Skripte werden nicht erlaubt. Die Logik liegt in Modulen unter
 * resources/js/ bzw. im Theme-JS und haengt an data-Attributen
 * (docs/turnstile.md Abschnitt 14). Ausnahmen tragen das Nonce des Requests
 * (App\Services\Security\CspNonce): fremdes HTML aus der Verwaltung —
 * Werbe-Schnipsel und config('app.tracking_scripts') — und der GA4-Block aus
 * components/analytics-meta.blade.php, dessen Reihenfolge im <head>
 * (Consent-Default vor gtag.js) feststehen muss.
 *
 * Vorgabe ist `mode => enforce`. `report` meldet nur, `off` setzt nichts.
 *
 * Gemeldet wird seit #51 auch serverseitig: `report-uri` und `report-to`
 * zeigen auf POST /csp-bericht, dazu kommt der Header `Reporting-Endpoints`.
 * Beide Direktiven, weil Chrome `report-uri` nicht mehr liest und
 * Safari/Firefox `report-to` nicht kennen. Vorher sammelte `report` nichts
 * ein — der Browser schrieb nur in seine eigene Konsole.
 *
 * Angefasst wird nur HTML und nur, wenn die Antwort keinen eigenen CSP-Header
 * traegt — das Bewertungs-Widget (ReviewWidgetController) setzt sich sein
 * `frame-ancestors *` selbst und darf nicht ueberschrieben werden.
 */
class ContentSecurityPolicy
{
    /** Direktiven, an die die Turnstile-Quellen gehaengt werden. */
    private const TURNSTILE_DIRECTIVES = ['script-src', 'frame-src', 'connect-src'];

    /** Direktiven, an die der Vite-Dev-Server gehaengt wird. */
    private const VITE_DIRECTIVES = ['script-src', 'style-src', 'connect-src'];

    public function __construct(private readonly CspNonce $nonce) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $mode = (string) config('csp.mode', 'off');

        if ($mode === 'off' || ! $this->appliesTo($response)) {
            return $response;
        }

        $header = $mode === 'enforce'
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($header, $this->policy());

        if ($this->reportTo() !== null) {
            // Die Gruppe fuer `report-to`. Chrome liest sie aus diesem Header,
            // nicht aus der Policy; ohne ihn ist `report-to` wirkungslos.
            $response->headers->set(
                'Reporting-Endpoints',
                $this->reportGroup().'="'.$this->reportUrl($request).'"'
            );
        }

        return $response;
    }

    /**
     * Nur HTML-Antworten ohne eigenen CSP-Header. Downloads, JSON und
     * Livewire-Antworten brauchen keine Policy.
     */
    private function appliesTo(Response $response): bool
    {
        if ($response->headers->has('Content-Security-Policy')
            || $response->headers->has('Content-Security-Policy-Report-Only')) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function policy(): string
    {
        /** @var array<string, list<string>> $directives */
        $directives = (array) config('csp.directives', []);

        $turnstile = $this->turnstileSources();
        $vite = $this->viteSources();
        $parts = [];

        foreach ($directives as $directive => $sources) {
            $sources = array_merge(
                (array) $sources,
                $this->derivedSources($directive),
                in_array($directive, self::TURNSTILE_DIRECTIVES, true) ? $turnstile : [],
                in_array($directive, self::VITE_DIRECTIVES, true) ? $vite : [],
                $this->extraSources($directive),
            );

            $sources = array_values(array_unique(array_filter($sources)));

            if ($sources === []) {
                continue;
            }

            $parts[] = $directive.' '.implode(' ', $sources);
        }

        // Zum Schluss, damit die Sammelstelle unabhaengig von der
        // Direktivenliste in der Konfiguration gesetzt wird.
        $pfad = $this->reportPath();

        if ($pfad !== null) {
            // Relativ, nicht absolut: der Browser loest gegen das Dokument
            // auf und trifft damit immer das eigene Portal. Eine feste URL
            // waere bei 23 Portalen falsch.
            $parts[] = 'report-uri '.$pfad;
            $parts[] = 'report-to '.$this->reportGroup();
        }

        return implode('; ', $parts);
    }

    /**
     * Pfad der Sammelstelle (#51), oder null, wenn sie abgeschaltet ist.
     * Muss mit einem Schraegstrich beginnen, sonst waere er relativ zum
     * Verzeichnis der aufgerufenen Seite.
     */
    private function reportPath(): ?string
    {
        if (config('csp.report.enabled') !== true) {
            return null;
        }

        $pfad = trim((string) config('csp.report.path'));

        return str_starts_with($pfad, '/') ? $pfad : null;
    }

    /** Name der Berichtsgruppe, die Policy und Reporting-Endpoints verbindet. */
    private function reportGroup(): string
    {
        $gruppe = trim((string) config('csp.report.group'));

        return $gruppe === '' ? 'csp' : $gruppe;
    }

    /**
     * Der Reporting-Endpoints-Header will eine URL, keinen Pfad — und zwar
     * die des aufgerufenen Hosts, damit der Bericht beim eigenen Portal
     * landet und nicht ueber eine fremde Herkunft geht.
     */
    private function reportTo(): ?string
    {
        return $this->reportPath();
    }

    /**
     * Die Reporting-API nimmt nur eine SICHERE URL an: steht dort `http://`,
     * verwirft Chrome die Gruppe stillschweigend und `report-to` ist
     * wirkungslos (nachgemessen in #51). Darum wird das Schema erzwungen,
     * sobald die Umgebung nicht `local` ist — hinter der Cloudflare-Kante
     * kann der Ursprung eine Anfrage als `http` sehen, obwohl der Browser
     * `https` spricht, und ein TrustProxies ist hier nicht konfiguriert.
     * Lokal bleibt es beim Schema der Anfrage, sonst zeigt `php artisan serve`
     * auf eine Adresse, die es nicht gibt.
     */
    private function reportUrl(Request $request): string
    {
        $schema = $request->isSecure() || ! app()->environment('local') ? 'https' : 'http';

        return $schema.'://'.$request->getHttpHost().((string) $this->reportPath());
    }

    /**
     * Quellen, die sich aus anderen Konfigurationen ergeben.
     *
     * @return list<string>
     */
    private function derivedSources(string $directive): array
    {
        return match ($directive) {
            // Nur Werbe- und Tracking-Schnipsel aus der Verwaltung tragen es.
            'script-src' => ["'nonce-".$this->nonce->value()."'"],

            // Der Anfrage-Dialog (resources/views/themes/sun-v2/js/modules/
            // lead-dialog.js) spricht direkt mit der Funnel-API des Leadsystems.
            'connect-src' => $this->origins([(string) config('leads.api_url')]),

            default => [],
        };
    }

    /**
     * Herkunft von api.js und Siteverify, abgeleitet aus config/turnstile.php.
     * Normal ist das beides https://challenges.cloudflare.com.
     *
     * @return list<string>
     */
    private function turnstileSources(): array
    {
        if (config('turnstile.enabled') !== true) {
            return [];
        }

        return $this->origins([
            (string) config('turnstile.script_url'),
            (string) config('turnstile.siteverify_url'),
        ]);
    }

    /**
     * Mit laufendem `npm run dev` kommen CSS und JS von localhost:5173, HMR
     * zusaetzlich ueber eine WebSocket-Verbindung. Nur dann, nie in Produktion.
     *
     * @return list<string>
     */
    private function viteSources(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        $hot = @file_get_contents(public_path('hot'));

        if ($hot === false) {
            return [];
        }

        $origins = $this->origins([trim($hot)]);

        foreach ($origins as $origin) {
            $origins[] = (string) preg_replace('/^http/', 'ws', $origin);
        }

        return array_values(array_unique($origins));
    }

    /**
     * Schema://Host[:Port] aus einer Liste von URLs.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function origins(array $urls): array
    {
        $origins = [];

        foreach ($urls as $url) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($scheme) && is_string($host)) {
                $port = parse_url($url, PHP_URL_PORT);
                $origins[] = $scheme.'://'.$host.(is_int($port) ? ':'.$port : '');
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    private function extraSources(string $directive): array
    {
        $extra = config('csp.extra.'.$directive);

        if (! is_string($extra) || trim($extra) === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', trim($extra)) ?: []));
    }
}
