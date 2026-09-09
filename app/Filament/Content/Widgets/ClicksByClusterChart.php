<?php

declare(strict_types=1);

namespace App\Filament\Content\Widgets;

/**
 * Klicks je Themencluster (#25), Reiter "Cluster".
 *
 * Beantwortet: welches Themenfeld traegt die Klicks? Der Balken zeigt die
 * Summe, die Tabelle daneben zusaetzlich Klicks je Artikel — ein grosses
 * Cluster mit vielen schwachen Artikeln sieht sonst besser aus als es ist.
 */
class ClicksByClusterChart extends PerformanceChartWidget
{
    protected ?string $heading = 'Klicks je Cluster';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $clusters = array_slice($this->snapshot()['clusters'], 0, self::BAR_LIMIT);

        return [
            'labels' => array_map(static fn (array $group): string => (string) $group['label'], $clusters),
            'datasets' => [
                [
                    'label' => __('Klicks'),
                    'data' => array_map(static fn (array $group): int => (int) $group['clicks'], $clusters),
                    'backgroundColor' => self::COLOR_PRIMARY,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return __('Zeitraum: :days Tage. Artikel ohne Cluster stehen als eigene Gruppe.', ['days' => $this->days]);
    }
}
