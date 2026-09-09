<?php

declare(strict_types=1);

namespace App\Filament\Content\Widgets;

/**
 * Kosten gegen Ertrag je Portal (#25), Reiter "Kosten".
 *
 * Kosten aus `llm_usage_logs`, Ertrag aus den AdSense-Spalten der
 * Metrikzeilen, beides in USD. Nur fuer die Rolle `owner`: die Seite rendert
 * das Diagramm gar nicht erst, wenn die Rolle fehlt.
 *
 * Portale ohne AdSense-Zuordnung tragen keinen Ertragsbalken — null heisst
 * "nicht gemessen", nicht "nichts verdient".
 */
class CostVsRevenueChart extends PerformanceChartWidget
{
    protected ?string $heading = 'Kosten und Ertrag je Portal';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $costs = array_slice($this->snapshot()['costs'], 0, self::BAR_LIMIT);

        return [
            'labels' => array_map(static fn (array $row): string => (string) $row['portal'], $costs),
            'datasets' => [
                [
                    'label' => __('Kosten (USD)'),
                    'data' => array_map(static fn (array $row): float => round((float) $row['cost_usd'], 2), $costs),
                    'backgroundColor' => self::COLOR_SECONDARY,
                    'borderRadius' => 4,
                ],
                [
                    'label' => __('Ertrag (USD)'),
                    'data' => array_map(
                        static fn (array $row): ?float => $row['revenue_usd'] === null
                            ? null
                            : round((float) $row['revenue_usd'], 2),
                        $costs,
                    ),
                    'backgroundColor' => self::COLOR_PRIMARY,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function showsLegend(): bool
    {
        return true;
    }

    public function getDescription(): ?string
    {
        return __('Zeitraum: :days Tage. Ohne AdSense-Zuordnung bleibt der Ertragsbalken leer.', ['days' => $this->days]);
    }
}
