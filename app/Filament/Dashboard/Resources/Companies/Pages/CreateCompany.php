<?php

namespace App\Filament\Dashboard\Resources\Companies\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Admins können den Inhaber über das Formular wählen
        // Firmeninhaber werden automatisch als Owner gesetzt
        if (! auth()->user()->isAdmin() || empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
