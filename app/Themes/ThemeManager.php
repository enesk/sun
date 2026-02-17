<?php

namespace App\Themes;

use App\Models\Tenant;
use Illuminate\Support\Collection;

class ThemeManager
{
    private ?ThemeDefinition $activeTheme = null;

    private ?Collection $discoveredThemes = null;

    private string $themesBasePath;

    public const DEFAULT_THEME = 'default';

    public const TENANT_THEME_KEY = 'theme.active';

    public const TENANT_THEME_OPTIONS_KEY = 'theme.options';

    public function __construct(?string $themesBasePath = null)
    {
        $this->themesBasePath = $themesBasePath ?? resource_path('views/themes');
    }

    /**
     * Discover all available themes from the themes directory.
     */
    public function discover(): Collection
    {
        if ($this->discoveredThemes !== null) {
            return $this->discoveredThemes;
        }

        $this->discoveredThemes = collect();

        if (! is_dir($this->themesBasePath)) {
            return $this->discoveredThemes;
        }

        $entries = scandir($this->themesBasePath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $directory = $this->themesBasePath . '/' . $entry;

            if (! is_dir($directory)) {
                continue;
            }
            $manifestPath = $directory . '/theme.json';

            if (! file_exists($manifestPath)) {
                continue;
            }

            try {
                $theme = ThemeDefinition::fromJsonFile($manifestPath);
                $this->discoveredThemes->put($theme->slug, $theme);
            } catch (\InvalidArgumentException $e) {
                // Skip invalid themes silently — log if app is available
                if (function_exists('report')) {
                    report($e);
                }
            }
        }

        return $this->discoveredThemes;
    }

    /**
     * Activate a theme for the current request.
     *
     * Sets the active theme definition. View path registration is handled
     * by ThemeViewFinder (called from ResolveTheme middleware).
     */
    public function activate(string $themeSlug): void
    {
        $themes = $this->discover();

        $theme = $themes->get($themeSlug);

        // Fallback to default if theme not found
        if (! $theme) {
            $theme = $themes->get(self::DEFAULT_THEME);
        }

        if (! $theme) {
            throw new \RuntimeException('No theme available. Default theme is missing.');
        }

        $this->activeTheme = $theme;
    }

    /**
     * Get the currently active theme.
     */
    public function active(): ?ThemeDefinition
    {
        return $this->activeTheme;
    }

    /**
     * Get a specific theme by slug.
     */
    public function get(string $slug): ?ThemeDefinition
    {
        return $this->discover()->get($slug);
    }

    /**
     * Check if a theme exists and is valid.
     */
    public function exists(string $slug): bool
    {
        return $this->discover()->has($slug);
    }

    /**
     * Validate that a theme has the required structure.
     */
    public function validate(string $slug): bool
    {
        $theme = $this->get($slug);

        if (! $theme) {
            return false;
        }

        return $theme->isValid();
    }

    /**
     * Get all available themes as array (for admin UI).
     */
    public function available(): Collection
    {
        return $this->discover()->map(fn (ThemeDefinition $theme) => $theme->toArray());
    }

    /**
     * Get the active theme slug for a tenant.
     */
    public function getTenantTheme(Tenant $tenant): string
    {
        return $tenant->getAttribute(self::TENANT_THEME_KEY) ?? self::DEFAULT_THEME;
    }

    /**
     * Set the active theme for a tenant.
     */
    public function setTenantTheme(Tenant $tenant, string $themeSlug): void
    {
        if (! $this->exists($themeSlug)) {
            throw new \InvalidArgumentException("Theme '{$themeSlug}' does not exist.");
        }

        $tenant->setAttribute(self::TENANT_THEME_KEY, $themeSlug);
        $tenant->save();
    }

    /**
     * Get theme options for a tenant (merged with defaults).
     */
    public function getTenantThemeOptions(Tenant $tenant): array
    {
        $themeSlug = $this->getTenantTheme($tenant);
        $theme = $this->get($themeSlug);

        if (! $theme) {
            return [];
        }

        $defaults = $theme->optionDefaults();
        $stored = $tenant->getAttribute(self::TENANT_THEME_OPTIONS_KEY) ?? [];

        return array_merge($defaults, $stored);
    }

    /**
     * Set theme options for a tenant.
     */
    public function setTenantThemeOptions(Tenant $tenant, array $options): void
    {
        $themeSlug = $this->getTenantTheme($tenant);
        $theme = $this->get($themeSlug);

        if (! $theme) {
            return;
        }

        // Only store valid options
        $validated = [];
        foreach ($options as $key => $value) {
            if ($theme->validateOptionValue($key, $value)) {
                $validated[$key] = $value;
            }
        }

        $tenant->setAttribute(self::TENANT_THEME_OPTIONS_KEY, $validated);
        $tenant->save();
    }

    /**
     * Get the themes base path.
     */
    public function getThemesBasePath(): string
    {
        return $this->themesBasePath;
    }

    /**
     * Clear the discovered themes cache (for testing/reloading).
     */
    public function clearCache(): void
    {
        $this->discoveredThemes = null;
        $this->activeTheme = null;
    }
}
