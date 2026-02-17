<?php

namespace App\Filament\Dashboard\Resources\Categories\Pages;

use App\Filament\Dashboard\Resources\Categories\CategoryResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
