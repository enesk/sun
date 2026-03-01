<?php

namespace App\Models\Portal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ClaimRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    // ── Status-Konstanten ──

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Ausstehend',
        self::STATUS_APPROVED => 'Genehmigt',
        self::STATUS_REJECTED => 'Abgelehnt',
        self::STATUS_CANCELLED => 'Storniert',
    ];

    // ── Häufige Ablehnungsgründe (für Admin-Dropdown) ──

    public const REJECTION_REASONS = [
        'document_unclear' => 'Dokument nicht lesbar / zu unscharf',
        'document_invalid' => 'Kein gültiger Nachweis (z.B. kein Gewerbeschein)',
        'name_mismatch' => 'Name stimmt nicht mit Firmeneintrag überein',
        'address_mismatch' => 'Adresse stimmt nicht mit Firmeneintrag überein',
        'already_claimed' => 'Firma wurde bereits von jemand anderem übernommen',
        'other' => 'Sonstiger Grund (siehe Kommentar)',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'comment',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // ── Media Library: Verifizierungsdokumente ──

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('claim_documents')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
            ])
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->nonQueued();
    }

    // ── Relationships ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Status-Checks ──

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // ── Status-Transitionen (State Machine) ──

    public function approve(int $reviewerId): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    public function reject(int $reviewerId, string $reason): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    public function resubmit(?string $comment = null): bool
    {
        if (!$this->isRejected()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PENDING,
            'comment' => $comment ?? $this->comment,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return true;
    }

    public function cancel(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->update(['status' => self::STATUS_CANCELLED]);

        return true;
    }

    // ── Accessors ──

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getHasDocumentsAttribute(): bool
    {
        return $this->getMedia('claim_documents')->isNotEmpty();
    }

    public function getDocumentCountAttribute(): int
    {
        return $this->getMedia('claim_documents')->count();
    }
}
