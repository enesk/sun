<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\City;
use Livewire\Component;
use Livewire\WithPagination;

class CityTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterState = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';
    public int $perPage = 25;

    public array $selected = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterState' => ['except' => '', 'as' => 'bundesland'],
        'sortBy' => ['except' => 'name', 'as' => 'sort'],
        'sortDir' => ['except' => 'asc', 'as' => 'dir'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterState(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function deleteCity(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $city = City::withCount('companies')->find($id);
        if (! $city) {
            return;
        }

        if ($city->companies_count > 0) {
            $this->dispatch('toast', type: 'error', message: "\"{$city->name}\" hat {$city->companies_count} zugeordnete Firmen und kann nicht gelöscht werden.");
            return;
        }

        $name = $city->name;
        $city->delete();

        $this->dispatch('toast', type: 'success', message: "Stadt \"{$name}\" wurde gelöscht.");
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected) || ! auth()->user()->isAdmin()) {
            return;
        }

        $cities = City::whereIn('id', $this->selected)
            ->doesntHave('companies')
            ->get();

        $count = $cities->count();
        foreach ($cities as $city) {
            $city->delete();
        }

        $this->selected = [];
        $this->selectAll = false;

        $this->dispatch('toast', type: 'success', message: "{$count} Städte wurden gelöscht.");
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getCityQuery()->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterState = '';
        $this->resetPage();
    }

    public function render()
    {
        $cities = $this->getCityQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $states = City::whereNotNull('administrative_area_level_1')
            ->where('administrative_area_level_1', '!=', '')
            ->distinct()
            ->orderBy('administrative_area_level_1')
            ->pluck('administrative_area_level_1');

        $isAdmin = auth()->user()->isAdmin();

        return view('livewire.verwaltung.city-table', compact(
            'cities',
            'states',
            'isAdmin',
        ));
    }

    private function getCityQuery()
    {
        $query = City::withCount('companies');

        if ($this->search !== '') {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('zipcode', 'like', $searchTerm)
                    ->orWhere('community', 'like', $searchTerm);
            });
        }

        if ($this->filterState !== '') {
            $query->where('administrative_area_level_1', $this->filterState);
        }

        return $query;
    }
}
