<?php

namespace App\Filament\Dashboard\Resources\CompanyVerifications\Pages;

use App\Filament\Dashboard\Resources\CompanyVerifications\CompanyVerificationResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

class ListCompanyVerifications extends ListRecords
{
    use ListDefaults;

    protected static string $resource = CompanyVerificationResource::class;
}
