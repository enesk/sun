<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdPlacements\Pages;

use App\Filament\Admin\Resources\AdPlacements\AdPlacementResource;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * Liste aller Placements (#10). Ohne Kopfaktionen: angelegt werden Belegungen
 * im Werbeplatz-Editor (#8), hier werden sie in Masse umgestellt.
 */
class ListAdPlacements extends ListRecords
{
    use ListDefaults;

    protected static string $resource = AdPlacementResource::class;
}
