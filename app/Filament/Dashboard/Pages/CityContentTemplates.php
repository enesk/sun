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
 * Tenant-Vorlage fuer Introtext und FAQ der Stadtseiten (#11).
 * Formular: App\Livewire\Filament\Dashboard\CityContentTemplates.
 */
class CityContentTemplates extends Page
{
    protected string $view = 'filament.dashboard.pages.city-content-templates';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'city-content-templates';

    public function getHeading(): string|Htmlable
    {
        return 'Stadtinhalte-Vorlage';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Stadtinhalte-Vorlage';
    }

    public static function getNavigationLabel(): string
    {
        return 'Stadtinhalte-Vorlage';
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
