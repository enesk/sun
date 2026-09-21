<?php

declare(strict_types=1);

namespace App\Guide\Filament\Concerns;

use App\Guide\Filament\Pages\ConfirmOutlines;
use App\Guide\Filament\Pages\ImportWizard;
use App\Guide\Filament\Pages\LegacyOverlaps;
use App\Guide\Filament\Resources\CategoryResource;
use App\Guide\Filament\Resources\TopicResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

use function Filament\Support\original_request;

/**
 * Reiter des Navigationspunkts "Themen": Themen · Kategorien · Import ·
 * Altartikel (design/guide-dashboard.md §1.2). "Altartikel" traegt die Zahl
 * offener Paare im Portalfilter; bei 0 bleibt der Reiter ohne Marke stehen. Filament-Unternavigation oben, jede
 * Ansicht hat ihre eigene Adresse; der Portalfilter bleibt ueber die Session
 * erhalten.
 */
trait HasTopicTabs
{
    /**
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make(__('Themen'))
                ->url(TopicResource::getUrl())
                // Nicht ".*": die Kategorien liegen unter themen.kategorien.*
                ->isActiveWhen(fn (): bool => original_request()->routeIs(
                    TopicResource::getRouteBaseName().'.index',
                    TopicResource::getRouteBaseName().'.view',
                    ConfirmOutlines::getRouteName(),
                ))
                ->sort(1),
            NavigationItem::make(__('Kategorien'))
                ->url(CategoryResource::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(CategoryResource::getRouteBaseName().'.*'))
                ->sort(2),
            NavigationItem::make(__('Import'))
                ->url(ImportWizard::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(ImportWizard::getRouteName()))
                ->sort(3),
            NavigationItem::make(__('Altartikel'))
                ->url(LegacyOverlaps::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(LegacyOverlaps::getRouteName()))
                ->badge(fn (): ?string => ($count = LegacyOverlaps::openCount()) > 0 ? (string) $count : null, 'status-review')
                ->sort(4),
        ];
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }
}
