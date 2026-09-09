<?php

namespace App\Services;

/**
 * Baut die robots.txt eines Mandanten (#18).
 *
 * Einzige Quelle fuer den Inhalt: sowohl der Sitemap-Job, der die Datei nach
 * storage/app/public/robots.txt schreibt, als auch die Fallback-Route nutzen
 * diesen Dienst. Die bestehenden Regeln des Portals (Allow /, die Disallow-
 * Liste, Sitemap-Zeile) bleiben unveraendert; die KI-Crawler bekommen eine
 * eigene Gruppe mit denselben Sperren und einer ausdruecklichen Erlaubnis.
 */
class RobotsTxtBuilder
{
    /**
     * Bereiche, die kein Crawler indexieren soll — Login, Verwaltung und die
     * internen Firmenprofil-Ansichten.
     *
     * @var array<int, string>
     */
    public const DEFAULT_DISALLOW = [
        '/firmenprofil/',
        '/verwaltung/',
        '/login',
        '/register',
    ];

    /**
     * KI-Crawler mit ausdruecklicher Erlaubnis. Google-Extended und
     * Applebot-Extended steuern nur die KI-Nutzung, nicht das normale
     * Crawling — sie stehen deshalb bewusst in derselben Gruppe.
     *
     * @var array<int, string>
     */
    public const AI_CRAWLERS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'PerplexityBot',
        'Perplexity-User',
        'Google-Extended',
        'CCBot',
        'Applebot-Extended',
    ];

    /**
     * @param  array<int, string>  $disallow
     */
    public function build(string $baseUrl, array $disallow = self::DEFAULT_DISALLOW): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $lines = ['User-agent: *', 'Allow: /'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = '# KI-Crawler und KI-Suchdienste duerfen die Inhalte dieses Portals nutzen.';
        $lines[] = '# Kurzindex: '.$baseUrl.'/llms.txt — Volltexte: '.$baseUrl.'/llms-full.txt';

        foreach (self::AI_CRAWLERS as $crawler) {
            $lines[] = 'User-agent: '.$crawler;
        }

        $lines[] = 'Allow: /';

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$baseUrl.'/sitemap.xml';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
