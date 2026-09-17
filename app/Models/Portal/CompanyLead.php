<?php

namespace App\Models\Portal;

use App\Constants\CompanyLeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Exklusive Anfrage an genau einen Betrieb (#9). Angelegt nur ueber
 * App\Services\Premium\LeadRoutingService::deliverExclusive().
 *
 * @property int $id
 * @property int $company_id
 * @property CompanyLeadStatus $status
 * @property list<array{key: string, label: string, type: string, value: mixed, value_label: string|null}>|null $answers
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $contact_purged_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Company|null $company
 */
class CompanyLead extends Model
{
    use TenantConnection;

    protected $fillable = [
        'company_id',
        'status',
        'answers',
        'contact_name',
        'contact_email',
        'contact_phone',
        'funnel_version',
        'quota_period',
        'read_at',
        'status_changed_at',
        'contact_purged_at',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyLeadStatus::class,
            'answers' => 'array',
            'funnel_version' => 'integer',
            'read_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'contact_purged_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWithStatus(Builder $query, CompanyLeadStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDueForPurge(Builder $query, int $months): Builder
    {
        return $query->whereNull('contact_purged_at')
            ->where('created_at', '<', now()->subMonths($months));
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

        $this->forceFill(['read_at' => now()])->save();
    }
}
