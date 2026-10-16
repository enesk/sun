<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdUnits\Pages;

use App\Filament\Admin\Resources\AdUnits\AdUnitResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdUnits extends ListRecords
{
    use ListDefaults;

    protected static string $resource = AdUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
