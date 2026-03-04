<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Job;
use Livewire\Component;
use Livewire\WithPagination;

class JobTable extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';
    public string $filterType = '';
    public string $filterCompany = '';

    // Sort
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    // Bulk
    public array $selected = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterType' => ['except' => ''],
        'filterCompany' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCompany(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getJobQuery()->pluck('jobs.id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterType = '';
        $this->filterCompany = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    // ========================================================================
    // Quick Actions
    // ========================================================================

    public function deactivateJob(int $jobId): void
    {
        $job = Job::with('company')->find($jobId);
        if (! $job) return;

        $job->update(['is_active' => false]);

        $this->dispatch('toast', type: 'success', message: "Stellenanzeige \"{$job->title}\" deaktiviert.");
    }

    public function activateJob(int $jobId): void
    {
        $job = Job::with('company')->find($jobId);
        if (! $job) return;

        $job->publish();

        $this->dispatch('toast', type: 'success', message: "Stellenanzeige \"{$job->title}\" aktiviert (30 Tage).");
    }

    public function deleteJob(int $jobId): void
    {
        $job = Job::with('applications')->find($jobId);
        if (! $job) return;

        $title = $job->title;

        // Bewerbungen + Media loeschen
        foreach ($job->applications as $application) {
            if (method_exists($application, 'clearMediaCollection')) {
                $application->clearMediaCollection('cv');
            }
        }
        $job->applications()->delete();
        $job->delete();

        $this->dispatch('toast', type: 'success', message: "Stellenanzeige \"{$title}\" gelöscht.");
    }

    // ========================================================================
    // Bulk Actions
    // ========================================================================

    public function bulkDeactivate(): void
    {
        $count = Job::whereIn('id', $this->selected)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('toast', type: 'success', message: "{$count} Stellenanzeigen deaktiviert.");
    }

    public function bulkDelete(): void
    {
        $jobs = Job::whereIn('id', $this->selected)->with('applications')->get();
        $count = 0;

        foreach ($jobs as $job) {
            foreach ($job->applications as $application) {
                if (method_exists($application, 'clearMediaCollection')) {
                    $application->clearMediaCollection('cv');
                }
            }
            $job->applications()->delete();
            $job->delete();
            $count++;
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('toast', type: 'success', message: "{$count} Stellenanzeigen gelöscht.");
    }

    // ========================================================================
    // Rendering
    // ========================================================================

    public function render()
    {
        $jobs = $this->getJobQuery()
            ->orderBy($this->sortBy === 'company' ? 'companies.name' : 'jobs.' . $this->sortBy, $this->sortDir)
            ->paginate(15);

        // Status counts for tabs
        $baseQuery = Job::query();
        $statusCounts = [
            'all' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->active()->count(),
            'expired' => (clone $baseQuery)->where(function ($q) {
                $q->where('expires_at', '<=', now())->orWhere('is_active', false);
            })->count(),
        ];

        // Company list for filter
        $companies = \App\Models\Portal\Company::orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return view('livewire.verwaltung.job-table', compact(
            'jobs',
            'statusCounts',
            'companies',
        ));
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

    private function getJobQuery()
    {
        $query = Job::with(['company', 'city'])
            ->withCount('applications')
            ->join('companies', 'jobs.company_id', '=', 'companies.id')
            ->select('jobs.*');

        // Status filter
        if ($this->filterStatus === 'active') {
            $query->active();
        } elseif ($this->filterStatus === 'expired') {
            $query->where(function ($q) {
                $q->where('jobs.expires_at', '<=', now())
                    ->orWhere('jobs.is_active', false);
            });
        }

        // Employment type filter
        if ($this->filterType !== '') {
            $query->where('jobs.employment_type', $this->filterType);
        }

        // Company filter
        if ($this->filterCompany !== '') {
            $query->where('jobs.company_id', (int) $this->filterCompany);
        }

        // Search
        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('jobs.title', 'like', $search)
                    ->orWhere('jobs.description', 'like', $search)
                    ->orWhere('companies.name', 'like', $search);
            });
        }

        return $query;
    }
}
