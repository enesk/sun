<?php

namespace App\Models\Portal;

use App\Constants\CompanyInquiryStatus;
use Database\Factories\Portal\CompanyInquiryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Kopie einer Anfrage aus dem Leadsystem (#32). Gespeichert wird immer am
 * Betrieb, auch wenn das Profil noch keinen Inhaber hat.
 *
 * @property int $id
 * @property int $company_id
 * @property string $lead_uuid
 * @property CompanyInquiryStatus $status
 * @property list<array{key: string, label: string, value: mixed, value_label: string|null}> $answers
 * @property bool $contact_visible
 * @property \Illuminate\Support\Carbon|null $contact_purged_at
 * @property-read Company|null $company
 */
class CompanyInquiry extends Model
{
    use HasFactory, SoftDeletes, TenantConnection;

    protected static function newFactory(): CompanyInquiryFactory
    {
        return CompanyInquiryFactory::new();
    }

    protected $fillable = [
        'company_id',
        'lead_uuid',
        'status',
        'answers',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_visible',
        'score',
        'result_key',
        'received_at',
        'read_at',
    ];

    protected $attributes = [
        'status' => 'new',
        'contact_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyInquiryStatus::class,
            'answers' => 'array',
            'contact_visible' => 'boolean',
            'score' => 'integer',
            'received_at' => 'datetime',
            'read_at' => 'datetime',
            'contact_purged_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWithStatus(Builder $query, CompanyInquiryStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Faellig fuer leads:inquiries:purge-contacts; Stichtag ist der Eingang. */
    public function scopeDueForPurge(Builder $query, int $months): Builder
    {
        return $query->whereNull('contact_purged_at')
            ->where('received_at', '<', now()->subMonths($months));
    }

    public function isPurged(): bool
    {
        return $this->contact_purged_at !== null;
    }

    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill([
            'status' => $this->status === CompanyInquiryStatus::NEW ? CompanyInquiryStatus::READ : $this->status,
            'read_at' => now(),
        ])->save();
    }
}
