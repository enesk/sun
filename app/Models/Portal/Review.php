<?php

namespace App\Models\Portal;

use App\Enums\ModerationStatus;
use App\Models\User;
use App\Observers\ReviewObserver;
use Database\Factories\Portal\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Bewertung eines Betriebs.
 *
 * Moderation ist fail-closed (#13): neu = pending, oeffentlich sichtbar
 * (Profil, Rating, JSON-LD) ausschliesslich approved. moderation_status bleibt
 * bewusst ohne Enum-Cast, weil Views und Tabellen den Rohwert vergleichen;
 * die Werte kommen aus App\Enums\ModerationStatus.
 */
#[ObservedBy(ReviewObserver::class)]
class Review extends Model
{
    use HasFactory, TenantConnection;

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'company_id',
        'user_id',
        'author_name',
        'rating',
        'title',
        'body',
        'is_approved',
        'approved_at',
        'moderation_status',
        'moderation_reason',
        'moderation_note',
        'moderated_at',
        'moderated_by',
        'moderated_by_name',
        'owner_response',
        'owner_response_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'moderated_at' => 'datetime',
        'moderated_by' => 'integer',
        'owner_response_at' => 'datetime',
    ];

    /**
     * Moderieren (freigeben, ablehnen, Status aendern, loeschen) duerfen nur
     * globale Administratoren (#17). Firmeninhaber sehen die Bewertungen ihrer
     * Betriebe und antworten ueber /firmenprofil, sonst koennten sie Kritik
     * und Meldungen gegen sich selbst wegmoderieren.
     */
    public static function canBeModeratedBy(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_PENDING);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_NEEDS_REVIEW);
    }

    /**
     * Alles, was noch eine Entscheidung braucht: pending + needs_review.
     */
    public function scopeAwaitingModeration(Builder $query): Builder
    {
        return $query->whereIn('moderation_status', ModerationStatus::openValues());
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('moderation_status', self::STATUS_REJECTED);
    }

    public function approve(?string $moderatedBy = null): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
            'moderation_status' => self::STATUS_APPROVED,
            'moderation_reason' => null,
            ...$this->moderatorAttributes($moderatedBy),
        ]);
    }

    public function reject(?string $reason = null, ?string $moderatedBy = null): void
    {
        $this->update([
            'is_approved' => false,
            'approved_at' => null,
            'moderation_status' => self::STATUS_REJECTED,
            'moderation_reason' => $reason !== null ? mb_strimwidth($reason, 0, 255, '…') : $this->moderation_reason,
            'moderation_note' => $reason,
            ...$this->moderatorAttributes($moderatedBy),
        ]);
    }

    /**
     * Zurueck in die Pruefung (Heuristik, Meldung). Blendet die Bewertung aus.
     */
    public function markForReview(string $reason): void
    {
        $this->update([
            'is_approved' => false,
            'moderation_status' => self::STATUS_NEEDS_REVIEW,
            'moderation_reason' => mb_strimwidth($reason, 0, 255, '…'),
        ]);
    }

    public function isPending(): bool
    {
        return $this->moderation_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->moderation_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->moderation_status === self::STATUS_REJECTED;
    }

    public function needsReview(): bool
    {
        return $this->moderation_status === self::STATUS_NEEDS_REVIEW;
    }

    public function isAwaitingModeration(): bool
    {
        return in_array($this->moderation_status, ModerationStatus::openValues(), true);
    }

    public function respondAsOwner(string $response): void
    {
        $this->update([
            'owner_response' => $response,
            'owner_response_at' => now(),
        ]);
    }

    /**
     * users.id liegt in der Central-DB; der Name wird fuer die Anzeige mitgeschrieben.
     *
     * @return array{moderated_at: \Illuminate\Support\Carbon, moderated_by: int|null, moderated_by_name: string|null}
     */
    private function moderatorAttributes(?string $moderatedBy): array
    {
        $user = auth()->user();

        return [
            'moderated_at' => now(),
            'moderated_by' => $user?->getAuthIdentifier(),
            'moderated_by_name' => $moderatedBy ?? $user?->name,
        ];
    }
}
