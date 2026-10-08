<?php

declare(strict_types=1);

namespace App\AntiSpam;

use App\AntiSpam\Support\AntiSpamConfig;
use Illuminate\Support\Str;

/**
 * Sperrliste der Wegwerf-E-Mail-Domains (#8).
 *
 * Drei Quellen, in dieser Reihenfolge:
 *
 *   1. resources/antispam/disposable-domains.txt — gepflegt im Repo,
 *      aktualisiert von `antispam:refresh-domains`
 *   2. tenants.data `antispam.disposable_domains` — Ergaenzungen des Portals
 *   3. tenants.data `antispam.allowed_domains` — nimmt eine Domain wieder
 *      heraus, falls eine Liste zu grob ist (schlaegt 1 und 2)
 *
 * Mustervergleich:
 *   mailinator.com   trifft mailinator.com und jede Subdomain davon
 *   tempmail.*       trifft tempmail.de, tempmail.net (ein Label)
 *   *.tempmail.com   trifft a.tempmail.com, nicht tempmail.com
 *
 * Die Datei wird je Request einmal gelesen und in einer statischen Map
 * gehalten — kein Cache-Store: die Liste ist wenige Kilobyte gross, und ein
 * Cache waere eine weitere Stelle, die nach einer Aenderung geleert werden
 * muss (vgl. docs/turnstile.md zum Cache im Portalkontext).
 */
class DisposableDomainList
{
    /** @var array<string, list<string>> */
    private static array $files = [];

    /** Steht die Domain der Adresse auf der Sperrliste? */
    public function blocks(?string $email): bool
    {
        $domain = self::domainOf($email);

        if ($domain === null) {
            return false;
        }

        if ($this->matchesAny($domain, AntiSpamConfig::list('allowed_domains'))) {
            return false;
        }

        return $this->matchesAny($domain, $this->patterns());
    }

    /**
     * Alle wirksamen Muster: Datei plus Portal-Ergaenzungen.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        return array_values(array_unique([
            ...self::fromFile($this->path()),
            ...array_map(
                static fn (string $entry): string => Str::lower($entry),
                AntiSpamConfig::list('disposable_domains'),
            ),
        ]));
    }

    public function path(): string
    {
        $relative = (string) AntiSpamConfig::get('disposable.list_path', 'antispam/disposable-domains.txt');

        return resource_path($relative);
    }

    /** Fuer den Pflege-Command und Filament (#9): wie viele Muster sind aktiv? */
    public function count(): int
    {
        return count($this->patterns());
    }

    public static function flush(): void
    {
        self::$files = [];
    }

    /**
     * Domain einer Adresse, klein geschrieben, ohne abschliessenden Punkt.
     */
    public static function domainOf(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = trim(mb_substr($email, $at + 1), " \t.");

        return $domain === '' ? null : $domain;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $domain, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $domain, string $pattern): bool
    {
        $pattern = Str::lower(trim($pattern, " \t."));

        if ($pattern === '') {
            return false;
        }

        if (str_contains($pattern, '*')) {
            // `*` steht fuer genau ein Label, deshalb [^.] statt .*
            $regex = '/^'.str_replace('\*', '[^.]+', preg_quote($pattern, '/')).'$/';

            return preg_match($regex, $domain) === 1;
        }

        // Eine gelistete Domain sperrt ihre Subdomains mit.
        return $domain === $pattern || str_ends_with($domain, '.'.$pattern);
    }

    /**
     * @return list<string>
     */
    private static function fromFile(string $path): array
    {
        if (array_key_exists($path, self::$files)) {
            return self::$files[$path];
        }

        if (! is_file($path) || ! is_readable($path)) {
            return self::$files[$path] = [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $patterns = [];

        foreach ($lines as $line) {
            $line = Str::lower(trim($line));

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $patterns[] = $line;
        }

        return self::$files[$path] = array_values(array_unique($patterns));
    }
}
