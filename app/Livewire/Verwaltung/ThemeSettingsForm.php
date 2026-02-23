<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use App\Themes\ThemeManager;
use Livewire\Component;
use Livewire\WithFileUploads;

class ThemeSettingsForm extends Component
{
    use WithFileUploads;

    // Theme selection
    public string $activeTheme = 'default';
    public array $themeOptions = [];

    // Branding colors
    public string $primaryColor = '#3B82F6';
    public string $secondaryColor = '#1E40AF';
    public string $accentColor = '#F59E0B';

    // Typography
    public string $fontFamily = 'Inter';
    public string $borderRadius = '0.5rem';

    // File uploads
    public $logo;
    public $favicon;
    public $ogImage;

    // Current files
    public ?string $currentLogoUrl = null;
    public ?string $currentFaviconUrl = null;
    public ?string $currentOgImageUrl = null;

    // Available themes data
    public array $availableThemes = [];

    // UI
    public bool $saved = false;

    public function mount(): void
    {
        $tenant = tenant();
        $branding = app(TenantBrandingService::class);
        $themeManager = app(ThemeManager::class);

        // Theme
        $this->activeTheme = $themeManager->getTenantTheme($tenant);
        $this->themeOptions = $themeManager->getTenantThemeOptions($tenant);

        // Build available themes list
        $this->availableThemes = $themeManager->discover()
            ->map(fn ($theme) => [
                'slug' => $theme->slug,
                'name' => $theme->name,
                'description' => $theme->description,
                'version' => $theme->version,
                'author' => $theme->author,
            ])
            ->values()
            ->toArray();

        // Branding
        $this->primaryColor = $branding->get($tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6');
        $this->secondaryColor = $branding->get($tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF');
        $this->accentColor = $branding->get($tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B');
        $this->fontFamily = $branding->get($tenant, TenantConfigConstants::FONT_FAMILY, 'Inter');
        $this->borderRadius = $branding->get($tenant, TenantConfigConstants::BORDER_RADIUS, '0.5rem');

        // Current files
        $logoPath = $branding->get($tenant, TenantConfigConstants::LOGO_PATH);
        $faviconPath = $branding->get($tenant, TenantConfigConstants::FAVICON_PATH);
        $ogImagePath = $branding->get($tenant, TenantConfigConstants::OG_IMAGE_PATH);

        $this->currentLogoUrl = $logoPath ? asset('storage/' . $logoPath) : null;
        $this->currentFaviconUrl = $faviconPath ? asset('storage/' . $faviconPath) : null;
        $this->currentOgImageUrl = $ogImagePath ? asset('storage/' . $ogImagePath) : null;
    }

    protected function rules(): array
    {
        return [
            'activeTheme' => ['required', 'string', 'max:50'],
            'primaryColor' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fontFamily' => ['required', 'string', 'max:100'],
            'borderRadius' => ['required', 'string', 'regex:/^[\d.]+rem$/'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg,webp'],
            'favicon' => ['nullable', 'image', 'max:512', 'mimes:png,ico,svg'],
            'ogImage' => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $branding = app(TenantBrandingService::class);
        $themeManager = app(ThemeManager::class);

        // Theme
        if ($themeManager->exists($this->activeTheme)) {
            $themeManager->setTenantTheme($tenant, $this->activeTheme);
        }
        $themeManager->setTenantThemeOptions($tenant, $this->themeOptions);

        // Branding colors
        $branding->setMany($tenant, [
            TenantConfigConstants::PRIMARY_COLOR => $this->primaryColor,
            TenantConfigConstants::SECONDARY_COLOR => $this->secondaryColor,
            TenantConfigConstants::ACCENT_COLOR => $this->accentColor,
            TenantConfigConstants::FONT_FAMILY => $this->fontFamily,
            TenantConfigConstants::BORDER_RADIUS => $this->borderRadius,
        ]);

        // File uploads
        if ($this->logo) {
            $branding->handleFileUpload($tenant, TenantConfigConstants::LOGO_PATH, $this->logo);
            $this->currentLogoUrl = asset('storage/' . $branding->get($tenant, TenantConfigConstants::LOGO_PATH));
            $this->logo = null;
        }

        if ($this->favicon) {
            $branding->handleFileUpload($tenant, TenantConfigConstants::FAVICON_PATH, $this->favicon);
            $this->currentFaviconUrl = asset('storage/' . $branding->get($tenant, TenantConfigConstants::FAVICON_PATH));
            $this->favicon = null;
        }

        if ($this->ogImage) {
            $branding->handleFileUpload($tenant, TenantConfigConstants::OG_IMAGE_PATH, $this->ogImage);
            $this->currentOgImageUrl = asset('storage/' . $branding->get($tenant, TenantConfigConstants::OG_IMAGE_PATH));
            $this->ogImage = null;
        }

        $this->saved = true;
        $this->dispatch('toast', type: 'success', message: 'Theme-Einstellungen gespeichert');
    }

    public function deleteLogo(): void
    {
        $branding = app(TenantBrandingService::class);
        $branding->deleteFile(tenant(), TenantConfigConstants::LOGO_PATH);
        $this->currentLogoUrl = null;
    }

    public function deleteFavicon(): void
    {
        $branding = app(TenantBrandingService::class);
        $branding->deleteFile(tenant(), TenantConfigConstants::FAVICON_PATH);
        $this->currentFaviconUrl = null;
    }

    public function deleteOgImage(): void
    {
        $branding = app(TenantBrandingService::class);
        $branding->deleteFile(tenant(), TenantConfigConstants::OG_IMAGE_PATH);
        $this->currentOgImageUrl = null;
    }

    public function render()
    {
        return view('livewire.verwaltung.theme-settings-form');
    }
}
