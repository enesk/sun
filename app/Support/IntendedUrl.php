<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Pruefung fuer Weiterleitungsziele nach Login/Registrierung (Security-Review
 * Ratgeber S2): Die Intended-URL stammt aus dem Referer und darf nur auf den
 * aktuellen Host, eine Zentraldomain oder eine Tenant-Domain zeigen.
 */
final class IntendedUrl
{
    public static function isOwn(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ! in_array(mb_strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = self::normalize($parts['host'] ?? '');

        if ($host === '') {
            return false;
        }

        $known = array_map(self::normalize(...), [request()->getHost(), ...(array) config('tenancy.central_domains', [])]);

        if (in_array($host, $known, true)) {
            return true;
        }

        return Tenant::query()->whereIn('domain', [$host, "www.{$host}"])->exists();
    }

    /**
     * Intended-URL aus dem Referer setzen, fremde Hosts verwerfen.
     */
    public static function rememberPrevious(): void
    {
        $previous = url()->previous();

        if (self::isOwn($previous)) {
            redirect()->setIntendedUrl($previous);
        }
    }

    private static function normalize(string $host): string
    {
        $host = mb_strtolower(trim($host), 'UTF-8');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
