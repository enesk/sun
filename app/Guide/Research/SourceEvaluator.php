<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Enums\TrustLevel;
use App\Guide\Models\TenantGuideSetting;

/**
 * Stuft Quellen des Ratgebersystems ein (#8).
 *
 * Reihenfolge: Blacklist (samt Subdomains) -> Quelle wird verworfen;
 * Whitelist des Portals (samt Subdomains) -> trust_level des Eintrags;
 * Domain-Muster aus config('guide.research.trust_patterns'); sonst 'other'.
 * Die Einstufung, die das Modell selbst mitliefert, zaehlt nicht — sonst
 * koennte jede Seite als 'press' durchgehen.
 *
 * Nur official, trade und press belegen einen Fakt (supportsFacts()).
 */
final class SourceEvaluator
{
    /** @var array<string, array{domain: string, publisher?: string, trust_level?: string}> */
    private array $whitelist = [];

    /** @var array<int, string> */
    private array $blacklist = [];

    /**
     * @param  array<int, mixed>  $whitelist  Eintraege {domain, publisher, type, trust_level}
     * @param  array<int, mixed>  $blacklist  Eintraege {domain, reason} oder reine Domains
     */
    public function __construct(array $whitelist = [], array $blacklist = [])
    {
        foreach ($whitelist as $entry) {
            $domain = self::normalizeDomain(is_array($entry) ? ($entry['domain'] ?? '') : $entry);

            if ($domain !== '') {
                $this->whitelist[$domain] = (is_array($entry) ? $entry : []) + ['domain' => $domain];
            }
        }

        foreach ($blacklist as $entry) {
            $domain = self::normalizeDomain(is_array($entry) ? ($entry['domain'] ?? '') : $entry);

            if ($domain !== '') {
                $this->blacklist[] = $domain;
            }
        }

        $this->blacklist = array_values(array_unique($this->blacklist));
    }

    public static function forSetting(TenantGuideSetting $setting): self
    {
        return new self(
            (array) ($setting->source_whitelist_json ?? []),
            (array) ($setting->source_blacklist_json ?? []),
        );
    }

    public function isBlacklisted(string $url): bool
    {
        $host = self::host($url);

        return $host === null || $this->matchDomain($host, $this->blacklist) !== null;
    }

    /**
     * Vertrauensstufe einer URL; null = Blacklist oder keine gueltige URL.
     */
    public function trustLevel(string $url): ?TrustLevel
    {
        $host = self::host($url);

        if ($host === null || $this->matchDomain($host, $this->blacklist) !== null) {
            return null;
        }

        $listed = $this->matchDomain($host, array_keys($this->whitelist));

        if ($listed !== null) {
            return TrustLevel::tryFrom((string) ($this->whitelist[$listed]['trust_level'] ?? '')) ?? TrustLevel::PRESS;
        }

        foreach ((array) config('guide.research.trust_patterns', []) as $level => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if (preg_match((string) $pattern, $host) === 1) {
                    return TrustLevel::tryFrom((string) $level) ?? TrustLevel::OTHER;
                }
            }
        }

        return TrustLevel::OTHER;
    }

    /**
     * Herausgeber laut Whitelist, falls die Domain dort steht.
     */
    public function publisher(string $url): ?string
    {
        $host = self::host($url);
        $listed = $host === null ? null : $this->matchDomain($host, array_keys($this->whitelist));

        return $listed === null ? null : ($this->whitelist[$listed]['publisher'] ?? null);
    }

    public static function supportsFacts(?TrustLevel $level): bool
    {
        return in_array($level, [TrustLevel::OFFICIAL, TrustLevel::TRADE, TrustLevel::PRESS], true);
    }

    /**
     * Rangfolge fuer Konflikte: official > trade > press > other.
     */
    public static function rank(?TrustLevel $level): int
    {
        return match ($level) {
            TrustLevel::OFFICIAL => 3,
            TrustLevel::TRADE => 2,
            TrustLevel::PRESS => 1,
            default => 0,
        };
    }

    /**
     * Web-Search-Filter nach Ticket #8: allowed_domains nur bei einer
     * Whitelist mit mindestens guide.research.allowed_domains_min Domains,
     * sonst ausschliesslich blocked_domains. Beide zugleich lehnt die API ab.
     *
     * @return array{allowed: array<int, string>, blocked: array<int, string>}
     */
    public function searchFilter(): array
    {
        $allowed = array_keys($this->whitelist);

        if (count($allowed) >= (int) config('guide.research.allowed_domains_min', 10)) {
            // Eine gesperrte Subdomain einer erlaubten Domain faengt trustLevel() ab.
            return ['allowed' => array_values(array_diff($allowed, $this->blacklist)), 'blocked' => []];
        }

        return ['allowed' => [], 'blocked' => $this->blacklist];
    }

    /**
     * Quellenhinweis fuer den Prompt ({{sources}}).
     *
     * @param  array<int, string>  $knownUrls  bereits bekannte guide_sources
     */
    public function promptText(array $knownUrls = []): string
    {
        $lines = [];

        if ($this->whitelist !== []) {
            $lines[] = 'Bevorzugt: '.implode(', ', array_map(
                fn (array $entry): string => isset($entry['publisher']) && $entry['publisher'] !== ''
                    ? "{$entry['domain']} ({$entry['publisher']})"
                    : $entry['domain'],
                array_values($this->whitelist),
            ));
        }

        if ($this->blacklist !== []) {
            $lines[] = 'Verboten: '.implode(', ', $this->blacklist);
        }

        if ($knownUrls !== []) {
            $lines[] = 'Bisher verwendete Quellen: '.implode(', ', $knownUrls);
        }

        return $lines === [] ? 'keine Vorgaben' : implode("\n", $lines);
    }

    public static function host(string $url): ?string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return self::normalizeDomain($host);
    }

    private static function normalizeDomain(mixed $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        return (string) preg_replace('/^www\./', '', rtrim($domain, '.'));
    }

    /**
     * @param  array<int, string>  $domains
     */
    private function matchDomain(string $host, array $domains): ?string
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                return $domain;
            }
        }

        return null;
    }
}
