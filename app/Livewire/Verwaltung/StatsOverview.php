<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Livewire\Component;

/**
 * KPI Widget for the Verwaltung overview page.
 *
 * Shows: Companies count, Reviews count (with pending), Avg Rating, Active/Premium split.
 * Supports period-based comparison (current vs. previous period).
 */
class StatsOverview extends Component
{
    public string $period = '30'; // days

    public function render()
    {
        $days = (int) $this->period;
        $now = now();
        $periodStart = $now->copy()->subDays($days);
        $prevStart = $periodStart->copy()->subDays($days);

        // Companies
        $companiesTotal = Company::count();
        $companiesNew = Company::where('created_at', '>=', $periodStart)->count();
        $companiesPrev = Company::whereBetween('created_at', [$prevStart, $periodStart])->count();

        // Reviews
        $reviewsTotal = Review::count();
        $reviewsPending = Review::where('moderation_status', 'pending')->count();
        $reviewsNew = Review::where('created_at', '>=', $periodStart)->count();
        $reviewsPrev = Review::whereBetween('created_at', [$prevStart, $periodStart])->count();

        // Average Rating (across all companies that have ratings)
        $avgRating = Company::where('rating', '>', 0)->avg('rating');
        $avgRating = $avgRating ? round($avgRating, 1) : 0;

        // Premium split
        $premiumCount = Company::where('is_premium', true)->count();

        $stats = [
            [
                'label' => 'Firmen',
                'value' => number_format($companiesTotal),
                'sub' => $companiesNew > 0 ? "+{$companiesNew} neu" : 'Keine neuen',
                'trend' => $this->calcTrend($companiesNew, $companiesPrev),
                'icon' => 'building',
                'color' => 'primary',
            ],
            [
                'label' => 'Bewertungen',
                'value' => number_format($reviewsTotal),
                'sub' => $reviewsPending > 0 ? "{$reviewsPending} wartend" : 'Alle moderiert',
                'trend' => $this->calcTrend($reviewsNew, $reviewsPrev),
                'icon' => 'star',
                'color' => 'accent',
                'highlight' => $reviewsPending > 0,
            ],
            [
                'label' => 'Ø Rating',
                'value' => $avgRating > 0 ? number_format($avgRating, 1) : '—',
                'sub' => $companiesTotal > 0 ? "{$companiesTotal} Einträge" : 'Keine Einträge',
                'trend' => null,
                'icon' => 'chart',
                'color' => 'default',
            ],
            [
                'label' => 'Premium',
                'value' => $premiumCount,
                'sub' => $companiesTotal > 0 ? round(($premiumCount / $companiesTotal) * 100) . '% Quote' : '—',
                'trend' => null,
                'icon' => 'sparkle',
                'color' => 'default',
            ],
        ];

        return view('livewire.verwaltung.stats-overview', compact('stats'));
    }

    private function calcTrend(int $current, int $previous): ?array
    {
        if ($previous === 0 && $current === 0) {
            return null;
        }

        if ($previous === 0) {
            return ['direction' => 'up', 'label' => 'Neu'];
        }

        $change = round((($current - $previous) / $previous) * 100);

        if ($change === 0) {
            return ['direction' => 'flat', 'label' => 'Stabil'];
        }

        return [
            'direction' => $change > 0 ? 'up' : 'down',
            'label' => ($change > 0 ? '+' : '') . $change . '%',
        ];
    }
}
