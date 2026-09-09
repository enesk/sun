<?php

declare(strict_types=1);

namespace App\Filament\Content\Widgets;

/**
 * Klicks je Region-Scope (#25), Reiter "Regionen".
 *
 * Bundesweit, Bundesland, Stadt — die Frage lautet, ob sich der
 * Regionalaufwand der Pipeline in Klicks niederschlaegt. Die einzelnen
 * Bundeslaender stehen in der Tabelle darunter; als Balken waeren es
 * sechzehn Zeilen, von denen die meisten leer sind.
 */
class ClicksByRegionChart extends PerformanceChartWidget
{
    protected ?string $heading = 'Klicks je Region-Ebene';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $scopes = $this->snapshot()['scopes'];

        return [
            'labels' => array_map(static fn (array $group): string => (string) $group['label'], $scopes),
            'datasets' => [
                [
                    'label' => __('Klicks'),
                    'data' => array_map(static fn (array $group): int => (int) $group['clicks'], $scopes),
                    'backgroundColor' => self::COLOR_PRIMARY,
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return __('Zeitraum: :days Tage.', ['days' => $this->days]);
    }
}
