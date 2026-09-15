<?php

namespace App\Filament\Admin\Resources\TenantTexts\Pages;

use App\Filament\Admin\Resources\TenantTexts\Concerns\WarnsAboutPlaceholders;
use App\Filament\Admin\Resources\TenantTexts\TenantTextResource;
use App\Filament\CrudDefaults;
use Filament\Resources\Pages\EditRecord;

class EditTenantText extends EditRecord
{
    use CrudDefaults;
    use WarnsAboutPlaceholders;

    protected static string $resource = TenantTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantTextResource::resetAction(),
        ];
    }

    protected function afterSave(): void
    {
        $this->notifyPlaceholderWarnings();
    }
}
