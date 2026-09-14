<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * SEO-Templates je Tenant (#10): Title, Description und H1 der Stadtseiten.
 * Formular: App\Livewire\Filament\Dashboard\SeoTemplates.
 */
class SeoTemplates extends Page
{
    protected string $view = 'filament.dashboard.pages.seo-templates';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'seo-templates';

    public function getHeading(): string|Htmlable
    {
        return 'SEO-Templates';
    }

    public function getTitle(): string|Htmlable
    {
        return 'SEO-Templates';
    }

    public static function getNavigationLabel(): string
    {
        return 'SEO-Templates';
    }

    public static function canAccess(): bool
    {
        $tenantPermissionService = app(TenantPermissionService::class);

        return $tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
        );
    }
}
