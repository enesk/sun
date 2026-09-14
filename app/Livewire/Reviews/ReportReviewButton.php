<?php

declare(strict_types=1);

namespace App\Livewire\Reviews;

use App\Models\Portal\Review;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Bewertung melden" an jeder oeffentlichen Bewertung (#13).
 *
 * Auch fuer Gaeste, begrenzt ueber den RateLimiter mit report:{user_id} bzw.
 * report:{ip}. Eine Meldung setzt eine freigegebene Bewertung auf
 * needs_review und blendet sie damit bis zur Entscheidung aus; Rating und
 * JSON-LD zieht der ReviewObserver nach. Meldungen auf bereits verborgene
 * Bewertungen werden still quittiert.
 */
class ReportReviewButton extends Component
{
    #[Locked]
    public int $reviewId;

    public bool $open = false;

    public bool $reported = false;

    public string $reason = '';

    public string $comment = '';

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->resetValidation();
    }

    public function report(): void
    {
        $reasons = self::reasons();

        $this->validate([
            'reason' => ['required', Rule::in(array_keys($reasons))],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.required' => 'Bitte wähle einen Grund.',
            'reason.in' => 'Bitte wähle einen Grund.',
            'comment.max' => 'Der Hinweis darf maximal 500 Zeichen lang sein.',
        ]);

        $key = self::rateLimitKey();
        $maxAttempts = (int) config('moderation.reviews.report.max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            $this->addError('reason', "Zu viele Meldungen. Bitte versuch es in {$minutes} Minuten noch einmal.");

            return;
        }

        RateLimiter::hit($key, (int) config('moderation.reviews.report.decay_seconds', 3600));

        $review = Review::query()->find($this->reviewId);

        if ($review?->isApproved()) {
            $comment = trim($this->comment);
            $review->markForReview("Gemeldet: {$reasons[$this->reason]}".($comment !== '' ? " – {$comment}" : ''));

            Log::info('Bewertung gemeldet', [
                'review_id' => $review->id,
                'company_id' => $review->company_id,
                'reason' => $this->reason,
                'user_id' => auth()->id(),
            ]);
        }

        $this->reset('reason', 'comment', 'open');
        $this->reported = true;
    }

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return (array) config('moderation.reviews.report.reasons', []);
    }

    public function render(): View
    {
        return view('livewire.reviews.report-review-button', [
            'reasons' => self::reasons(),
        ]);
    }

    private static function rateLimitKey(): string
    {
        $userId = auth()->id();

        return $userId !== null ? "report:{$userId}" : 'report:'.request()->ip();
    }
}
