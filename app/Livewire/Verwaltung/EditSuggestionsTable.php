<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\CompanyEditSuggestion;
use Livewire\Component;
use Livewire\WithPagination;

class EditSuggestionsTable extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';
    public string $filterField = '';
    public string $filterCompany = '';

    // Sort
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    // Bulk
    public array $selected = [];
    public bool $selectAll = false;

    // Reject modal
    public bool $showRejectModal = false;
    public ?int $rejectingSuggestionId = null;
    public string $rejectReason = '';
    public bool $isBulkReject = false;

    // Detail modal
    public bool $showDetailModal = false;
    public ?int $detailSuggestionId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterField' => ['except' => ''],
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

    public function updatingFilterField(): void
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
            $this->selected = $this->getSuggestionQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
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
        $this->filterField = '';
        $this->filterCompany = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    // ========================================================================
    // Quick Actions
    // ========================================================================

    public function approveSuggestion(int $id): void
    {
        $suggestion = $this->resolveSuggestion($id);
        if (! $suggestion) return;

        $suggestion->update([
            'status' => CompanyEditSuggestion::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: "Änderungsvorschlag für \"{$suggestion->company->name}\" genehmigt.");
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectingSuggestionId = $id;
        $this->rejectReason = '';
        $this->isBulkReject = false;
        $this->showRejectModal = true;
    }

    public function confirmReject(): void
    {
        if ($this->isBulkReject) {
            $this->executeBulkReject();
            return;
        }

        $suggestion = $this->resolveSuggestion($this->rejectingSuggestionId);
        if (! $suggestion) return;

        $suggestion->update([
            'status' => CompanyEditSuggestion::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->showRejectModal = false;
        $this->rejectingSuggestionId = null;
        $this->rejectReason = '';

        $this->dispatch('toast', type: 'success', message: "Änderungsvorschlag abgelehnt.");
    }

    public function showDetail(int $id): void
    {
        $this->detailSuggestionId = $id;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->detailSuggestionId = null;
    }

    // ========================================================================
    // Bulk Actions
    // ========================================================================

    public function bulkApprove(): void
    {
        $suggestions = CompanyEditSuggestion::with('company')->whereIn('id', $this->selected)->get();
        $count = 0;

        foreach ($suggestions as $suggestion) {
            if ($this->canAccessSuggestion($suggestion)) {
                $suggestion->update([
                    'status' => CompanyEditSuggestion::STATUS_APPROVED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
                $count++;
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('toast', type: 'success', message: "{$count} Vorschläge genehmigt.");
    }

    public function openBulkRejectModal(): void
    {
        $this->rejectReason = '';
        $this->isBulkReject = true;
        $this->showRejectModal = true;
    }

    private function executeBulkReject(): void
    {
        $suggestions = CompanyEditSuggestion::with('company')->whereIn('id', $this->selected)->get();
        $count = 0;

        foreach ($suggestions as $suggestion) {
            if ($this->canAccessSuggestion($suggestion)) {
                $suggestion->update([
                    'status' => CompanyEditSuggestion::STATUS_REJECTED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
                $count++;
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->showRejectModal = false;
        $this->rejectReason = '';
        $this->dispatch('toast', type: 'success', message: "{$count} Vorschläge abgelehnt.");
    }

    public function deleteSuggestion(int $id): void
    {
        $suggestion = $this->resolveSuggestion($id);
        if (! $suggestion) return;

        $companyName = $suggestion->company->name ?? 'Unbekannt';
        $suggestion->delete();

        $this->dispatch('toast', type: 'success', message: "Vorschlag für \"{$companyName}\" gelöscht.");
    }

    // ========================================================================
    // Rendering
    // ========================================================================

    public function render()
    {
        $suggestions = $this->getSuggestionQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);

        $isAdmin = auth()->user()->isAdmin();

        // Status counts for tabs
        $baseQuery = $this->getBaseQuery();
        $statusCounts = [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->pending()->count(),
            'approved' => (clone $baseQuery)->where('status', CompanyEditSuggestion::STATUS_APPROVED)->count(),
            'rejected' => (clone $baseQuery)->where('status', CompanyEditSuggestion::STATUS_REJECTED)->count(),
        ];

        // Company list for filter
        $companies = $this->getCompaniesForFilter();

        // Detail suggestion
        $detailSuggestion = null;
        if ($this->detailSuggestionId) {
            $detailSuggestion = CompanyEditSuggestion::with('company', 'reviewer')->find($this->detailSuggestionId);
        }

        return view('livewire.verwaltung.edit-suggestions-table', compact(
            'suggestions',
            'isAdmin',
            'statusCounts',
            'companies',
            'detailSuggestion',
        ));
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

    private function getBaseQuery()
    {
        $query = CompanyEditSuggestion::with('company');
        $user = auth()->user();

        if (! $user->isAdmin()) {
            $query->whereHas('company', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query;
    }

    private function getSuggestionQuery()
    {
        $query = $this->getBaseQuery();

        // Status filter
        if ($this->filterStatus === 'pending') {
            $query->pending();
        } elseif ($this->filterStatus === 'approved') {
            $query->where('status', CompanyEditSuggestion::STATUS_APPROVED);
        } elseif ($this->filterStatus === 'rejected') {
            $query->where('status', CompanyEditSuggestion::STATUS_REJECTED);
        }

        // Field filter
        if ($this->filterField !== '') {
            $query->where('field', $this->filterField);
        }

        // Company filter
        if ($this->filterCompany !== '') {
            $query->where('company_id', (int) $this->filterCompany);
        }

        // Search
        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('suggested_value', 'like', $search)
                    ->orWhere('reason', 'like', $search)
                    ->orWhere('reporter_name', 'like', $search)
                    ->orWhereHas('company', fn ($q2) => $q2->where('name', 'like', $search));
            });
        }

        return $query;
    }

    private function getCompaniesForFilter(): array
    {
        $user = auth()->user();

        $query = \App\Models\Portal\Company::query();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    private function resolveSuggestion(?int $id): ?CompanyEditSuggestion
    {
        if (! $id) return null;

        $suggestion = CompanyEditSuggestion::with('company')->find($id);

        if (! $suggestion || ! $this->canAccessSuggestion($suggestion)) {
            return null;
        }

        return $suggestion;
    }

    private function canAccessSuggestion(CompanyEditSuggestion $suggestion): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        return $suggestion->company && $suggestion->company->user_id === $user->id;
    }
}
