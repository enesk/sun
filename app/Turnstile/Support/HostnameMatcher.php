<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\Tenant;

/**
 * Welche Hostnames darf ein Token tragen (#4, docs/turnstile.md §3, Punkt 2)?
 *
 * Das ist der Kern der Sicherheit: ein Cloudflare-Widget gilt fuer bis zu zehn
 * Hostnames (Gruppen in §8), also fuer mehrere Portale. Ohne diesen Vergleich
 * liesse sich ein auf fahrschulefinder.de geloestes Token auf
 * elektrikerportal.com einreichen.
 *
 * Erlaubt sind:
 *
 * * die Domain des aktuellen Portals (tenants.domain — in diesem Projekt hat
 *   ein Tenant genau eine Domain, Subdomain-Portale wie
 *   apotheke.firmenfreund.de sind eigene Tenants mit eigener Zeile),
 * * deren www-Variante in beide Richtungen (RedirectWwwToNonWww leitet erst
 *   nach dem Laden um, das Widget kann also auf www gelaufen sein),
 * * der Host der laufenden Anfrage — ohne Tenant-Kontext (zentrale Domain) ist
 *   das die einzige Quelle.
 *
 * Der Vergleich laeuft kleingeschrieben und ohne Port.
 */
class HostnameMatcher
{
    /**
     * @return list<string>
     */
    public static function allowedFor(?Tenant $tenant, ?string $requestHost): array
    {
        $hosts = [];

        foreach ([$tenant?->domain, $requestHost] as $candidate) {
            $host = self::normalise(is_scalar($candidate) ? (string) $candidate : null);

            if ($host === null) {
                continue;
            }

            $hosts[] = $host;
            $hosts[] = str_starts_with($host, 'www.') ? mb_substr($host, 4) : "www.{$host}";
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /** Der Hostname, der im Log als "erwartet" steht. */
    public static function expectedFor(?Tenant $tenant, ?string $requestHost): ?string
    {
        return self::normalise(is_scalar($tenant?->domain) ? (string) $tenant->domain : null)
            ?? self::normalise($requestHost);
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function matches(?string $reported, array $allowed): bool
    {
        $reported = self::normalise($reported);

        if ($reported === null || $allowed === []) {
            return false;
        }

        return in_array($reported, $allowed, true);
    }

    private static function normalise(?string $host): ?string
    {
        $host = mb_strtolower(trim((string) $host));

        if ($host === '') {
            return null;
        }

        // Port abschneiden (localhost:8000), aber IPv6-Klammern stehen lassen.
        if (! str_contains($host, ']') && str_contains($host, ':')) {
            $host = (string) strstr($host, ':', true);
        }

        return rtrim($host, '.') ?: null;
    }
}
