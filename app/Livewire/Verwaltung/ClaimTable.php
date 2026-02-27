<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\ClaimRequest;
use App\Services\ClaimService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin Claim-Moderation Queue.
 *
 * Zeigt alle Claim-Anträge mit Filter-Tabs (Alle/Ausstehend/Genehmigt/Abgelehnt),
 * Suche, und Slide-Over-Detail-Ansicht für Approve/Reject.
 *
 * Ticket: #169 [CLAIM-VER-6]
 */
class ClaimTable extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = 'pending';

    // Sort
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    // Slide-Over
    public bool $showDetail = false;
    public ?int $detailClaimId = null;

    // Reject
    public string $rejectionReason = '';
    public string $rejectionReasonKey = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'pending'],
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
        $this->filterStatus = 'pending';
        $this->resetPage();
    }

    // ========================================================================
    // Slide-Over
    // ========================================================================

    public function openDetail(int $claimId): void
    {
        $this->detailClaimId = $claimId;
        $this->rejectionReason = '';
        $this->rejectionReasonKey = '';
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detailClaimId = null;
        $this->rejectionReason = '';
        $this->rejectionReasonKey = '';
    }

    // ========================================================================
    // Actions
    // ========================================================================

    public function approveClaim(int $claimId): void
    {
        $claim = ClaimRequest::with('company', 'user')->find($claimId);

        if (! $claim || ! $claim->isPending()) {
            $this->dispatch('toast', type: 'error', message: 'Claim-Antrag konnte nicht genehmigt werden.');
            return;
        }

        $claimService = app(ClaimService::class);
        $success = $claimService->approveClaimRequest($claim, auth()->id());

        if ($success) {
            $this->dispatch('toast', type: 'success', message: "Claim für \"{$claim->company->name}\" genehmigt. Firma wurde zugewiesen.");
            $this->closeDetail();
        } else {
            $this->dispatch('toast', type: 'error', message: 'Genehmigung fehlgeschlagen. Bitte prüfen Sie den Log.');
        }
    }

    public function rejectClaim(int $claimId): void
    {
        $claim = ClaimRequest::with('company')->find($claimId);

        if (! $claim || ! $claim->isPending()) {
            $this->dispatch('toast', type: 'error', message: 'Claim-Antrag konnte nicht abgelehnt werden.');
            return;
        }

        // Ablehnungsgrund zusammenbauen
        $reason = $this->rejectionReasonKey && $this->rejectionReasonKey !== 'other'
            ? ClaimRequest::REJECTION_REASONS[$this->rejectionReasonKey] ?? $this->rejectionReason
            : $this->rejectionReason;

        if (empty(trim($reason))) {
            $this->dispatch('toast', type: 'error', message: 'Bitte geben Sie einen Ablehnungsgrund an.');
            return;
        }

        $claimService = app(ClaimService::class);
        $success = $claimService->rejectClaimRequest($claim, auth()->id(), $reason);

        if ($success) {
            $this->dispatch('toast', type: 'success', message: "Claim für \"{$claim->company->name}\" abgelehnt.");
            $this->closeDetail();
        } else {
            $this->dispatch('toast', type: 'error', message: 'Ablehnung fehlgeschlagen.');
        }
    }

    // ========================================================================
    // Rendering
    // ========================================================================

    public function render()
    {
        $claims = $this->getClaimQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);

        // Status counts
        $statusCounts = [
            'all' => ClaimRequest::count(),
            'pending' => ClaimRequest::pending()->count(),
            'approved' => ClaimRequest::approved()->count(),
            'rejected' => ClaimRequest::rejected()->count(),
        ];

        // Detail-Claim laden
        $detailClaim = null;
        if ($this->showDetail && $this->detailClaimId) {
            $detailClaim = ClaimRequest::with(['company', 'user', 'reviewer', 'media'])
                ->find($this->detailClaimId);
        }

        return view('livewire.verwaltung.claim-table', compact(
            'claims',
            'statusCounts',
            'detailClaim',
        ));
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

    private function getClaimQuery()
    {
        $query = ClaimRequest::with(['company', 'user']);

        // Status filter
        if ($this->filterStatus === 'pending') {
            $query->pending();
        } elseif ($this->filterStatus === 'approved') {
            $query->approved();
        } elseif ($this->filterStatus === 'rejected') {
            $query->rejected();
        }

        // Search
        if ($this->search) {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('company', fn ($q2) => $q2->where('name', 'like', $search))
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search));
            });
        }

        return $query;
    }
}
