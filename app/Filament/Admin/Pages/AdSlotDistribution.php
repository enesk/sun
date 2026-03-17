<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;

class AdSlotDistribution extends Page
{
    protected string $view = 'filament.admin.pages.ad-slot-distribution';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return 'Ad-Slots verteilen';
    }

    public static function getModelLabel(): string
    {
        return 'Ad-Slots verteilen';
    }

    public function getTitle(): string
    {
        return 'Ad-Slots verteilen';
    }

    public static function canAccess(): bool
    {
        return auth()->user()
            && auth()->user()->is_admin;
    }
}
