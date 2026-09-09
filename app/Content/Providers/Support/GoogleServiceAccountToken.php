<?php

declare(strict_types=1);

namespace App\Content\Providers\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zugriffstoken eines Google-Dienstkontos (#23).
 *
 * Derselbe Service-Account-Flow, den der SearchConsoleClient (#9) fuer die
 * Search Console benutzt: ein selbst signiertes JWT (RS256) wird gegen ein
 * Zugriffstoken getauscht. Ausgelagert, weil der Metrik-Collector einen
 * zweiten Bereich braucht (adsense.readonly) und ein Token immer genau fuer
 * einen Bereich gilt — zwei Bereiche heissen zwei Tokens, aber nicht zwei
 * Implementierungen.
 *
 * Der SearchConsoleClient bleibt unveraendert: sein Token ist erprobt und
 * seine Pruefungen (Schluessel ausserhalb des Web-Roots) sind identisch, aber
 * ein Umbau gehoert nicht in dieses Ticket.
 *
 * Tokens werden bis kurz vor Ablauf zwischengespeichert, der Cache-Schluessel
 * haengt an Dienstkonto und Bereich.
 */
final class GoogleServiceAccountToken
{
    private const CACHE_PREFIX = 'content:google:access_token';

    private const JWT_LIFETIME = 3600;

    public function __construct(
        private readonly ?string $credentialsPath,
        private readonly string $scope,
        private readonly string $tokenUrl = 'https://oauth2.googleapis.com/token',
        private readonly int $timeout = 60,
        private readonly int $ttl = 3300,
    ) {}

    /**
     * Liegt eine lesbare Schluesseldatei vor? Der Aufrufer prueft das, bevor
     * er ueberhaupt Mandanten durchgeht.
     */
    public function isConfigured(): bool
    {
        $path = $this->path();

        return $path !== null && is_readable($path);
    }

    public function token(): string
    {
        $credentials = $this->credentials();
        $key = self::CACHE_PREFIX.':'.sha1((string) $credentials['client_email'].'|'.$this->scope);

        return Cache::remember($key, $this->ttl, fn (): string => $this->fetch($credentials));
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function fetch(array $credentials): string
    {
        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($this->tokenUrl, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($credentials),
            ])
            ->throw()
            ->json();

        $token = $response['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google: Token-Tausch lieferte kein access_token.');
        }

        return $token;
    }

    /**
     * Signiertes JWT nach RFC 7523.
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
            'scope' => $this->scope,
            'aud' => $this->tokenUrl,
            'iat' => $now,
            'exp' => $now + self::JWT_LIFETIME,
        ];

        $payload = $this->base64Url((string) json_encode($header, JSON_UNESCAPED_SLASHES))
            .'.'.$this->base64Url((string) json_encode($claims, JSON_UNESCAPED_SLASHES));

        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Google: private_key der Schluesseldatei ist nicht lesbar.');
        }

        $signature = '';

        if (! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Google: JWT konnte nicht signiert werden.');
        }

        return $payload.'.'.$this->base64Url($signature);
    }

    /**
     * @return array{client_email: string, private_key: string, private_key_id?: string}
     */
    private function credentials(): array
    {
        $path = $this->path();

        if ($path === null) {
            throw new RuntimeException('Google: kein Schluesselpfad gesetzt (GOOGLE_SERVICE_ACCOUNT_JSON).');
        }

        $this->assertOutsideWebRoot($path);

        if (! is_readable($path)) {
            throw new RuntimeException("Google: Schluesseldatei '{$path}' ist nicht lesbar.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Google: Schluesseldatei '{$path}' enthaelt kein gueltiges JSON.");
        }

        foreach (['client_email', 'private_key'] as $field) {
            if (! is_string($decoded[$field] ?? null) || $decoded[$field] === '') {
                throw new RuntimeException("Google: Schluesseldatei ohne '{$field}'.");
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
                "Google: Schluesseldatei '{$real}' liegt im Web-Root und waere oeffentlich abrufbar.",
            );
        }
    }

    private function path(): ?string
    {
        if (! is_string($this->credentialsPath) || trim($this->credentialsPath) === '') {
            return null;
        }

        $path = trim($this->credentialsPath);

        // Relative Angaben beziehen sich auf das Projektverzeichnis.
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
