<?php

declare(strict_types=1);

namespace App\Content\Providers;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zugang zur Google Search Console API (#9).
 *
 * Authentifizierung ueber einen einzigen Service Account, der in jeder
 * Tenant-Property als Nutzer eingetragen ist. Die JSON-Schluesseldatei liegt
 * ausserhalb des Web-Roots; der Pfad steht in
 * config('content.providers.search_console.credentials_path').
 *
 * Bewusst ohne google/apiclient: gebraucht wird genau ein Endpunkt
 * (searchanalytics.query) und der Service-Account-Flow. Der besteht aus einem
 * selbst signierten JWT (RS256), das gegen ein Zugriffstoken getauscht wird —
 * rund 60 Zeilen gegen ein Paket, das mit google/apiclient-services den
 * vendor-Ordner um mehrere hundert Megabyte aufblaeht. Der Rest laeuft ueber
 * den HTTP-Client des Frameworks, wie beim LlmClient (#6) auch.
 *
 * Das Zugriffstoken wird zwischengespeichert, damit nicht jeder Mandant seinen
 * eigenen Token-Tausch ausloest.
 */
class SearchConsoleClient
{
    public const PROVIDER = 'search_console';

    private const TOKEN_CACHE_KEY = 'content:gsc:access_token';

    private const JWT_LIFETIME = 3600;

    /**
     * Ist ein brauchbarer Schluessel hinterlegt? Der Connector prueft das,
     * bevor er ueberhaupt Mandanten durchgeht.
     */
    public function isConfigured(): bool
    {
        $path = $this->credentialsPath();

        return $path !== null && is_readable($path);
    }

    /**
     * Suchanfragen einer Property ueber ein Zeitfenster.
     *
     * Paginiert selbst ueber startRow, bis die API weniger Zeilen liefert als
     * angefordert oder max_rows erreicht ist.
     *
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>> Rohzeilen der API
     */
    public function searchAnalytics(
        string $property,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        array $dimensions = ['query', 'page'],
    ): array {
        $rowLimit = (int) $this->option('row_limit', 5000);
        $maxRows = (int) $this->option('max_rows', 25000);

        $rows = [];
        $startRow = 0;

        do {
            $batch = $this->query($property, [
                'startDate' => CarbonImmutable::parse($startDate)->toDateString(),
                'endDate' => CarbonImmutable::parse($endDate)->toDateString(),
                'dimensions' => array_values($dimensions),
                'rowLimit' => $rowLimit,
                'startRow' => $startRow,
                'dataState' => (string) $this->option('data_state', 'final'),
                'type' => 'web',
            ]);

            foreach ($batch as $row) {
                $rows[] = $row;
            }

            $startRow += $rowLimit;
        } while (count($batch) >= $rowLimit && count($rows) < $maxRows);

        return array_slice($rows, 0, $maxRows);
    }

    /**
     * Properties, die das Dienstkonto sehen darf (sites.list).
     *
     * Nur fuer die Abnahme gedacht (#90): eine Property, in der das
     * Dienstkonto nicht als Nutzer eingetragen ist, liefert bei
     * searchanalytics.query keine Fehlermeldung, sondern eine leere Liste.
     * Der Vergleich mit dieser Aufzaehlung trennt "keine Freigabe" von
     * "noch keine Daten".
     *
     * @return array<string, string> siteUrl => permissionLevel
     */
    public function sites(): array
    {
        $url = rtrim((string) $this->option('base_url', 'https://searchconsole.googleapis.com/webmasters/v3'), '/').'/sites';

        $response = $this->request()->get($url)->throw()->json();

        $sites = [];

        foreach ((array) ($response['siteEntry'] ?? []) as $entry) {
            $siteUrl = $entry['siteUrl'] ?? null;

            if (is_string($siteUrl) && $siteUrl !== '') {
                $sites[$siteUrl] = (string) ($entry['permissionLevel'] ?? 'unbekannt');
            }
        }

        return $sites;
    }

    /**
     * Adresse des Dienstkontos aus der Schluesseldatei — die Kennung, die in
     * Search Console und AdSense als Nutzer eingetragen werden muss.
     */
    public function serviceAccountEmail(): string
    {
        return (string) $this->credentials()['client_email'];
    }

    /**
     * Ein einzelner searchanalytics.query-Aufruf.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    public function query(string $property, array $body): array
    {
        $url = rtrim((string) $this->option('base_url', 'https://searchconsole.googleapis.com/webmasters/v3'), '/')
            .'/sites/'.rawurlencode($property).'/searchAnalytics/query';

        $response = $this->request()->post($url, $body)->throw()->json();

        return is_array($response['rows'] ?? null) ? $response['rows'] : [];
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 60));
    }

    /**
     * Zugriffstoken des Service Accounts, zwischengespeichert bis kurz vor
     * Ablauf. Der Schluessel des Cache-Eintrags haengt am Dienstkonto, damit
     * ein Schluesselwechsel nicht auf ein altes Token faellt.
     */
    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $key = self::TOKEN_CACHE_KEY.':'.sha1((string) $credentials['client_email']);

        return Cache::remember(
            $key,
            (int) $this->option('token_ttl', 3300),
            fn () => $this->fetchAccessToken($credentials),
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function fetchAccessToken(array $credentials): string
    {
        $response = Http::asForm()
            ->timeout((int) $this->option('timeout', 60))
            ->post((string) $this->option('token_url', 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($credentials),
            ])
            ->throw()
            ->json();

        $token = $response['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Search Console: Token-Tausch lieferte kein access_token.');
        }

        return $token;
    }

    /**
     * Signiertes JWT nach RFC 7523 (Google: "Service Account Flow").
     *
     * @param  array<string, mixed>  $credentials
     */
    private function assertion(array $credentials): string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        if (isset($credentials['private_key_id'])) {
            $header['kid'] = (string) $credentials['private_key_id'];
        }

        $claims = [
            'iss' => (string) $credentials['client_email'],
            'scope' => (string) $this->option('scope', 'https://www.googleapis.com/auth/webmasters.readonly'),
            'aud' => (string) $this->option('token_url', 'https://oauth2.googleapis.com/token'),
            'iat' => $now,
            'exp' => $now + self::JWT_LIFETIME,
        ];

        $payload = $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES))
            .'.'.$this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Search Console: private_key der Schluesseldatei ist nicht lesbar.');
        }

        $signature = '';

        if (! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Search Console: JWT konnte nicht signiert werden.');
        }

        return $payload.'.'.$this->base64Url($signature);
    }

    /**
     * Inhalt der Schluesseldatei. Der Pfad wird bei jedem Zugriff geprueft:
     * ein Schluessel unterhalb von public/ waere ueber das Web erreichbar.
     *
     * @return array{client_email: string, private_key: string, private_key_id?: string}
     */
    private function credentials(): array
    {
        $path = $this->credentialsPath();

        if ($path === null) {
            throw new RuntimeException(
                'Search Console: kein Schluesselpfad gesetzt (GOOGLE_SERVICE_ACCOUNT_JSON).',
            );
        }

        $this->assertOutsideWebRoot($path);

        if (! is_readable($path)) {
            throw new RuntimeException("Search Console: Schluesseldatei '{$path}' ist nicht lesbar.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Search Console: Schluesseldatei '{$path}' enthaelt kein gueltiges JSON.");
        }

        foreach (['client_email', 'private_key'] as $field) {
            if (! is_string($decoded[$field] ?? null) || $decoded[$field] === '') {
                throw new RuntimeException("Search Console: Schluesseldatei ohne '{$field}'.");
            }
        }

        return $decoded;
    }

    private function assertOutsideWebRoot(string $path): void
    {
        $real = realpath($path);
        $public = realpath(public_path());

        if ($real === false || $public === false) {
            return;
        }

        if (str_starts_with($real, rtrim($public, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                "Search Console: Schluesseldatei '{$real}' liegt im Web-Root und waere oeffentlich abrufbar.",
            );
        }
    }

    private function credentialsPath(): ?string
    {
        $path = $this->option('credentials_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        // Relative Angaben beziehen sich auf das Projektverzeichnis.
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.search_console.{$key}", $default);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
