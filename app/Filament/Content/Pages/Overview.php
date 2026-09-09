<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Orchestration\DailyReportBuilder;
use App\Content\Services\ContentOverviewService;
use BackedEnum;

/**
 * Uebersicht: Tagesziel, Portalraster, Kosten, Quellenlage, Ereignisse (#19).
 *
 * Beantwortet in unter zehn Sekunden, ob der Tag in Ordnung ist
 * (design/content-dashboard.md, §1). Die Zahlen kommen aus dem
 * ContentOverviewService, der die 24 Tenant-Datenbanken aggregiert und sein
 * Ergebnis 60 Sekunden zwischenspeichert.
 */
class Overview extends ContentPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = '/';

    protected static string $followUpTicket = '#19';

    protected string $view = 'filament.content.pages.overview';

    public static function getNavigationLabel(): string
    {
        return __('Übersicht');
    }

    public function getTitle(): string
    {
        return __('Übersicht');
    }

    /**
     * Wird vom Polling der Seite aufgerufen. Der Cache des Dienstes laeuft
     * nach 60 Sekunden ohnehin ab; erzwungen wird er hier nicht, sonst
     * kaeme bei 24 Portalen jede Minute die volle Abfragelast.
     */
    public function refreshOverview(): void {}

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return app(ContentOverviewService::class)->snapshot();
    }

    /**
     * Der Tagesbericht des letzten Laufs (#22). Er entsteht um 20:00 im
     * Befehl `content:report:daily`; vor dem ersten Lauf gibt es ihn nicht
     * und die Flaeche bleibt leer.
     *
     * @return array<string, mixed>|null
     */
    public function getDailyReport(): ?array
    {
        return app(DailyReportBuilder::class)->latest();
    }

    public function canSeeCosts(): bool
    {
        return (bool) $this->contentUser()?->canSeeCosts();
    }
}
