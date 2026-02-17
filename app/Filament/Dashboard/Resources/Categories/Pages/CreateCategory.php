<?php

namespace App\Filament\Dashboard\Resources\Categories\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = CategoryResource::class;
}
