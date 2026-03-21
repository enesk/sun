<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenantConfigConstants;
use App\Jobs\GenerateTenantSitemapJob;
use App\Models\Portal\Category;
use App\Models\Portal\Company;
use App\Services\CompanyUrlService;
use App\Services\TenantBrandingService;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class GeneralSettingsForm extends Component
{
    // Workspace
    public string $tenantName = '';
    public string $addressLine1 = '';
    public string $addressLine2 = '';
    public string $city = '';
    public string $state = '';
    public string $zip = '';
    public string $countryCode = 'DE';
    public string $phone = '';
    public string $taxNumber = '';

    // Contact
    public string $contactEmail = '';
    public string $contactPhone = '';
    public string $contactAddress = '';

    // Social Media
    public string $socialFacebook = '';
    public string $socialInstagram = '';
    public string $socialTwitter = '';
    public string $socialLinkedin = '';

    // Analytics
    public string $googleAnalyticsId = '';
    public string $googleTagManagerId = '';

    // Features
    public bool $reviewsEnabled = true;
    public bool $registrationEnabled = true;
    public bool $premiumListingsEnabled = false;

    // URL-Konfiguration
    public string $companyUrlPattern = 'id-slug';

    // SEO
    public string $siteTitle = '';
    public string $siteDescription = '';
    public string $metaKeywords = '';

    // Footer
    public string $footerText = '';

    // UI State
    public bool $saved = false;
    public string $activeTab = 'workspace';

    // Sitemap Progress
    public ?array $sitemapProgress = null;

    public function mount(): void
    {
        $tenant = tenant();
        $branding = app(TenantBrandingService::class);

        // Workspace
        $this->tenantName = $tenant->name ?? '';
        $address = $tenant->address()->first();
        if ($address) {
            $this->addressLine1 = $address->address_line_1 ?? '';
            $this->addressLine2 = $address->address_line_2 ?? '';
            $this->city = $address->city ?? '';
            $this->state = $address->state ?? '';
            $this->zip = $address->zip ?? '';
            $this->countryCode = $address->country_code ?? 'DE';
            $this->phone = $address->phone ?? '';
            $this->taxNumber = $address->tax_number ?? '';
        }

        // Contact
        $this->contactEmail = $branding->get($tenant, TenantConfigConstants::CONTACT_EMAIL) ?? '';
        $this->contactPhone = $branding->get($tenant, TenantConfigConstants::CONTACT_PHONE) ?? '';
        $this->contactAddress = $branding->get($tenant, TenantConfigConstants::CONTACT_ADDRESS) ?? '';

        // Social
        $this->socialFacebook = $branding->get($tenant, TenantConfigConstants::SOCIAL_FACEBOOK) ?? '';
        $this->socialInstagram = $branding->get($tenant, TenantConfigConstants::SOCIAL_INSTAGRAM) ?? '';
        $this->socialTwitter = $branding->get($tenant, TenantConfigConstants::SOCIAL_TWITTER) ?? '';
        $this->socialLinkedin = $branding->get($tenant, TenantConfigConstants::SOCIAL_LINKEDIN) ?? '';

        // Analytics
        $this->googleAnalyticsId = $branding->get($tenant, TenantConfigConstants::GOOGLE_ANALYTICS_ID) ?? '';
        $this->googleTagManagerId = $branding->get($tenant, TenantConfigConstants::GOOGLE_TAG_MANAGER_ID) ?? '';

        // Features
        $this->reviewsEnabled = (bool) $branding->get($tenant, TenantConfigConstants::REVIEWS_ENABLED, true);
        $this->registrationEnabled = (bool) $branding->get($tenant, TenantConfigConstants::REGISTRATION_ENABLED, true);
        $this->premiumListingsEnabled = (bool) $branding->get($tenant, TenantConfigConstants::PREMIUM_LISTINGS_ENABLED, false);

        // URL-Konfiguration
        $this->companyUrlPattern = $branding->get($tenant, TenantConfigConstants::COMPANY_URL_PATTERN) ?? 'id-slug';

        // SEO
        $this->siteTitle = $branding->get($tenant, TenantConfigConstants::SITE_TITLE) ?? '';
        $this->siteDescription = $branding->get($tenant, TenantConfigConstants::SITE_DESCRIPTION) ?? '';
        $this->metaKeywords = $branding->get($tenant, TenantConfigConstants::META_KEYWORDS) ?? '';

        // Footer
        $this->footerText = $branding->get($tenant, TenantConfigConstants::FOOTER_TEXT) ?? '';

        // Check for running sitemap generation
        $progress = Cache::get(GenerateTenantSitemapJob::cacheKey($tenant->id));
        if ($progress && $progress['status'] === 'running') {
            $this->sitemapProgress = $progress;
        }
    }

    protected function rules(): array
    {
        return [
            'tenantName' => ['required', 'string', 'min:2', 'max:255'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'countryCode' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:50'],
            'taxNumber' => ['nullable', 'string', 'max:50'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'contactAddress' => ['nullable', 'string', 'max:500'],
            'socialFacebook' => ['nullable', 'url', 'max:255'],
            'socialInstagram' => ['nullable', 'url', 'max:255'],
            'socialTwitter' => ['nullable', 'url', 'max:255'],
            'socialLinkedin' => ['nullable', 'url', 'max:255'],
            'googleAnalyticsId' => ['nullable', 'string', 'max:50', 'regex:/^(G-|UA-|GTM-)?[A-Z0-9\-]+$/i'],
            'googleTagManagerId' => ['nullable', 'string', 'max:50', 'regex:/^GTM-[A-Z0-9]+$/i'],
            'reviewsEnabled' => ['boolean'],
            'registrationEnabled' => ['boolean'],
            'premiumListingsEnabled' => ['boolean'],
            'companyUrlPattern' => ['required', 'string', 'in:' . implode(',', array_keys(CompanyUrlService::PATTERNS))],
            'siteTitle' => ['nullable', 'string', 'max:255'],
            'siteDescription' => ['nullable', 'string', 'max:500'],
            'metaKeywords' => ['nullable', 'string', 'max:500'],
            'footerText' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $branding = app(TenantBrandingService::class);
        $tenantService = app(TenantService::class);

        // Track URL-Pattern-Wechsel für Sitemap-Regenerierung
        $previousPattern = $branding->get($tenant, TenantConfigConstants::COMPANY_URL_PATTERN) ?? 'id-slug';
        $patternChanged = $previousPattern !== $this->companyUrlPattern;

        // Workspace name
        $tenantService->updateTenantName($tenant, $this->tenantName);

        // Address
        $addressData = [
            'address_line_1' => $this->addressLine1 ?: null,
            'address_line_2' => $this->addressLine2 ?: null,
            'city' => $this->city ?: null,
            'state' => $this->state ?: null,
            'zip' => $this->zip ?: null,
            'country_code' => $this->countryCode ?: null,
            'phone' => $this->phone ?: null,
            'tax_number' => $this->taxNumber ?: null,
        ];

        $address = $tenant->address()->first();
        if ($address) {
            $address->update($addressData);
        } else {
            $tenant->address()->create($addressData);
        }

        // Tenant config — batch update
        $branding->setMany($tenant, [
            TenantConfigConstants::CONTACT_EMAIL => $this->contactEmail ?: null,
            TenantConfigConstants::CONTACT_PHONE => $this->contactPhone ?: null,
            TenantConfigConstants::CONTACT_ADDRESS => $this->contactAddress ?: null,
            TenantConfigConstants::SOCIAL_FACEBOOK => $this->socialFacebook ?: null,
            TenantConfigConstants::SOCIAL_INSTAGRAM => $this->socialInstagram ?: null,
            TenantConfigConstants::SOCIAL_TWITTER => $this->socialTwitter ?: null,
            TenantConfigConstants::SOCIAL_LINKEDIN => $this->socialLinkedin ?: null,
            TenantConfigConstants::GOOGLE_ANALYTICS_ID => $this->googleAnalyticsId ?: null,
            TenantConfigConstants::GOOGLE_TAG_MANAGER_ID => $this->googleTagManagerId ?: null,
            TenantConfigConstants::REVIEWS_ENABLED => $this->reviewsEnabled,
            TenantConfigConstants::REGISTRATION_ENABLED => $this->registrationEnabled,
            TenantConfigConstants::PREMIUM_LISTINGS_ENABLED => $this->premiumListingsEnabled,
            TenantConfigConstants::COMPANY_URL_PATTERN => $this->companyUrlPattern,
            TenantConfigConstants::SITE_TITLE => $this->siteTitle ?: null,
            TenantConfigConstants::SITE_DESCRIPTION => $this->siteDescription ?: null,
            TenantConfigConstants::META_KEYWORDS => $this->metaKeywords ?: null,
            TenantConfigConstants::FOOTER_TEXT => $this->footerText ?: null,
        ]);

        // Sitemap regenerieren bei URL-Pattern-Wechsel
        if ($patternChanged && app()->environment('production')) {
            GenerateTenantSitemapJob::dispatch($tenant->id);
        }

        $this->saved = true;
        $this->dispatch('toast', type: 'success', message: 'Einstellungen gespeichert');
    }

    public function generateSitemap(): void
    {
        $tenant = tenant();
        $domain = $tenant->domain;

        if (empty($domain)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Domain konfiguriert — Sitemap kann nicht generiert werden.');
            return;
        }

        // Reset previous progress
        $cacheKey = GenerateTenantSitemapJob::cacheKey($tenant->id);
        Cache::put($cacheKey, [
            'status' => 'running',
            'percent' => 0,
            'message' => 'Job wird gestartet...',
        ], now()->addMinutes(10));

        GenerateTenantSitemapJob::dispatch($tenant->id);

        $this->sitemapProgress = Cache::get($cacheKey);
    }

    public function pollSitemapProgress(): void
    {
        $tenant = tenant();
        $cacheKey = GenerateTenantSitemapJob::cacheKey($tenant->id);
        $progress = Cache::get($cacheKey);

        if (!$progress) {
            $this->sitemapProgress = null;
            return;
        }

        $this->sitemapProgress = $progress;

        if ($progress['status'] === 'completed') {
            Cache::forget($cacheKey);
            $this->dispatch('toast', type: 'success', message: $progress['message']);
            // Keep progress visible briefly, then clear on next poll
        } elseif ($progress['status'] === 'failed') {
            Cache::forget($cacheKey);
            $this->dispatch('toast', type: 'error', message: $progress['message']);
        }
    }

    public function getSitemapInfo(): array
    {
        $sitemapPath = storage_path('app/public/sitemap.xml');
        $robotsPath = storage_path('app/public/robots.txt');
        $domain = tenant()->domain ?? null;

        return [
            'exists' => file_exists($sitemapPath),
            'lastGenerated' => file_exists($sitemapPath) ? date('d.m.Y H:i', filemtime($sitemapPath)) : null,
            'size' => file_exists($sitemapPath) ? round(filesize($sitemapPath) / 1024) : 0,
            'robotsExists' => file_exists($robotsPath),
            'url' => $domain ? "https://{$domain}/sitemap.xml" : null,
        ];
    }

    public function render()
    {
        return view('livewire.verwaltung.general-settings-form', [
            'sitemapInfo' => $this->getSitemapInfo(),
        ]);
    }
}
