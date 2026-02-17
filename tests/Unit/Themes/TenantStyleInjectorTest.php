<?php

namespace Tests\Unit\Themes;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use App\Themes\TenantStyleInjector;
use PHPUnit\Framework\TestCase;

class TenantStyleInjectorTest extends TestCase
{
    private TenantStyleInjector $injector;

    private TenantBrandingService $brandingService;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brandingService = $this->createMock(TenantBrandingService::class);
        $this->tenant = $this->createMock(Tenant::class);
        $this->injector = new TenantStyleInjector($this->brandingService);
    }

    public function test_generates_css_variables_with_defaults(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', '#3B82F6'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', '#1E40AF'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#F59E0B'],
            ]);

        $css = $this->injector->generateCssVariables($this->tenant);

        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('--portal-primary: #3B82F6;', $css);
        $this->assertStringContainsString('--portal-secondary: #1E40AF;', $css);
        $this->assertStringContainsString('--portal-accent: #F59E0B;', $css);
    }

    public function test_generates_css_variables_with_custom_colors(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', '#FF0000'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', '#00FF00'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#0000FF'],
            ]);

        $css = $this->injector->generateCssVariables($this->tenant);

        $this->assertStringContainsString('--portal-primary: #FF0000;', $css);
        $this->assertStringContainsString('--portal-secondary: #00FF00;', $css);
        $this->assertStringContainsString('--portal-accent: #0000FF;', $css);
    }

    public function test_generate_style_tag_wraps_in_style_element(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', '#3B82F6'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', '#1E40AF'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#F59E0B'],
            ]);

        $html = $this->injector->generateStyleTag($this->tenant);

        $this->assertStringStartsWith('<style id="tenant-branding">', $html);
        $this->assertStringEndsWith('</style>', $html);
        $this->assertStringContainsString(':root {', $html);
    }

    public function test_sanitizes_css_injection_attempts(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', '#3B82F6; } body { display:none'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', '#1E40AF'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#F59E0B'],
            ]);

        $css = $this->injector->generateCssVariables($this->tenant);

        // The injection attempt should be sanitized
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringNotContainsString('body {', $css);
    }

    public function test_sanitizes_special_characters(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', '<script>alert(1)</script>'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', '#1E40AF'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#F59E0B'],
            ]);

        $css = $this->injector->generateCssVariables($this->tenant);

        $this->assertStringNotContainsString('<script>', $css);
        $this->assertStringNotContainsString('</script>', $css);
    }

    public function test_allows_valid_css_values(): void
    {
        $this->brandingService->method('get')
            ->willReturnMap([
                [$this->tenant, TenantConfigConstants::PRIMARY_COLOR, '#3B82F6', 'rgb(59, 130, 246)'],
                [$this->tenant, TenantConfigConstants::SECONDARY_COLOR, '#1E40AF', 'hsl(220, 80%, 50%)'],
                [$this->tenant, TenantConfigConstants::ACCENT_COLOR, '#F59E0B', '#F59E0B'],
            ]);

        $css = $this->injector->generateCssVariables($this->tenant);

        $this->assertStringContainsString('rgb(59, 130, 246)', $css);
        $this->assertStringContainsString('hsl(220, 80%, 50%)', $css);
    }
}
