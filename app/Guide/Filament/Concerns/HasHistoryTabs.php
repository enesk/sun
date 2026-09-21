<?php

declare(strict_types=1);

namespace App\Guide\Filament\Concerns;

use App\Guide\Filament\Pages\ArticleVersions;
use App\Guide\Filament\Pages\Costs;
use App\Guide\Filament\Pages\RunHistory;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

use function Filament\Support\original_request;

/**
 * Reiter des Navigationspunkts "Verlauf": Laeufe · Versionen · Kosten
 * (design/guide-dashboard.md §1.2, §7). "Kosten" nur fuer Inhaber.
 */
trait HasHistoryTabs
{
    /**
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make(__('Läufe'))
                ->url(RunHistory::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(RunHistory::getRouteName()))
                ->sort(1),
            NavigationItem::make(__('Versionen'))
                ->url(ArticleVersions::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(ArticleVersions::getRouteName()))
                ->sort(2),
            NavigationItem::make(__('Kosten'))
                ->url(Costs::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(Costs::getRouteName()))
                ->visible(fn (): bool => Costs::canAccess())
                ->sort(3),
        ];
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }
}
