<?php

declare(strict_types=1);

namespace App\Guide\Filament\Widgets;

use App\Guide\Services\CostReport;
use App\Guide\Services\TopicDirectory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Kosten je Tag der letzten 30 Tage, gestapelt nach Arbeitsschritt, mit der
 * Linie des Tagesbudgets (#16, design/guide-dashboard.md §7.3). Die Werte
 * stehen zusaetzlich als Tabelle auf der Seite — das Diagramm ist nie die
 * einzige Quelle.
 */
class CostsByDayChart extends ChartWidget
{
    /** Farben aus resources/css/content/theme.css (Marke, kein Statuston). */
    private const COLORS = [
        'research' => 'rgba(13, 133, 123, 0.9)',
        'writing' => 'rgba(114, 219, 205, 0.9)',
        'quality' => 'rgba(180, 83, 9, 0.85)',
        'other' => 'rgba(120, 113, 108, 0.6)',
    ];

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('Kosten je Tag (30 Tage)');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $report = app(CostReport::class)->report(app(TopicDirectory::class)->tenants());
        $days = $report['days'];
        $datasets = [];

        foreach (array_keys(self::COLORS) as $kind) {
            $values = array_map(fn (array $day): float => round($day[$kind], 2), array_values($days));

            if ($kind === 'other' && array_sum($values) === 0.0) {
                continue;
            }

            $datasets[] = [
                'type' => 'bar',
                'label' => CostReport::kindLabel($kind),
                'data' => $values,
                'backgroundColor' => self::COLORS[$kind],
                'stack' => 'kosten',
            ];
        }

        if ($report['totals']['budget_day'] > 0) {
            $datasets[] = [
                'type' => 'line',
                'label' => __('Tagesbudget'),
                'data' => array_fill(0, count($days), $report['totals']['budget_day']),
                'borderColor' => 'rgba(185, 28, 28, 1)',
                'borderWidth' => 1,
                'borderDash' => [4, 4],
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        return [
            'labels' => array_map(fn (string $date): string => Carbon::parse($date)->format('d.m.'), array_keys($days)),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => true]],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'grid' => ['color' => 'rgba(22, 33, 31, 0.08)']],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
