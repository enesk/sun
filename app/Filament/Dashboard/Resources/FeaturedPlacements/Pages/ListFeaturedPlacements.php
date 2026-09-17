<?php

namespace App\Filament\Dashboard\Resources\FeaturedPlacements\Pages;

use App\Filament\Dashboard\Resources\FeaturedPlacements\FeaturedPlacementResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

class ListFeaturedPlacements extends ListRecords
{
    use ListDefaults;

    protected static string $resource = FeaturedPlacementResource::class;
}
