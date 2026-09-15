<?php

namespace App\Filament\Admin\Resources\TenantTexts\Pages;

use App\Filament\Admin\Resources\TenantTexts\TenantTextResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantTexts extends ListRecords
{
    use ListDefaults;

    protected static string $resource = TenantTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
