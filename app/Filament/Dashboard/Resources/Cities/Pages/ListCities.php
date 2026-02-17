<?php

namespace App\Filament\Dashboard\Resources\Cities\Pages;

use App\Filament\Dashboard\Resources\Cities\CityResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
