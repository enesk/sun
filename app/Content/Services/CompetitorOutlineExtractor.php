<?php

declare(strict_types=1);

namespace App\Content\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Robots\RobotsTxt;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Liest die Gliederung einer Wettbewerberseite (#10).
 *
 * Der SerpInsightService braucht von den ersten Suchtreffern mehr, als die
 * SERP-Antwort hergibt: wie die Seite gegliedert ist und wie lang sie ist.
 * Beides beantwortet nur die Seite selbst.
 *
 * Bewusste Grenzen dieses Abrufs:
 *
 *  - robots.txt des Hosts wird vorher geprueft und je Host zwischengespeichert.
 *    Untersagt sie den Abruf, gibt es kein Ergebnis, keinen Notbehelf.
 *  - Timeout 10 s, nur HTML, Groessengrenze aus der Konfiguration.
 *  - Gespeichert werden ausschliesslich Ueberschriften und die Wortzahl —
 *    keine Inhalte, keine Auszuege, kein HTML. Die fremde Seite dient der
 *    Themenabdeckung, nicht als Textquelle.
 */
final class CompetitorOutlineExtractor
{
    private const ROBOTS_CACHE_PREFIX = 'content:serp:robots:';

    /** Bereiche, die keinen Fliesstext enthalten und die Wortzahl verfaelschen. */
    private const NOISE_SELECTORS = ['script', 'style', 'noscript', 'nav', 'header', 'footer', 'aside', 'form', 'template'];

    /**
     * Gliederung und Wortzahl einer Seite. Null, wenn robots.txt den Abruf
     * untersagt, die Seite nicht erreichbar ist oder kein HTML liefert.
     *
     * @return array{h2s: array<int, string>, word_count: int}|null
     */
    public function extract(string $url, ?string $userAgent = null): ?array
    {
        $userAgent = $userAgent ?? $this->defaultUserAgent();

        if (! $this->mayCrawl($url, $userAgent)) {
            Log::info('SERP-Gliederung: Abruf durch robots.txt untersagt.', ['url' => $url]);

            return null;
        }

        $html = $this->fetch($url, $userAgent);

        if ($html === null) {
            return null;
        }

        return $this->parse($html);
    }

    /**
     * @return array{h2s: array<int, string>, word_count: int}
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        $headings = [];

        try {
            $crawler->filter('h2')->each(function (Crawler $node) use (&$headings): void {
                $text = $this->clean($node->text(''));

                // Kurze H2 sind fast immer Widget-Titel ("Newsletter"),
                // sehr lange sind eingebetteter Fliesstext.
                if (mb_strlen($text) < 8 || mb_strlen($text) > 160) {
                    return;
                }

                $headings[mb_strtolower($text)] = $text;
            });
        } catch (Throwable $exception) {
            Log::info('SERP-Gliederung: H2-Auswertung gescheitert.', ['error' => $exception->getMessage()]);
        }

        return [
            'h2s' => array_slice(array_values($headings), 0, max(1, (int) $this->option('max_headings', 25))),
            'word_count' => $this->wordCount($crawler),
        ];
    }

    private function fetch(string $url, string $userAgent): ?string
    {
        try {
            $response = Http::withUserAgent($userAgent)
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->timeout((int) $this->option('timeout', 10))
                ->connectTimeout((int) $this->option('connect_timeout', 5))
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (Throwable $exception) {
            Log::info('SERP-Gliederung: Abruf gescheitert.', ['url' => $url, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! Str::contains((string) $response->header('Content-Type'), 'html', ignoreCase: true)) {
            return null;
        }

        $body = $response->body();
        $maxBytes = (int) $this->option('max_bytes', 2000000);

        if ($body === '' || ($maxBytes > 0 && strlen($body) > $maxBytes)) {
            return null;
        }

        return $body;
    }

    /**
     * Wortzahl des Fliesstextes ohne Navigation, Fusszeile und Skripte.
     */
    private function wordCount(Crawler $crawler): int
    {
        try {
            $crawler->filter(implode(',', self::NOISE_SELECTORS))->each(static function (Crawler $node): void {
                $element = $node->getNode(0);
                $element?->parentNode?->removeChild($element);
            });

            $body = $crawler->filter('body');
            $text = $body->count() > 0 ? $body->text('') : $crawler->text('');
        } catch (Throwable) {
            return 0;
        }

        $text = $this->clean($text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * robots.txt des Hosts, je Host zwischengespeichert. Ohne erreichbare
     * robots.txt gilt der Abruf als erlaubt — genauso handhabt es der
     * AbstractHttpConnector (#7).
     */
    private function mayCrawl(string $url, string $userAgent): bool
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
            function () use ($robotsUrl, $userAgent): string {
                try {
                    $response = Http::withUserAgent($userAgent)
                        ->timeout((int) $this->option('timeout', 10))
                        ->connectTimeout((int) $this->option('connect_timeout', 5))
                        ->get($robotsUrl);

                    return $response->successful() ? $response->body() : '';
                } catch (Throwable) {
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

    /**
     * Produktname des Bots — der Token, gegen den robots.txt-Gruppen gematcht
     * werden.
     */
    private function robotsUserAgent(): string
    {
        return 'SUN-ContentBot';
    }

    /**
     * Ohne Mandantenbezug (der Dienst laeuft auch aus dem Content-Panel)
     * steht die Anwendungs-URL als Kontaktadresse im User-Agent.
     */
    private function defaultUserAgent(): string
    {
        $template = (string) config('content.sources.http.user_agent_template', 'SUN-ContentBot/1.0 (+:bot_url)');
        $path = trim((string) config('content.sources.http.bot_path', 'bot'), '/');

        return str_replace(':bot_url', rtrim((string) config('app.url'), '/').'/'.$path, $template);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.serp_insight.fetch.{$key}", $default);
    }
}
