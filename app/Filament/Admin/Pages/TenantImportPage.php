<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;

class TenantImportPage extends Page
{
    protected string $view = 'filament.admin.pages.tenant-import';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return 'Daten-Import';
    }

    public static function getModelLabel(): string
    {
        return 'Tenant Daten-Import';
    }

    public function getTitle(): string
    {
        return 'Tenant Daten-Import';
    }

    public static function canAccess(): bool
    {
        return auth()->user()
            && auth()->user()->is_admin;
    }
}
