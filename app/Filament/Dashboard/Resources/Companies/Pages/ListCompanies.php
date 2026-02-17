<?php

namespace App\Filament\Dashboard\Resources\Companies\Pages;

use App\Filament\Dashboard\Resources\Companies\CompanyResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
