<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Category;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterParent = '';
    public string $sortBy = 'sort_order';
    public string $sortDir = 'asc';
    public int $perPage = 25;

    public array $selected = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterParent' => ['except' => '', 'as' => 'parent'],
        'sortBy' => ['except' => 'sort_order', 'as' => 'sort'],
        'sortDir' => ['except' => 'asc', 'as' => 'dir'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterParent(): void
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

    public function deleteCategory(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $category = Category::find($id);
        if (! $category) {
            return;
        }

        if ($category->children()->exists()) {
            $this->dispatch('toast', type: 'error', message: "\"{$category->name}\" hat Unterkategorien und kann nicht gelöscht werden.");
            return;
        }

        $name = $category->name;
        $category->companies()->detach();
        $category->delete();

        $this->dispatch('toast', type: 'success', message: "Kategorie \"{$name}\" wurde gelöscht.");
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected) || ! auth()->user()->isAdmin()) {
            return;
        }

        $categories = Category::whereIn('id', $this->selected)
            ->doesntHave('children')
            ->get();

        $count = 0;
        foreach ($categories as $category) {
            $category->companies()->detach();
            $category->delete();
            $count++;
        }

        $this->selected = [];
        $this->selectAll = false;

        $this->dispatch('toast', type: 'success', message: "{$count} Kategorien wurden gelöscht.");
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getCategoryQuery()->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterParent = '';
        $this->resetPage();
    }

    public function render()
    {
        $categories = $this->getCategoryQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $parentCategories = Category::roots()->ordered()->pluck('name', 'id');

        $isAdmin = auth()->user()->isAdmin();

        return view('livewire.verwaltung.category-table', compact(
            'categories',
            'parentCategories',
            'isAdmin',
        ));
    }

    private function getCategoryQuery()
    {
        $query = Category::with('parent')
            ->withCount(['companies', 'children']);

        if ($this->search !== '') {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('slug', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        if ($this->filterParent !== '') {
            if ($this->filterParent === 'roots') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (int) $this->filterParent);
            }
        }

        return $query;
    }
}
