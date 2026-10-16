<?php

declare(strict_types=1);

namespace App\Ads\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tagesaggregat der eigenen Impressionen (Ticket #12, docs/ad-slots.md 14).
 *
 * Geschrieben wird ausschliesslich von {@see \App\Ads\Jobs\AggregateAdImpressions}
 * — nie aus einem Request. Gelesen von {@see \App\Ads\Support\AdImpressionStats}.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $day
 * @property string $slot_key
 * @property int $placement_id
 * @property string $device
 * @property int $impressions
 * @property int $deliveries
 */
class AdImpressionDaily extends Model
{
    use CentralConnection;

    protected $table = 'ad_impression_daily';

    protected $fillable = [
        'tenant_id',
        'day',
        'slot_key',
        'placement_id',
        'device',
        'impressions',
        'deliveries',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'placement_id' => 'integer',
            'impressions' => 'integer',
            'deliveries' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
