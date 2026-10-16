<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdUnits\Pages;

use App\Filament\Admin\Resources\AdUnits\AdUnitResource;
use App\Filament\CrudDefaults;
use Filament\Resources\Pages\CreateRecord;

class CreateAdUnit extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = AdUnitResource::class;
}
