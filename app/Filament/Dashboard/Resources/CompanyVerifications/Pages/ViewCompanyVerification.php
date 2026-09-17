<?php

namespace App\Filament\Dashboard\Resources\CompanyVerifications\Pages;

use App\Filament\Dashboard\Resources\CompanyVerifications\CompanyVerificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCompanyVerification extends ViewRecord
{
    protected static string $resource = CompanyVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompanyVerificationResource::approveAction(),
            CompanyVerificationResource::rejectAction(),
        ];
    }
}
