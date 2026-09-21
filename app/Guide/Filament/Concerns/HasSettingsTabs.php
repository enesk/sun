<?php

declare(strict_types=1);

namespace App\Guide\Filament\Concerns;

use App\Guide\Filament\Pages\DailyRunSettings;
use App\Guide\Filament\Pages\TenantGuideSettings;
use App\Guide\Filament\Resources\PromptTemplateResource;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

use function Filament\Support\original_request;

/**
 * Reiter des Navigationspunkts "Einstellungen": Tageslauf · Portal · Prompts
 * (design/guide-dashboard.md §1.2, §9). Tageslauf ist netzwerkweit (globaler
 * Schalter, Pruefabstand je Kategorie, Pausen, #33); Budget und Quellen
 * stehen als Abschnitte auf der Portalseite, weil sie an
 * tenant_guide_settings haengen.
 */
trait HasSettingsTabs
{
    /**
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make(__('Tageslauf'))
                ->url(DailyRunSettings::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(DailyRunSettings::getRouteName()))
                ->sort(1),
            NavigationItem::make('Portal')
                ->url(TenantGuideSettings::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(TenantGuideSettings::getRouteName()))
                ->sort(2),
            NavigationItem::make(__('Prompts'))
                ->url(PromptTemplateResource::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(PromptTemplateResource::getRouteBaseName().'.*'))
                ->sort(3),
        ];
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }
}
