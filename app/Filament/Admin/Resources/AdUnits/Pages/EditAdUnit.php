<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdUnits\Pages;

use App\Ads\Models\AdUnit;
use App\Filament\Admin\Resources\AdUnits\AdUnitResource;
use App\Filament\CrudDefaults;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Bearbeiten eines Werbemittels (#10). Loeschen steht nur bereit, solange
 * keine Belegung daran haengt — sonst bleibt das Archivieren.
 */
class EditAdUnit extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = AdUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdUnitResource::previewAction(),
            AdUnitResource::archiveAction(),
            AdUnitResource::unarchiveAction(),
            DeleteAction::make()
                ->visible(fn (AdUnit $record): bool => $record->isDeletable()),
        ];
    }
}
