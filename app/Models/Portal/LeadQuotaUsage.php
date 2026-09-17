<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Lead-Zaehler eines Betriebs je Monat (#3). quota_at_period_start null = unbegrenzt.
 *
 * @property int $id
 * @property int $company_id
 * @property string $period
 * @property int $used_count
 * @property int|null $quota_at_period_start
 * @property-read Company|null $company
 */
class LeadQuotaUsage extends Model
{
    use TenantConnection;

    public const PERIOD_FORMAT = 'Y-m';

    protected $fillable = [
        'company_id',
        'period',
        'used_count',
        'quota_at_period_start',
    ];

    protected $attributes = [
        'used_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
            'quota_at_period_start' => 'integer',
        ];
    }

    public static function periodFor(?Carbon $date = null): string
    {
        return ($date ?? now())->format(self::PERIOD_FORMAT);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }
}
