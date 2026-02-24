<?php

namespace App\Models\Portal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEditSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const FIELD_ADDRESS = 'address';
    public const FIELD_PHONE = 'phone';
    public const FIELD_HOURS = 'hours';
    public const FIELD_DESCRIPTION = 'description';
    public const FIELD_OTHER = 'other';

    public const FIELDS = [
        self::FIELD_ADDRESS => 'Adresse',
        self::FIELD_PHONE => 'Telefonnummer',
        self::FIELD_HOURS => 'Öffnungszeiten',
        self::FIELD_DESCRIPTION => 'Beschreibung',
        self::FIELD_OTHER => 'Sonstiges',
    ];

    protected $fillable = [
        'company_id',
        'field',
        'suggested_value',
        'reason',
        'reporter_name',
        'reporter_email',
        'status',
        'ip_address',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Accessors ──

    public function getFieldLabelAttribute(): string
    {
        return self::FIELDS[$this->field] ?? $this->field;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
