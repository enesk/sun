<?php

namespace App\Services;

use App\Constants\TenantConfigConstants;
use App\Models\Portal\Company;

class CompanyUrlService
{
    public const PATTERN_ID_SLUG = 'id-slug';
    public const PATTERN_CITY_ID_SLUG = 'city-id-slug';

    public const PATTERNS = [
        self::PATTERN_ID_SLUG => [
            'label' => '{id}-{firmen-slug}',
            'example' => '/123-malerbetrieb-mueller',
        ],
        self::PATTERN_CITY_ID_SLUG => [
            'label' => '{stadt}/{id}-{firmen-slug}',
            'example' => '/berlin/123-malerbetrieb-mueller',
        ],
    ];

    /**
     * Aktuelles URL-Pattern des Tenants.
     */
    public static function pattern(): string
    {
        $tenant = tenant();
        if (! $tenant) {
            return self::PATTERN_ID_SLUG;
        }

        $pattern = $tenant->getAttribute(TenantConfigConstants::COMPANY_URL_PATTERN);

        if (! $pattern || ! array_key_exists($pattern, self::PATTERNS)) {
            return self::PATTERN_ID_SLUG;
        }

        return $pattern;
    }

    /**
     * Vollständige URL für eine Company (inkl. Domain).
     */
    public static function url(Company $company): string
    {
        $pattern = self::effectivePattern($company);

        return match ($pattern) {
            self::PATTERN_CITY_ID_SLUG => route('portal.companies.show.city', [
                'citySlug' => $company->city->slug,
                'companySlug' => $company->id . '-' . $company->slug,
            ]),
            default => route('portal.companies.show', [
                'companySlug' => $company->id . '-' . $company->slug,
            ]),
        };
    }

    /**
     * Relativer Pfad für eine Company (ohne Domain, für Sitemap etc.).
     */
    public static function path(Company $company): string
    {
        $companySlug = $company->id . '-' . $company->slug;
        $pattern = self::effectivePattern($company);

        return match ($pattern) {
            self::PATTERN_CITY_ID_SLUG => '/' . $company->city->slug . '/' . $companySlug,
            default => '/' . $companySlug,
        };
    }

    /**
     * Effektives Pattern für eine Company.
     * Firmen ohne City fallen auf id-slug zurück (kein /unbekannt/ in URLs).
     */
    public static function effectivePattern(Company $company): string
    {
        $pattern = self::pattern();

        if ($pattern === self::PATTERN_CITY_ID_SLUG && ! $company->city?->slug) {
            return self::PATTERN_ID_SLUG;
        }

        return $pattern;
    }

    /**
     * Prüft ob eine URL dem kanonischen Pattern des Tenants entspricht.
     * Gibt die kanonische URL zurück falls ein Redirect nötig ist, sonst null.
     */
    public static function canonicalRedirect(Company $company, string $pattern, ?string $citySlug = null, string $companySlug = ''): ?string
    {
        $effectivePattern = self::effectivePattern($company);
        $expectedCompanySlug = $company->id . '-' . $company->slug;

        // Falsches Pattern → Redirect zur kanonischen URL
        if ($pattern !== $effectivePattern) {
            return self::url($company);
        }

        // Richtiges Pattern, aber falscher Slug → Redirect
        if ($companySlug !== $expectedCompanySlug) {
            return self::url($company);
        }

        // Bei city-slug Pattern: auch City-Slug prüfen
        if ($effectivePattern === self::PATTERN_CITY_ID_SLUG) {
            $expectedCitySlug = $company->city->slug;
            if ($citySlug !== $expectedCitySlug) {
                return self::url($company);
            }
        }

        return null;
    }
}
