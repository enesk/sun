<?php

declare(strict_types=1);

namespace App\Content\Quality;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Erreichbarkeit der externen Links eines Entwurfs (#15).
 *
 * HEAD-Request je Ziel mit 5 Sekunden Timeout. 4xx und 5xx sind blockierend:
 * ein Ratgeber, der auf eine geloeschte Foerderrichtlinie verweist, ist
 * schlechter als einer ohne Quelle. Eine Zeitueberschreitung dagegen sagt
 * nichts ueber das Ziel aus und ist deshalb nur eine Warnung — sonst haengt
 * die Freigabe an der Tagesform eines fremden Servers.
 *
 * Ergebnisse liegen einen Tag im Cache. Mehrere Artikel zitieren dieselben
 * Quellen; ohne Cache pruefte das Gate dieselbe Foerderdatenbank 40-mal.
 */
final class LinkChecker
{
    private const CACHE_PREFIX = 'content:linkcheck:';

    /**
     * @param  array<int, string>  $urls
     * @return array<int, array{url: string, status: string, code: int|null, ok: bool, message: string}>
     */
    public function check(array $urls): array
    {
        if (! (bool) config('content_seo_rules.links.enabled', true)) {
            return [];
        }

        $results = [];

        foreach (array_values(array_unique($urls)) as $url) {
            if (! is_string($url) || ! str_starts_with($url, 'http')) {
                continue;
            }

            $results[] = $this->checkOne($url);
        }

        return $results;
    }

    /**
     * Nur die Ziele, die tatsaechlich einen Fehlercode geliefert haben.
     *
     * @param  array<int, array{url: string, status: string, code: int|null, ok: bool, message: string}>  $results
     * @return array<int, array{url: string, status: string, code: int|null, ok: bool, message: string}>
     */
    public function broken(array $results): array
    {
        return array_values(array_filter(
            $results,
            static fn (array $result): bool => $result['status'] === 'broken',
        ));
    }

    /**
     * @return array{url: string, status: string, code: int|null, ok: bool, message: string}
     */
    private function checkOne(string $url): array
    {
        $seconds = max(60, (int) config('content_seo_rules.links.cache_seconds', 86400));

        return Cache::remember(
            self::CACHE_PREFIX.sha1($url),
            $seconds,
            fn (): array => $this->request($url),
        );
    }

    /**
     * @return array{url: string, status: string, code: int|null, ok: bool, message: string}
     */
    private function request(string $url): array
    {
        $timeout = max(1, (int) config('content_seo_rules.links.timeout', 5));
        $connect = max(1, (int) config('content_seo_rules.links.connect_timeout', 3));
        $retryOn = (array) config('content_seo_rules.links.retry_with_get_on', [403, 405, 501]);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->withUserAgent($this->userAgent())
                ->head($url);

            $code = $response->status();

            // Manche Server kennen HEAD nicht oder sperren es aus. Ein GET
            // klaert, ob die Seite existiert, bevor der Link als kaputt gilt.
            if (in_array($code, array_map('intval', $retryOn), true)) {
                $code = Http::timeout($timeout)
                    ->connectTimeout($connect)
                    ->withUserAgent($this->userAgent())
                    ->get($url)
                    ->status();
            }

            if ($code < 400) {
                return [
                    'url' => $url,
                    'status' => 'ok',
                    'code' => $code,
                    'ok' => true,
                    'message' => "HTTP {$code}",
                ];
            }

            return [
                'url' => $url,
                'status' => 'broken',
                'code' => $code,
                'ok' => false,
                'message' => __('Ziel antwortet mit HTTP :code.', ['code' => $code]),
            ];
        } catch (Throwable $exception) {
            return [
                'url' => $url,
                'status' => 'unreachable',
                'code' => null,
                'ok' => false,
                'message' => __('Ziel nicht erreichbar (:reason).', [
                    'reason' => str($exception->getMessage())->limit(120)->toString(),
                ]),
            ];
        }
    }

    private function userAgent(): string
    {
        $template = (string) config('content.sources.http.user_agent_template', 'SUN-ContentBot/1.0 (+:bot_url)');

        return str_replace(':bot_url', url((string) config('content.sources.http.bot_path', 'bot')), $template);
    }
}
