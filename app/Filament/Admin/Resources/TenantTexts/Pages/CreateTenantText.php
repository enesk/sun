<?php

namespace App\Filament\Admin\Resources\TenantTexts\Pages;

use App\Filament\Admin\Resources\TenantTexts\Concerns\WarnsAboutPlaceholders;
use App\Filament\Admin\Resources\TenantTexts\TenantTextResource;
use App\Filament\CrudDefaults;
use App\Support\Translation\PortalTextCatalog;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantText extends CreateRecord
{
    use CrudDefaults;
    use WarnsAboutPlaceholders;

    protected static string $resource = TenantTextResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['locale'] = PortalTextCatalog::LOCALE;
        $data['group'] = PortalTextCatalog::GROUP;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->notifyPlaceholderWarnings();
    }
}
