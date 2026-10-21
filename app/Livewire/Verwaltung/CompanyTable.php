<?php

namespace App\Livewire\Verwaltung;

use App\Companies\Deletion\DeleteCompanyAction;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class CompanyTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterCity = '';
    public string $filterCategory = '';
    public string $filterStatus = '';
    public string $filterPremium = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public int $perPage = 15;

    // Bulk actions
    public array $selected = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterCity' => ['except' => '', 'as' => 'city'],
        'filterCategory' => ['except' => '', 'as' => 'category'],
        'filterStatus' => ['except' => '', 'as' => 'status'],
        'sortBy' => ['except' => 'created_at', 'as' => 'sort'],
        'sortDir' => ['except' => 'desc', 'as' => 'dir'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCity(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
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

    public function toggleActive(int $id): void
    {
        $company = Company::find($id);
        if (! $company) {
            return;
        }

        // Only admins can toggle other's companies
        $user = auth()->user();
        if (! $user->isAdmin() && $company->user_id !== $user->id) {
            return;
        }

        $company->update(['is_active' => ! $company->is_active]);

        $status = $company->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', message: "\"{$company->name}\" wurde {$status}.");
    }

    public function togglePremium(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $company = Company::find($id);
        if (! $company) {
            return;
        }

        $company->update(['is_premium' => ! $company->is_premium]);

        $status = $company->is_premium ? 'Premium aktiviert' : 'Premium deaktiviert';
        $this->dispatch('toast', type: 'success', message: "\"{$company->name}\": {$status}.");
    }

    public function deleteCompany(int $id): void
    {
        $company = Company::find($id);
        if (! $company) {
            return;
        }

        $user = auth()->user();
        if (! $user->isAdmin() && $company->user_id !== $user->id) {
            return;
        }

        $name = $company->name;

        // Loeschung ausschliesslich ueber die Pipeline (SUN-RQ #8): Profil,
        // Bilder, Bewertungen, Leads anonymisieren, 410, Sitemap, IndexNow,
        // Caches — jeder Schritt protokolliert in `deletion_log`. Vorher
        // raeumte diese Methode nur Oeffnungszeiten, Kategorien und Bilder
        // ab; das war der zweite, unvollstaendige Loeschweg.
        try {
            $result = app(DeleteCompanyAction::class)->forCompany($company, [
                'reason' => 'Verwaltung: '.($user->email ?? 'unbekannt'),
            ]);
        } catch (Throwable $exception) {
            Log::error('Verwaltung: Loeschung fehlgeschlagen.', [
                'company_id' => $id,
                'exception' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', type: 'error', message: "\"{$name}\" konnte nicht gelöscht werden.");

            return;
        }

        $this->dispatch(
            'toast',
            type: $result->ok() ? 'success' : 'warning',
            message: $result->ok()
                ? "\"{$name}\" wurde vollständig gelöscht."
                : "\"{$name}\" wurde gelöscht, aber nicht jeder Schritt lief durch (run_id {$result->runId}).",
        );
    }

    public function bulkToggleActive(bool $active): void
    {
        if (empty($this->selected)) {
            return;
        }

        $query = Company::whereIn('id', $this->selected);

        // Non-admins can only toggle their own
        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        // Bewusst Model fuer Model: ein Massen-Update am Query Builder loest
        // keine Model-Events aus, die Freischalt-Mail wuerde ausbleiben.
        $companies = $query->get();
        $count = $companies->count();
        $companies->each(fn (Company $company) => $company->update(['is_active' => $active]));

        $this->selected = [];
        $this->selectAll = false;

        $status = $active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', message: "{$count} Firmen wurden {$status}.");
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getCompanyQuery()->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterCity = '';
        $this->filterCategory = '';
        $this->filterStatus = '';
        $this->filterPremium = '';
        $this->resetPage();
    }

    public function render()
    {
        $companies = $this->getCompanyQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $cities = City::orderBy('name')->pluck('name', 'id');
        $categories = Category::orderBy('name')->pluck('name', 'id');

        $isAdmin = auth()->user()->isAdmin();

        return view('livewire.verwaltung.company-table', compact(
            'companies',
            'cities',
            'categories',
            'isAdmin',
        ));
    }

    private function getCompanyQuery()
    {
        $query = Company::with(['city', 'categories', 'owner']);

        // Non-admins only see their own companies
        $user = auth()->user();
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Search
        if ($this->search !== '') {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('tel', 'like', $searchTerm)
                    ->orWhere('zipcode', 'like', $searchTerm);
            });
        }

        // Filters
        if ($this->filterCity !== '') {
            $query->where('city_id', (int) $this->filterCity);
        }

        if ($this->filterCategory !== '') {
            $query->inCategory((int) $this->filterCategory);
        }

        if ($this->filterStatus !== '') {
            $query->where('is_active', $this->filterStatus === 'active');
        }

        if ($this->filterPremium !== '') {
            $query->where('is_premium', $this->filterPremium === 'yes');
        }

        return $query;
    }
}
