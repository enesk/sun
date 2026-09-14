<?php

namespace App\Filament\Dashboard\Resources\CityContents\Pages;

use App\Filament\Dashboard\Resources\CityContents\CityContentResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCityContents extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CityContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
