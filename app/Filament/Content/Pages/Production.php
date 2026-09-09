<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use BackedEnum;
use Livewire\Attributes\Url;

/**
 * Produktion: Pipeline-Board, Redaktionskalender und Artikelliste (#19, #36).
 *
 * Kalender und Artikelliste sind bewusst keine eigenen Navigationspunkte,
 * sondern Reiter hier — so steht es in design/content-dashboard.md, §0. Die Inhalte selbst
 * sind eigenstaendige Livewire-Komponenten (App\Content\Livewire), damit das
 * Polling des Boards nicht die ganze Seite neu rendert.
 */
class Production extends ContentPage
{
    public const TAB_BOARD = 'board';

    public const TAB_CALENDAR = 'kalender';

    public const TAB_LIST = 'liste';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'produktion';

    protected static string $followUpTicket = '#19';

    protected string $view = 'filament.content.pages.production';

    #[Url(as: 'reiter', history: true)]
    public string $tab = self::TAB_BOARD;

    public static function getNavigationLabel(): string
    {
        return __('Produktion');
    }

    public function getTitle(): string
    {
        return __('Produktion');
    }

    public function switchTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->getTabs()) ? $tab : self::TAB_BOARD;
    }

    /**
     * @return array<string, string>
     */
    public function getTabs(): array
    {
        return [
            self::TAB_BOARD => __('Board'),
            self::TAB_CALENDAR => __('Kalender'),
            self::TAB_LIST => __('Artikel'),
        ];
    }
}
