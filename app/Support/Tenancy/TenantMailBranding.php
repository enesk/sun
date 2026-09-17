<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;

/**
 * Markenfarbe, Portalname und Portal-URLs fuer die Premium-Mails (#16).
 *
 * Mails laufen ueber die Queue bzw. tenants:run, dort zeigt url() nicht
 * zuverlaessig auf die Portal-Domain; deshalb wird die Spalte tenants.domain
 * verwendet. Farbregel wie in layouts.sun/layouts.panel (sun-v2).
 */
final class TenantMailBranding
{
    public function __construct(private readonly ?Tenant $tenant) {}

    /**
     * Ausdruecklich uebergebener Tenant (z.B. Mails aus dem Central-Kontext),
     * sonst der initialisierte.
     */
    public static function for(?Tenant $tenant = null): self
    {
        $tenant ??= tenant();

        return new self($tenant instanceof Tenant ? $tenant : null);
    }

    public static function current(): self
    {
        return self::for();
    }

    public function brandColor(): string
    {
        $color = (string) ($this->tenant?->getAttribute(TenantConfigConstants::PRIMARY_COLOR) ?? '');
        $projectDefault = (string) (TenantConfigConstants::DEFAULTS[TenantConfigConstants::PRIMARY_COLOR] ?? '');

        if (! preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color) || strcasecmp($color, $projectDefault) === 0) {
            return (string) config('themes.sun-v2.default_brand_color', '#1d4ed8');
        }

        return $color;
    }

    public function portalName(): string
    {
        if ($this->tenant === null) {
            // Ohne Portal-Kontext der Plattformname, nie der App-Name (SaaSykit)
            return (string) config('app.platform_name', config('app.name'));
        }

        return $this->tenant->terms['portal'];
    }

    public function url(string $path = '/'): string
    {
        $domain = $this->tenant?->getAttribute('domain');
        $domain = is_string($domain) && $domain !== '' ? $domain : null;
        $path = '/'.ltrim($path, '/');

        return $domain !== null ? "https://{$domain}{$path}" : url($path);
    }
}
