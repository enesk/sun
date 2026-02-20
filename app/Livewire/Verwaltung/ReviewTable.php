<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Review;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class ReviewTable extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';
    public string $filterRating = '';
    public string $filterCompany = '';

    // Sort
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    // Bulk
    public array $selected = [];
    public bool $selectAll = false;

    // Reject modal
    public bool $showRejectModal = false;
    public ?int $rejectingReviewId = null;
    public string $rejectReason = '';
    public bool $isBulkReject = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterRating' => ['except' => ''],
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

    public function updatingFilterRating(): void
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
            $this->selected = $this->getReviewQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
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
        $this->filterRating = '';
        $this->filterCompany = '';
        $this->selected = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    // ========================================================================
    // Quick Actions
    // ========================================================================

    public function approveReview(int $reviewId): void
    {
        $review = $this->resolveReview($reviewId);
        if (! $review) return;

        $review->approve(auth()->user()->name);

        $this->dispatch('toast', type: 'success', message: "Bewertung von \"{$review->author_name}\" freigegeben.");
    }

    public function openRejectModal(int $reviewId): void
    {
        $this->rejectingReviewId = $reviewId;
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

        $review = $this->resolveReview($this->rejectingReviewId);
        if (! $review) return;

        $review->reject($this->rejectReason ?: null, auth()->user()->name);

        $this->showRejectModal = false;
        $this->rejectingReviewId = null;
        $this->rejectReason = '';

        $this->dispatch('toast', type: 'success', message: "Bewertung von \"{$review->author_name}\" abgelehnt.");
    }

    // ========================================================================
    // Bulk Actions
    // ========================================================================

    public function bulkApprove(): void
    {
        $reviews = Review::whereIn('id', $this->selected)->get();
        $count = 0;

        foreach ($reviews as $review) {
            if ($this->canAccessReview($review)) {
                $review->approve(auth()->user()->name);
                $count++;
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->dispatch('toast', type: 'success', message: "{$count} Bewertungen freigegeben.");
    }

    public function openBulkRejectModal(): void
    {
        $this->rejectReason = '';
        $this->isBulkReject = true;
        $this->showRejectModal = true;
    }

    private function executeBulkReject(): void
    {
        $reviews = Review::whereIn('id', $this->selected)->get();
        $count = 0;

        foreach ($reviews as $review) {
            if ($this->canAccessReview($review)) {
                $review->reject($this->rejectReason ?: null, auth()->user()->name);
                $count++;
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->showRejectModal = false;
        $this->rejectReason = '';
        $this->dispatch('toast', type: 'success', message: "{$count} Bewertungen abgelehnt.");
    }

    public function deleteReview(int $reviewId): void
    {
        $review = $this->resolveReview($reviewId);
        if (! $review) return;

        $authorName = $review->author_name;
        $review->delete();

        $this->dispatch('toast', type: 'success', message: "Bewertung von \"{$authorName}\" gelöscht.");
    }

    // ========================================================================
    // Rendering
    // ========================================================================

    public function render()
    {
        $reviews = $this->getReviewQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);

        $isAdmin = auth()->user()->isAdmin();

        // Status counts for tabs
        $baseQuery = $this->getBaseQuery();
        $statusCounts = [
            'all' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->pending()->count(),
            'approved' => (clone $baseQuery)->approved()->count(),
            'rejected' => (clone $baseQuery)->rejected()->count(),
        ];

        // Company list for filter
        $companies = $this->getCompaniesForFilter();

        return view('livewire.verwaltung.review-table', compact(
            'reviews',
            'isAdmin',
            'statusCounts',
            'companies',
        ));
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

    private function getBaseQuery()
    {
        $query = Review::with('company');
        $user = auth()->user();

        if (! $user->isAdmin()) {
            $query->whereHas('company', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query;
    }

    private function getReviewQuery()
    {
        $query = $this->getBaseQuery();

        // Status filter
        if ($this->filterStatus === 'pending') {
            $query->pending();
        } elseif ($this->filterStatus === 'approved') {
            $query->approved();
        } elseif ($this->filterStatus === 'rejected') {
            $query->rejected();
        }

        // Rating filter
        if ($this->filterRating !== '') {
            $query->where('rating', (float) $this->filterRating);
        }

        // Company filter
        if ($this->filterCompany !== '') {
            $query->where('company_id', (int) $this->filterCompany);
        }

        // Search
        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('author_name', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('body', 'like', $search)
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

    private function resolveReview(?int $reviewId): ?Review
    {
        if (! $reviewId) return null;

        $review = Review::with('company')->find($reviewId);

        if (! $review || ! $this->canAccessReview($review)) {
            return null;
        }

        return $review;
    }

    private function canAccessReview(Review $review): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        return $review->company && $review->company->user_id === $user->id;
    }
}
