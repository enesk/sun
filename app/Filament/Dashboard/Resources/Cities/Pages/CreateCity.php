<?php

namespace App\Filament\Dashboard\Resources\Cities\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Cities\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = CityResource::class;
}
