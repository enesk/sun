<?php

namespace App\Filament\Dashboard\Resources\CityContents\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\CityContents\CityContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCityContent extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = CityContentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CityContentResource::normalizeFormData($data);
    }
}
