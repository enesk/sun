<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Branchenpaket eines Portals ueber die Vorgabe des Themes legen (#44).
 *
 * Die Vorgabe (config/themes/sun-v2.php) ist auf Elektriker geschrieben.
 * Portale anderer Branchen bringen ein Paket unter config/branches/<slug>.php
 * mit, das nur die abweichenden Schluessel enthaelt. Aufgerufen wird das
 * einmal je Anfrage aus der Middleware ResolveTheme, bevor eine View laeuft.
 */
final class BranchConfig
{
    public const TENANT_ATTRIBUTE = 'sun_v2_branch';

    private const CONFIG_KEY = 'themes.sun-v2';

    /**
     * Slug des Branchenpakets, oder null fuer die Vorgabe.
     */
    public static function resolve(?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        $override = $tenant->getAttribute(self::TENANT_ATTRIBUTE);

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $domain = Str::lower((string) $tenant->getAttribute('domain'));
        $domains = (array) config('themes.sun-v2-branches.domains', []);

        if (isset($domains[$domain])) {
            return (string) $domains[$domain];
        }

        $slug = Str::slug((string) $tenant->getAttribute('name')).'-'.Str::slug($domain);

        foreach ((array) config('themes.sun-v2-branches.needles', []) as $needle => $branch) {
            if (str_contains($slug, (string) $needle)) {
                return (string) $branch;
            }
        }

        $default = config('themes.sun-v2-branches.default');

        return is_string($default) && $default !== '' ? $default : null;
    }

    /**
     * Paket des Portals in die Theme-Config mischen. Ohne Paket bleibt alles,
     * wie es ist — die Vorgabe gilt dann unveraendert.
     */
    public static function apply(?Tenant $tenant): void
    {
        $branch = self::resolve($tenant);

        if ($branch === null) {
            return;
        }

        $package = config("branches.{$branch}");

        if (! is_array($package) || $package === []) {
            return;
        }

        config([self::CONFIG_KEY => self::merge((array) config(self::CONFIG_KEY, []), $package)]);
    }

    /**
     * Wie array_replace_recursive, aber Listen werden ersetzt statt
     * verschmolzen: eine Branche mit sechs Leistungskacheln soll nicht die
     * restlichen zwei der Vorgabe erben.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            $existing = $base[$key] ?? null;

            $base[$key] = is_array($value) && is_array($existing) && ! array_is_list($value)
                ? self::merge($existing, $value)
                : $value;
        }

        return $base;
    }
}
