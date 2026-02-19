<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ThemeSettings extends Page
{
    protected string $view = 'filament.dashboard.pages.theme-settings';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'theme-settings';

    public function getHeading(): string|Htmlable
    {
        return 'Theme & Design';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Theme & Design';
    }

    public static function getNavigationLabel(): string
    {
        return 'Theme';
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
