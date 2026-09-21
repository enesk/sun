<?php

declare(strict_types=1);

namespace App\Guide\Publishing;

use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * IndexNow-Ping des Ratgebersystems (#12).
 *
 * Ein POST an api.indexnow.org mit host, key, keyLocation und urlList
 * erreicht Bing, Yandex und Seznam; Google nimmt IndexNow nicht an und
 * bekommt die Aenderung ueber lastmod der Ratgeber-Sitemap.
 *
 * Der Schluessel wird nirgends gespeichert, sondern je Mandant aus
 * Tenant-Schluessel und APP_KEY abgeleitet — dieselbe Ableitung wie in der
 * alten Pipeline (IndexNowClient, SUN-RC-020, entfernt mit #35), damit
 * die unter /<key>.txt ausgelieferte Datei (IndexNowKeyController) fuer
 * beide gilt, bis die alte Pipeline zurueckgebaut ist (#23).
 *
 * Grundregel: Ein Ping haelt eine Veroeffentlichung nie auf. Wiederholt
 * wird nach guide.resilience.retry nur bei 429, 5xx und Verbindungsfehlern;
 * jeder Fehler wird protokolliert und geschluckt.
 */
final class IndexNowClient
{
    public function isEnabled(): bool
    {
        return (bool) config('guide.publishing.indexnow.enabled', true);
    }

    /**
     * Schluessel des Mandanten, nur [a-f0-9] (Route-Muster in routes/tenant.php).
     */
    public function key(Tenant $tenant): string
    {
        $length = max(8, min(128, (int) config('guide.publishing.indexnow.key_length', 32)));

        return substr(hash_hmac('sha256', 'indexnow|'.$tenant->getTenantKey(), (string) config('app.key')), 0, $length);
    }

    public function keyLocation(Tenant $tenant): ?string
    {
        return $this->host($tenant) === null ? null : $this->baseUrl($tenant).'/'.$this->key($tenant).'.txt';
    }

    /**
     * Meldet URLs (absolut oder relativ zur Portal-Domain). Rueckgabe ist der
     * Protokolleintrag fuer guide_topic_runs.publish_json.
     *
     * @param  array<int, string>  $urls
     * @return array{status: string, http_status: int|null, urls: array<int, string>, key_location: string|null, message: string|null, pinged_at: string}
     */
    public function submit(Tenant $tenant, array $urls): array
    {
        $host = $this->host($tenant);
        $urls = $this->normalize($tenant, $urls);
        $result = [
            'status' => 'skipped',
            'http_status' => null,
            'urls' => $urls,
            'key_location' => $this->keyLocation($tenant),
            'message' => null,
            'pinged_at' => now()->toIso8601String(),
        ];

        if (! $this->isEnabled()) {
            $result['message'] = 'IndexNow ist abgeschaltet (guide.publishing.indexnow.enabled).';

            return $this->log($tenant, $result);
        }

        if ($host === null || $urls === []) {
            $result['message'] = $host === null ? 'Der Mandant hat keine Domain.' : 'Keine meldbaren URLs.';

            return $this->log($tenant, $result);
        }

        try {
            $response = Http::timeout(max(1, (int) config('guide.publishing.indexnow.timeout', 15)))
                ->acceptJson()
                ->asJson()
                ->retry(
                    max(1, (int) config('guide.resilience.retry.attempts', 3)),
                    fn (int $attempt): int => $this->delayMs($attempt),
                    fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                    false,
                )
                ->post((string) config('guide.publishing.indexnow.endpoint', 'https://api.indexnow.org/indexnow'), [
                    'host' => $host,
                    'key' => $this->key($tenant),
                    'keyLocation' => $result['key_location'],
                    'urlList' => $urls,
                ]);

            $result['http_status'] = $response->status();
            $result['status'] = $response->successful() ? 'ok' : 'failed';
            $result['message'] = $response->successful() ? null : Str::limit($response->body(), 300, '');
        } catch (Throwable $exception) {
            $result['status'] = 'failed';
            $result['message'] = Str::limit($exception->getMessage(), 300, '');
        }

        return $this->log($tenant, $result);
    }

    public function baseUrl(Tenant $tenant): string
    {
        $host = $this->host($tenant);

        if ($host === null) {
            return rtrim((string) config('app.url'), '/');
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$host}";
    }

    /**
     * 2 s -> 6 s -> 18 s plus Jitter (docs/guide-system.md, §6).
     */
    private function delayMs(int $attempt): int
    {
        $base = (int) config('guide.resilience.retry.base_delay_ms', 2000);
        $multiplier = (int) config('guide.resilience.retry.multiplier', 3);
        $jitter = (int) config('guide.resilience.retry.jitter_ms', 500);

        return (int) ($base * $multiplier ** max(0, $attempt - 1)) + random_int(0, max(0, $jitter));
    }

    /**
     * @param  array{status: string, http_status: int|null, urls: array<int, string>, key_location: string|null, message: string|null, pinged_at: string}  $result
     * @return array{status: string, http_status: int|null, urls: array<int, string>, key_location: string|null, message: string|null, pinged_at: string}
     */
    private function log(Tenant $tenant, array $result): array
    {
        $context = [
            'tenant_id' => $tenant->getKey(),
            'host' => $this->host($tenant),
            'urls' => $result['urls'],
            'http_status' => $result['http_status'],
            'message' => $result['message'],
        ];

        match ($result['status']) {
            'ok' => Log::info('Ratgeber: IndexNow gemeldet.', $context),
            'failed' => Log::warning('Ratgeber: IndexNow-Meldung fehlgeschlagen.', $context),
            default => Log::info('Ratgeber: IndexNow uebersprungen.', $context),
        };

        return $result;
    }

    /**
     * Absolute URLs desselben Hosts, ohne Duplikate und innerhalb der
     * Mengengrenze der API.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function normalize(Tenant $tenant, array $urls): array
    {
        $host = $this->host($tenant);
        $base = $this->baseUrl($tenant);
        $absolute = [];

        foreach ($urls as $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            if (! str_starts_with($url, 'http')) {
                $url = $base.'/'.ltrim($url, '/');
            }

            if ($host !== null && parse_url($url, PHP_URL_HOST) !== $host) {
                continue;
            }

            $absolute[$url] = $url;
        }

        return array_slice(array_values($absolute), 0, max(1, (int) config('guide.publishing.indexnow.max_urls', 100)));
    }

    private function host(Tenant $tenant): ?string
    {
        $domain = trim((string) $tenant->domain);

        return $domain === '' ? null : $domain;
    }
}
