<?php

declare(strict_types=1);

namespace App\Content\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Der Filterbalken der Produktionsansicht (#19).
 *
 * Reihenfolge und Verhalten sind in allen Bereichen gleich
 * (design/content-dashboard.md, §0): Portal · Status · Region · Branche ·
 * Zuruecksetzen. Jeder Filter steht in der URL, ueberlebt damit den Wechsel
 * zwischen Board und Kalender und ist teilbar.
 */
trait HasPipelineFilters
{
    /** @var array<int, string> */
    #[Url(as: 'portal', history: true)]
    public array $portals = [];

    /** @var array<int, string> */
    #[Url(as: 'status', history: true)]
    public array $statuses = [];

    /** @var array<int, string> */
    #[Url(as: 'region', history: true)]
    public array $regions = [];

    /** @var array<int, string> */
    #[Url(as: 'branche', history: true)]
    public array $branches = [];

    public function resetFilters(): void
    {
        $this->portals = [];
        $this->statuses = [];
        $this->regions = [];
        $this->branches = [];
    }

    public function hasFilters(): bool
    {
        return $this->portals !== [] || $this->statuses !== [] || $this->regions !== [] || $this->branches !== [];
    }

    /**
     * Entfernt eine einzelne Marke unter dem Filterbalken.
     */
    public function removeFilter(string $group, string $value): void
    {
        if (! in_array($group, ['portals', 'statuses', 'regions', 'branches'], true)) {
            return;
        }

        $this->{$group} = array_values(array_filter(
            $this->{$group},
            fn (string $current): bool => $current !== $value,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'portals' => $this->portals,
            'statuses' => $this->statuses,
            'regions' => $this->regions,
            'branches' => $this->branches,
        ];
    }
}
