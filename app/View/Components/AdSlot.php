<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Portal\AdSlot as AdSlotModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class AdSlot extends Component
{
    public string $position;

    public Collection $slots;

    /**
     * Positions that should be lazy-loaded (below-the-fold).
     * Above-the-fold positions (header_below, sidebar_top) are NOT lazy-loaded.
     */
    private const LAZY_POSITIONS = [
        'listing_detail_after_description',
        'footer_above',
    ];

    /**
     * CLS container dimensions per position.
     * Format: [desktop => [min-width, min-height], mobile => [min-width, min-height] | 'hidden']
     */
    private const CLS_DIMENSIONS = [
        'header_below' => [
            'desktop' => ['min-w-[728px]', 'min-h-[90px]'],
            'mobile' => ['min-w-[320px]', 'min-h-[100px]'],
        ],
        'sidebar_top' => [
            'desktop' => ['min-w-[300px]', 'min-h-[250px]'],
            'mobile' => ['min-w-[300px]', 'min-h-[250px]'],
        ],
        'sidebar_sticky' => [
            'desktop' => ['min-w-[300px]', 'min-h-[250px]'],
            'mobile' => 'hidden',
        ],
        'content_after_intro' => [
            'desktop' => ['', 'min-h-[90px]'],
            'mobile' => ['min-w-[300px]', 'min-h-[250px]'],
        ],
        'listing_detail_after_description' => [
            'desktop' => ['', 'min-h-[90px]'],
            'mobile' => ['min-w-[300px]', 'min-h-[250px]'],
        ],
        'footer_above' => [
            'desktop' => ['min-w-[728px]', 'min-h-[90px]'],
            'mobile' => ['min-w-[320px]', 'min-h-[100px]'],
        ],
        'mobile_sticky_bottom' => [
            'desktop' => 'hidden',
            'mobile' => ['min-w-[320px]', 'min-h-[50px]'],
        ],
    ];

    public function __construct(string $position)
    {
        $this->position = $position;

        // Load from request-scope cache to avoid multiple queries per page
        $allSlots = $this->getAllSlots();
        $this->slots = $allSlots->get($position, collect());
    }

    public function shouldRender(): bool
    {
        return $this->slots->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.ad-slot');
    }

    /**
     * Get CLS container CSS classes for a position (dimensions only, no visibility).
     */
    public static function clsContainerClasses(string $position): string
    {
        $dims = self::CLS_DIMENSIONS[$position] ?? null;

        if (! $dims) {
            return '';
        }

        $classes = [];

        // Mobile dimensions (base breakpoint)
        if ($dims['mobile'] !== 'hidden') {
            if ($dims['mobile'][0]) {
                $classes[] = $dims['mobile'][0];
            }
            if ($dims['mobile'][1]) {
                $classes[] = $dims['mobile'][1];
            }
        }

        // Desktop dimension overrides (lg breakpoint)
        if ($dims['desktop'] !== 'hidden') {
            $desktopW = $dims['desktop'][0] ?? '';
            $desktopH = $dims['desktop'][1] ?? '';
            $mobileW = is_array($dims['mobile']) ? ($dims['mobile'][0] ?? '') : '';
            $mobileH = is_array($dims['mobile']) ? ($dims['mobile'][1] ?? '') : '';

            if ($desktopW && $desktopW !== $mobileW) {
                $classes[] = 'lg:' . $desktopW;
            }
            if ($desktopH && $desktopH !== $mobileH) {
                $classes[] = 'lg:' . $desktopH;
            }
        }

        return implode(' ', array_filter($classes));
    }

    /**
     * Load all active ad slots once per request, grouped by position.
     */
    private function getAllSlots(): Collection
    {
        $key = '_ad_slots_cache';

        if (! app()->bound($key)) {
            try {
                $grouped = AdSlotModel::active()
                    ->sorted()
                    ->get()
                    ->groupBy('position');
            } catch (\Throwable) {
                // Table may not exist yet (pre-migration) or no tenant context
                $grouped = collect();
            }

            app()->instance($key, $grouped);
        }

        return app($key);
    }

    /**
     * Check if active ad slots exist for a given position (usable outside component context).
     */
    public static function hasSlotsForPosition(string $position): bool
    {
        try {
            return AdSlotModel::active()->forPosition($position)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a position should be lazy-loaded.
     */
    public static function isLazy(string $position): bool
    {
        return in_array($position, self::LAZY_POSITIONS, true);
    }

    /**
     * Get CSS classes for device visibility.
     */
    public static function deviceClasses(array $visibility): string
    {
        $hasDesktop = in_array('desktop', $visibility);
        $hasTablet = in_array('tablet', $visibility);
        $hasMobile = in_array('mobile', $visibility);

        // All devices — no hiding needed
        if ($hasDesktop && $hasTablet && $hasMobile) {
            return '';
        }

        // Single device
        if ($hasDesktop && ! $hasTablet && ! $hasMobile) {
            return 'hidden lg:block';
        }
        if ($hasTablet && ! $hasDesktop && ! $hasMobile) {
            return 'hidden md:block lg:hidden';
        }
        if ($hasMobile && ! $hasDesktop && ! $hasTablet) {
            return 'block md:hidden';
        }

        // Two devices
        if ($hasDesktop && $hasTablet && ! $hasMobile) {
            return 'hidden md:block';
        }
        if ($hasDesktop && $hasMobile && ! $hasTablet) {
            return 'block md:hidden lg:block';
        }
        if ($hasTablet && $hasMobile && ! $hasDesktop) {
            return 'block lg:hidden';
        }

        return '';
    }
}
