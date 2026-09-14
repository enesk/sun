<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Review;
use Livewire\Component;

/**
 * Recent reviews widget with quick-approve/reject actions.
 *
 * Shows the last 5 reviews across all companies in the tenant.
 * Only global admins may approve or reject (#17); other dashboard users see
 * the reviews of their own companies read-only.
 */
class RecentReviews extends Component
{
    public function approve(int $reviewId): void
    {
        $this->authorizeModeration();

        $review = Review::findOrFail($reviewId);
        $review->approve();

        $this->dispatch('toast', type: 'success', message: 'Bewertung freigegeben.');
    }

    public function reject(int $reviewId): void
    {
        $this->authorizeModeration();

        $review = Review::findOrFail($reviewId);
        $review->reject();

        $this->dispatch('toast', type: 'success', message: 'Bewertung abgelehnt.');
    }

    public function render()
    {
        $user = auth()->user();
        $canModerate = Review::canBeModeratedBy($user);

        $baseQuery = Review::query()->when(
            ! $user->isAdmin(),
            fn ($query) => $query->whereHas('company', fn ($q) => $q->where('user_id', $user->id)),
        );

        $reviews = (clone $baseQuery)
            ->with('company')
            ->latest()
            ->take(5)
            ->get();

        $pendingCount = (clone $baseQuery)->awaitingModeration()->count();

        return view('livewire.verwaltung.recent-reviews', compact('reviews', 'pendingCount', 'canModerate'));
    }

    private function authorizeModeration(): void
    {
        abort_unless(Review::canBeModeratedBy(auth()->user()), 403, 'Bewertungen moderieren nur Administratoren.');
    }
}
