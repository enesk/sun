<?php

declare(strict_types=1);

namespace App\Content\Providers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * IndexNow-Ping (#21).
 *
 * Ein POST an api.indexnow.org erreicht Bing, Yandex und Seznam gleichzeitig.
 * Google nimmt IndexNow nicht an und die Google Indexing API ist auf
 * JobPosting und BroadcastEvent beschraenkt — fuer Ratgeber bleibt es dort
 * bei lastmod in der Sitemap.
 *
 * Der Schluessel wird nirgends gespeichert. Er wird je Mandant
 * deterministisch aus Tenant-Schluessel und APP_KEY abgeleitet: derselbe
 * Mandant bekommt immer denselben Schluessel, zwei Mandanten nie denselben,
 * und die Datei unter /<key>.txt kann ohne Datenhaltung ausgeliefert werden
 * (IndexNowKeyController). Der Schluessel ist ohnehin oeffentlich — er steht
 * im Ping und in der Datei.
 *
 * Grundregel: Ein Ping darf eine Veroeffentlichung nie aufhalten. Jeder
 * Fehler wird protokolliert und geschluckt.
 */
final class IndexNowClient
{
    public const PROVIDER = 'indexnow';

    public function isEnabled(): bool
    {
        return (bool) config('content.publishing.indexnow.enabled', true);
    }

    /**
     * Schluessel des Mandanten. Nur [a-z0-9], damit er als Dateiname und als
     * Pfadsegment unauffaellig ist.
     */
    public function key(Tenant $tenant): string
    {
        $length = max(8, min(128, (int) config('content.publishing.indexnow.key_length', 32)));

        $hash = hash_hmac('sha256', 'indexnow|'.$tenant->getTenantKey(), (string) config('app.key'));

        return substr($hash, 0, $length);
    }

    public function keyLocation(Tenant $tenant): ?string
    {
        $host = $this->host($tenant);

        return $host === null ? null : $this->baseUrl($tenant).'/'.$this->key($tenant).'.txt';
    }

    /**
     * Meldet URLs an IndexNow. Rueckgabe ist der Protokolleintrag, den der
     * Publisher an den Entwurf haengt.
     *
     * @param  array<int, string>  $urls
     * @return array{status: string, http_status: int|null, urls: array<int, string>, key_location: string|null, message: string|null, pinged_at: string}
     */
    public function submit(Tenant $tenant, array $urls): array
    {
        $urls = $this->normalize($tenant, $urls);
        $host = $this->host($tenant);
        $result = [
            'status' => 'skipped',
            'http_status' => null,
            'urls' => $urls,
            'key_location' => $this->keyLocation($tenant),
            'message' => null,
            'pinged_at' => now()->toIso8601String(),
        ];

        if (! $this->isEnabled()) {
            $result['message'] = 'IndexNow ist abgeschaltet (content.publishing.indexnow.enabled).';

            return $result;
        }

        if ($host === null || $urls === []) {
            $result['message'] = $host === null ? 'Der Mandant hat keine Domain.' : 'Keine meldbaren URLs.';

            return $result;
        }

        try {
            $response = Http::timeout(max(1, (int) config('content.publishing.indexnow.timeout', 15)))
                ->acceptJson()
                ->asJson()
                ->post((string) config('content.publishing.indexnow.endpoint', 'https://api.indexnow.org/indexnow'), [
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

        $context = [
            'tenant_id' => $tenant->getKey(),
            'host' => $host,
            'urls' => count($urls),
            'http_status' => $result['http_status'],
            'message' => $result['message'],
        ];

        $result['status'] === 'ok'
            ? Log::info('IndexNow gemeldet.', $context)
            : Log::warning('IndexNow-Meldung fehlgeschlagen.', $context);

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
        $max = max(1, (int) config('content.publishing.indexnow.max_urls', 100));

        $absolute = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            if (! str_starts_with($url, 'http')) {
                $url = $base.'/'.ltrim($url, '/');
            }

            if ($host !== null && ! str_contains((string) parse_url($url, PHP_URL_HOST), $host)) {
                continue;
            }

            $absolute[$url] = $url;
        }

        return array_slice(array_values($absolute), 0, $max);
    }

    private function host(Tenant $tenant): ?string
    {
        $domain = trim((string) $tenant->domain);

        return $domain === '' ? null : $domain;
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
}
