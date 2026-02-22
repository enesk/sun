<?php

namespace App\Models\Portal;

use Database\Factories\Portal\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

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
        'moderation_note',
        'moderated_by',
        'owner_response',
        'owner_response_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'owner_response_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (Review $review) {
            $review->company->recalculateRating();
        });

        static::deleted(function (Review $review) {
            $review->company->recalculateRating();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('moderation_status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('moderation_status', self::STATUS_PENDING);
    }

    public function approve(?string $moderatedBy = null): void
    {
        $this->update([
            'is_approved' => true,
            'approved_at' => now(),
            'moderation_status' => self::STATUS_APPROVED,
            'moderated_by' => $moderatedBy ?? auth()->user()?->name,
        ]);
    }

    public function reject(?string $reason = null, ?string $moderatedBy = null): void
    {
        $this->update([
            'is_approved' => false,
            'approved_at' => null,
            'moderation_status' => self::STATUS_REJECTED,
            'moderation_note' => $reason,
            'moderated_by' => $moderatedBy ?? auth()->user()?->name,
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

    public function respondAsOwner(string $response): void
    {
        $this->update([
            'owner_response' => $response,
            'owner_response_at' => now(),
        ]);
    }

    public function scopeRejected($query)
    {
        return $query->where('moderation_status', self::STATUS_REJECTED);
    }
}
