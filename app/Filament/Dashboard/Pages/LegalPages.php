<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class LegalPages extends Page
{
    protected string $view = 'filament.dashboard.pages.legal-pages';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'legal-pages';

    public function getHeading(): string|Htmlable
    {
        return 'Rechtliche Seiten';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Rechtliche Seiten';
    }

    public static function getNavigationLabel(): string
    {
        return 'Rechtliches';
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
