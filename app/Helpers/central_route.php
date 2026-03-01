<?php

if (! function_exists('central_route')) {
    /**
     * Generate a URL for a named route on the central domain.
     *
     * In a multi-tenant setup, route() generates URLs for the current
     * tenant domain. Checkout and payment routes live on the central
     * domain only. This helper rewrites the host to the central domain.
     */
    function central_route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $url = route($name, $parameters, $absolute);

        $centralDomain = config('tenancy.central_domains.0');

        if (! $centralDomain) {
            return $url;
        }

        $parsed = parse_url($url);

        if (! isset($parsed['host']) || $parsed['host'] === $centralDomain) {
            return $url;
        }

        // Replace the tenant domain with the central domain
        $scheme = $parsed['scheme'] ?? 'https';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return "{$scheme}://{$centralDomain}{$port}{$path}{$query}";
    }
}
