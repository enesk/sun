<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Review;
use Livewire\Component;

/**
 * Recent reviews widget with quick-approve/reject actions.
 *
 * Shows the last 5 reviews across all companies in the tenant.
 * Admin can approve or reject directly from the dashboard.
 */
class RecentReviews extends Component
{
    public function approve(int $reviewId): void
    {
        $review = Review::findOrFail($reviewId);
        $review->update([
            'moderation_status' => Review::STATUS_APPROVED,
            'is_approved' => true,
            'approved_at' => now(),
            'moderated_by' => auth()->id(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Bewertung freigegeben.');
    }

    public function reject(int $reviewId): void
    {
        $review = Review::findOrFail($reviewId);
        $review->update([
            'moderation_status' => Review::STATUS_REJECTED,
            'is_approved' => false,
            'moderated_by' => auth()->id(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Bewertung abgelehnt.');
    }

    public function render()
    {
        $reviews = Review::with('company')
            ->latest()
            ->take(5)
            ->get();

        $pendingCount = Review::where('moderation_status', 'pending')->count();

        return view('livewire.verwaltung.recent-reviews', compact('reviews', 'pendingCount'));
    }
}
