<?php

declare(strict_types=1);

namespace App\Ads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Stundenpuffer des Impression-Zaehlens (Ticket #12, docs/ad-slots.md 14).
 *
 * Kurzlebig: {@see \App\Ads\Jobs\AggregateAdImpressions} raeumt jede
 * abgeschlossene Stunde ins Tagesaggregat und loescht sie hier. Das Model
 * steht fuer das Lesen im Job und in der Diagnose; geschrieben wird der
 * Zaehler von {@see \App\Ads\Support\AdImpressionRecorder} mit einem rohen
 * Upsert, weil Eloquent kein "hochzaehlen oder anlegen" in einem Statement
 * kann.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $hour
 * @property string $slot_key
 * @property int $placement_id
 * @property string $device
 * @property int $impressions
 * @property int $deliveries
 */
class AdImpressionBuffer extends Model
{
    use CentralConnection;

    protected $table = 'ad_impression_buffer';

    protected $fillable = [
        'tenant_id',
        'hour',
        'slot_key',
        'placement_id',
        'device',
        'impressions',
        'deliveries',
    ];

    protected function casts(): array
    {
        return [
            'hour' => 'datetime',
            'placement_id' => 'integer',
            'impressions' => 'integer',
            'deliveries' => 'integer',
        ];
    }
}
