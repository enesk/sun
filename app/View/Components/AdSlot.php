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
