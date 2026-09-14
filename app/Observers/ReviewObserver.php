<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Portal\Company;
use App\Models\Portal\Review;
use App\Services\Moderation\ReviewSpamDetector;
use App\Services\Seo\StructuredDataService;

/**
 * Moderation und Rating-Pflege fuer Bewertungen (#13).
 *
 * - creating: neue Bewertungen ohne Status sind pending; die Heuristik stuft
 *   Verdachtsfaelle auf needs_review hoch. Importe mit explizitem approved
 *   bleiben unberuehrt.
 * - saved/deleted: Rating, Counter-Cache (companies.rating/rating_count) und
 *   JSON-LD-Cache des Betriebs werden neu aufgebaut, sobald sich an der
 *   oeffentlich sichtbaren Menge etwas aendert. Deshalb gehoert die
 *   Neuberechnung hierher und nicht in Filament-Actions.
 */
class ReviewObserver
{
    public function creating(Review $review): void
    {
        $status = $review->moderation_status ?: Review::STATUS_PENDING;
        $review->moderation_status = $status;

        if ($status !== Review::STATUS_PENDING) {
            return;
        }

        $review->is_approved = false;
        $reason = ReviewSpamDetector::fromConfig()->reasonFor($review);

        if ($reason === null) {
            return;
        }

        $review->moderation_status = Review::STATUS_NEEDS_REVIEW;
        $review->moderation_reason = $reason;
    }

    public function saved(Review $review): void
    {
        $statusTouched = $review->wasRecentlyCreated || $review->wasChanged('moderation_status');
        $wasApproved = $review->getOriginal('moderation_status') === Review::STATUS_APPROVED;
        $visibleNow = $review->isApproved();

        if ($review->wasChanged('company_id') && ! $review->wasRecentlyCreated) {
            $this->refreshCompany((int) $review->getOriginal('company_id'));
            $this->refreshCompany((int) $review->company_id);

            return;
        }

        $affectsPublic = ($statusTouched && ($wasApproved || $visibleNow))
            || ($visibleNow && $review->wasChanged('rating'));

        if ($affectsPublic) {
            $this->refreshCompany((int) $review->company_id);
        }
    }

    public function deleted(Review $review): void
    {
        if ($review->isApproved()) {
            $this->refreshCompany((int) $review->company_id);
        }
    }

    private function refreshCompany(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }

        Company::query()->find($companyId)?->recalculateRating();

        // recalculateRating() verwirft den Cache nur, wenn sich Werte aendern
        StructuredDataService::forget($companyId);
    }
}
