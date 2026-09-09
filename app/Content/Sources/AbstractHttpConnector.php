<?php

declare(strict_types=1);

namespace App\Content\Sources;

use App\Content\Sources\Contracts\SourceConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Spatie\Robots\RobotsTxt;

/**
 * Basis fuer alle Connectoren, die per HTTP abrufen (#7).
 *
 * Setzt einheitlich User-Agent und Timeout und prueft vor jedem Abruf einer
 * HTML- bzw. Feed-Quelle die robots.txt des Hosts. API-Abrufe gegen bezahlte
 * Endpunkte (DataForSEO, GSC) laufen ueber json() ohne robots-Pruefung.
 */
abstract class AbstractHttpConnector implements SourceConnector
{
    private const ROBOTS_CACHE_PREFIX = 'content:sources:robots:';

    protected function http(TenantContext $context): PendingRequest
    {
        return Http::withUserAgent($this->userAgent($context))
            ->timeout((int) config('content.sources.http.timeout', 15))
            ->connectTimeout((int) config('content.sources.http.connect_timeout', 5));
    }

    /**
     * 'SUN-ContentBot/1.0 (+https://<tenant-domain>/bot)'
     */
    protected function userAgent(TenantContext $context): string
    {
        $template = (string) config('content.sources.http.user_agent_template', 'SUN-ContentBot/1.0 (+:bot_url)');

        return str_replace(':bot_url', $context->botUrl(), $template);
    }

    /**
     * Produktname des Bots ohne Versions- und Kontaktzusatz — das ist der
     * Token, gegen den robots.txt-Gruppen gematcht werden.
     */
    protected function robotsUserAgent(): string
    {
        return 'SUN-ContentBot';
    }

    /**
     * Abruf einer HTML-/Feed-Quelle inklusive robots.txt-Pruefung.
     * Gibt null zurueck, wenn robots.txt den Abruf untersagt.
     */
    protected function get(string $url, TenantContext $context, array $query = []): ?Response
    {
        if (! $this->mayCrawl($url, $context)) {
            Log::info('Abruf durch robots.txt untersagt.', ['connector' => $this->key(), 'url' => $url]);

            return null;
        }

        return $this->json($url, $context, $query);
    }

    /**
     * Abruf ohne robots.txt-Pruefung, fuer API-Endpunkte mit Vertrag.
     */
    protected function json(string $url, TenantContext $context, array $query = []): Response
    {
        return $this->http($context)->get($url, $query)->throw();
    }

    /**
     * RSS/Atom-Feed als SimpleXMLElement, null bei robots-Sperre.
     */
    protected function feed(string $url, TenantContext $context): ?SimpleXMLElement
    {
        $response = $this->get($url, $context);

        if ($response === null) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException("Feed '{$url}' liefert kein gueltiges XML.");
        }

        return $xml;
    }

    /**
     * robots.txt des Hosts, je Host zwischengespeichert.
     */
    protected function mayCrawl(string $url, TenantContext $context): bool
    {
        if (! config('content.sources.http.respect_robots', true)) {
            return true;
        }

        $robotsUrl = $this->robotsUrl($url);

        if ($robotsUrl === null) {
            return false;
        }

        $body = Cache::remember(
            self::ROBOTS_CACHE_PREFIX.md5($robotsUrl),
            (int) config('content.sources.http.robots_cache_seconds', 21600),
            function () use ($robotsUrl, $context): string {
                try {
                    $response = $this->http($context)->get($robotsUrl);

                    // Ohne robots.txt (404) gilt der Abruf als erlaubt.
                    return $response->successful() ? $response->body() : '';
                } catch (\Throwable) {
                    return '';
                }
            },
        );

        return (new RobotsTxt($body))->allows($url, $this->robotsUserAgent());
    }

    private function robotsUrl(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.($port !== null ? ":{$port}" : '').'/robots.txt';
    }
}
