<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class JobApplication extends Model implements HasMedia
{
    use TenantConnection, InteractsWithMedia;

    // ── Status ──

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Neu',
        self::STATUS_REVIEWED => 'Gesehen',
        self::STATUS_CONTACTED => 'Kontaktiert',
        self::STATUS_REJECTED => 'Abgelehnt',
    ];

    protected $fillable = [
        'job_id',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'message',
        'status',
        'ip_address',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // ── Media Collections ──

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }

    // ── Relationships ──

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForJob($query, int $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    // ── Accessors ──

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getHasCvAttribute(): bool
    {
        return $this->hasMedia('cv');
    }

    public function getCvUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cv') ?: null;
    }

    // ── Methods ──

    public function markReviewed(): void
    {
        $this->update([
            'status' => self::STATUS_REVIEWED,
            'reviewed_at' => now(),
        ]);
    }

    public function markContacted(): void
    {
        $this->update(['status' => self::STATUS_CONTACTED]);
    }

    public function markRejected(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }
}
