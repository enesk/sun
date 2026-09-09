<?php

declare(strict_types=1);

namespace App\Filament\Content\Widgets;

use App\Content\Services\PerformanceDashboardService;
use Filament\Widgets\ChartWidget;

/**
 * Gemeinsame Grundlage der drei Diagramme der Leistungsansicht (#25).
 *
 * Zeitraum und Portalauswahl kommen als Eigenschaften von der Seite; die
 * Zahlen holt jedes Diagramm selbst aus dem PerformanceDashboardService.
 * Das ist keine vierfache Last: der Dienst legt seine Momentaufnahme 60
 * Sekunden in den Cache, Seite und Diagramme desselben Aufrufs teilen sie
 * sich (design/content-dashboard.md, §5).
 */
abstract class PerformanceChartWidget extends ChartWidget
{
    /**
     * Farben aus resources/css/content/theme.css. Statusfarben werden hier
     * bewusst nicht verwendet — ein Balken ist kein Zustand (#30).
     */
    protected const COLOR_PRIMARY = 'rgba(23, 166, 152, 0.85)';

    protected const COLOR_SECONDARY = 'rgba(180, 83, 9, 0.85)';

    protected const COLOR_LINE = 'rgba(22, 33, 31, 0.08)';

    /**
     * Zeitraum in Tagen (7, 30 oder 90).
     */
    public int $days = PerformanceDashboardService::PERIOD_DEFAULT;

    /**
     * Portalauswahl der Seite; leer heisst "alle zugaenglichen Portale".
     *
     * @var array<int, int>
     */
    public array $portals = [];

    protected ?string $maxHeight = '280px';

    /**
     * Hoechstzahl Balken je Diagramm. Mehr liest niemand ab, der Rest steht
     * in der Tabelle darunter.
     */
    protected const BAR_LIMIT = 8;

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        return app(PerformanceDashboardService::class)->snapshot($this->days, ['portals' => $this->portals]);
    }

    /**
     * Waagerechte Balken mit weicher Rasterlinie — dieselbe Bauform in allen
     * drei Diagrammen, damit sie nebeneinander vergleichbar bleiben.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => $this->showsLegend()],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => self::COLOR_LINE],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function showsLegend(): bool
    {
        return false;
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
