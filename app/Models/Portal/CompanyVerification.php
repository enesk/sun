<?php

namespace App\Models\Portal;

use App\Constants\CompanyVerificationDocumentType;
use App\Constants\CompanyVerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Eingereichter Nachweis fuer das Verifiziert-Badge (#3).
 * reviewed_by_user_id verweist auf users der Central-DB (kein FK, keine Relation).
 *
 * @property int $id
 * @property int $company_id
 * @property string $document_path
 * @property CompanyVerificationDocumentType $document_type
 * @property CompanyVerificationStatus $status
 * @property int|null $reviewed_by_user_id
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $document_purged_at
 * @property-read Company|null $company
 */
class CompanyVerification extends Model
{
    use TenantConnection;

    protected $fillable = [
        'company_id',
        'document_path',
        'document_type',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'document_purged_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => CompanyVerificationDocumentType::class,
            'status' => CompanyVerificationStatus::class,
            'reviewed_by_user_id' => 'integer',
            'reviewed_at' => 'datetime',
            'document_purged_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isPending(): bool
    {
        return $this->status === CompanyVerificationStatus::PENDING;
    }

    public function hasDocument(): bool
    {
        return $this->document_purged_at === null && $this->document_path !== '';
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->document_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CompanyVerificationStatus::PENDING);
    }
}
