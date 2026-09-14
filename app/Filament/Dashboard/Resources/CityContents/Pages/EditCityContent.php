<?php

namespace App\Filament\Dashboard\Resources\CityContents\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\CityContents\CityContentResource;
use Filament\Resources\Pages\EditRecord;

class EditCityContent extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = CityContentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CityContentResource::normalizeFormData($data);
    }
}
