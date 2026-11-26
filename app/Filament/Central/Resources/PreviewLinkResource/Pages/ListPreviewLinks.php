<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\PreviewLinkResource\Pages;

use App\Filament\Central\Resources\PreviewLinkResource;
use App\Filament\ListDefaults;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreviewLinks extends ListRecords
{
    use ListDefaults;

    protected static string $resource = PreviewLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Vorschau-Link anlegen')),
        ];
    }

    /**
     * Ziel von „Neu erzeugen" — zeigt die neuen URLs.
     */
    public function previewUrlsAction(): Action
    {
        return PreviewLinkResource::urlsAction();
    }
}
