<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Services\ReviewQueueService;
use BackedEnum;
use Throwable;

/**
 * Pruefung: Freigabe-Queue mit Artikelvorschau und Qualitaetsreport (#20).
 *
 * Der einzige Bereich, der Arbeit einfordert, und deshalb der einzige mit
 * Zaehlmarke in der Navigation. Die Zahl sind die Entwuerfe im Status
 * `review` ueber alle sichtbaren Portale; bei 0 verschwindet sie ganz
 * (design/content-dashboard.md, §0).
 *
 * Inhalt und Handlungen liegen in der Livewire-Komponente
 * App\Content\Livewire\ReviewQueue.
 */
class Review extends ContentPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'pruefung';

    protected static string $followUpTicket = '#20';

    protected string $view = 'filament.content.pages.review';

    public static function getNavigationLabel(): string
    {
        return __('Prüfung');
    }

    public function getTitle(): string
    {
        return __('Prüfung');
    }

    /**
     * Zaehlmarke. Ab 100 steht dort "99+" — die genaue Zahl hilft dann
     * niemandem mehr, und die Marke ist 20 px breit.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = app(ReviewQueueService::class)->pendingCount();
        } catch (Throwable) {
            // Ein Portal ohne Pipeline-Tabellen darf die Navigation nicht
            // zerlegen. Ohne Zahl ist die Marke einfach nicht da.
            return null;
        }

        if ($count <= 0) {
            return null;
        }

        return $count > 99 ? '99+' : (string) $count;
    }
}
