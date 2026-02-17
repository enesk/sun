<?php

namespace App\Filament\Dashboard\Resources\Companies\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = CompanyResource::class;
}
