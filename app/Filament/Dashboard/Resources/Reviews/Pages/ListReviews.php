<?php

namespace App\Filament\Dashboard\Resources\Reviews\Pages;

use App\Filament\Dashboard\Resources\Reviews\ReviewResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    use ListDefaults;

    protected static string $resource = ReviewResource::class;
}
