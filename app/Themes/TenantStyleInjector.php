<?php

namespace App\Themes;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;

/**
 * Generates CSS custom properties from tenant branding configuration.
 *
 * These variables are injected as an inline <style> block in the layout,
 * allowing themes to use tenant-specific colors/fonts without hardcoding.
 */
class TenantStyleInjector
{
    public function __construct(
        private TenantBrandingService $brandingService,
    ) {}

    /**
     * Generate the CSS custom properties block for a tenant.
     * Returns the inner CSS (without <style> tags) for flexibility.
     */
    public function generateCssVariables(Tenant $tenant): string
    {
        $vars = [];

        // Core colors
        $primary = $this->brandingService->get($tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6');
        $secondary = $this->brandingService->get($tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF');
        $accent = $this->brandingService->get($tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B');

        $vars['--portal-primary'] = $this->sanitizeCssValue($primary);
        $vars['--portal-secondary'] = $this->sanitizeCssValue($secondary);
        $vars['--portal-accent'] = $this->sanitizeCssValue($accent);

        // Computed color variants (RGB decomposition for opacity support)
        $vars['--portal-primary-rgb'] = $this->hexToRgb($primary);
        $vars['--portal-secondary-rgb'] = $this->hexToRgb($secondary);
        $vars['--portal-accent-rgb'] = $this->hexToRgb($accent);

        // Light/Dark variants via color-mix (CSS Level 4, supported in all modern browsers)
        $vars['--portal-primary-light'] = "color-mix(in srgb, {$this->sanitizeCssValue($primary)} 15%, white)";
        $vars['--portal-primary-dark'] = "color-mix(in srgb, {$this->sanitizeCssValue($primary)} 80%, black)";
        $vars['--portal-primary-hover'] = "color-mix(in srgb, {$this->sanitizeCssValue($primary)} 85%, black)";

        $vars['--portal-secondary-light'] = "color-mix(in srgb, {$this->sanitizeCssValue($secondary)} 15%, white)";
        $vars['--portal-secondary-dark'] = "color-mix(in srgb, {$this->sanitizeCssValue($secondary)} 80%, black)";

        $vars['--portal-accent-light'] = "color-mix(in srgb, {$this->sanitizeCssValue($accent)} 15%, white)";
        $vars['--portal-accent-dark'] = "color-mix(in srgb, {$this->sanitizeCssValue($accent)} 80%, black)";

        // Typography
        $fontFamily = $this->brandingService->get($tenant, TenantConfigConstants::FONT_FAMILY, 'Inter');
        $vars['--portal-font-family'] = $this->sanitizeCssValue($fontFamily) . ', ui-sans-serif, system-ui, sans-serif';

        // Border Radius
        $borderRadius = $this->brandingService->get($tenant, TenantConfigConstants::BORDER_RADIUS, '0.5rem');
        $vars['--portal-radius'] = $this->sanitizeCssValue($borderRadius);
        $vars['--portal-radius-sm'] = $this->scaleRem($borderRadius, 0.5);
        $vars['--portal-radius-lg'] = $this->scaleRem($borderRadius, 1.5);

        // Spacing (8px grid — always injected for consistency)
        $baseSpace = 0.5; // 8px = 0.5rem
        foreach ([1 => 0.5, 2 => 1, 3 => 1.5, 4 => 2, 5 => 2.5, 6 => 3, 8 => 4, 10 => 5, 12 => 6, 16 => 8] as $step => $multiplier) {
            $vars["--portal-space-{$step}"] = ($baseSpace * $multiplier) . 'rem';
        }

        // Shadows (elevation system)
        $vars['--portal-shadow-sm'] = '0 1px 2px 0 rgb(0 0 0 / 0.05)';
        $vars['--portal-shadow-md'] = '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)';
        $vars['--portal-shadow-lg'] = '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)';
        $vars['--portal-shadow-xl'] = '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)';

        // Build CSS
        $lines = [':root {'];
        foreach ($vars as $property => $value) {
            $lines[] = "    {$property}: {$value};";
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Generate a full <style> tag ready for injection into HTML <head>.
     */
    public function generateStyleTag(Tenant $tenant): string
    {
        $css = $this->generateCssVariables($tenant);

        return "<style id=\"tenant-branding\">\n{$css}\n</style>";
    }

    /**
     * Convert hex color to comma-separated RGB values.
     * Used for rgba() support: rgba(var(--portal-primary-rgb), 0.5)
     */
    private function hexToRgb(?string $hex): string
    {
        $hex = ltrim($this->sanitizeCssValue($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '59, 130, 246'; // Fallback: #3B82F6
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    /**
     * Scale a rem value by a factor.
     * e.g. scaleRem('0.5rem', 1.5) => '0.75rem'
     */
    private function scaleRem(?string $value, float $factor): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^([\d.]+)rem$/', trim($value), $matches)) {
            $scaled = round((float) $matches[1] * $factor, 3);

            return $scaled . 'rem';
        }

        return $value;
    }

    /**
     * Sanitize a CSS value to prevent injection attacks.
     * Strips anything that could break out of a CSS property value.
     */
    private function sanitizeCssValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Allow: hex colors, rgb/hsl functions, rem/px units, font names, commas, spaces, parentheses, dots, percentages
        $sanitized = preg_replace('/[^a-zA-Z0-9\s\#\(\)\,\.\-\_\%\/]/', '', $value);

        return $sanitized ?: '';
    }
}
