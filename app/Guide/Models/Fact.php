<?php

declare(strict_types=1);

namespace App\Guide\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Ein Fakt aus dem Fakten-Set eines Themas (#8). Ersetzte Werte bleiben mit
 * `is_current = false` stehen; nur aktuelle Fakten gehen in den facts_hash
 * des Themas ein (Topic::calculateFactsHash()). `stale_source`: die Quelle ist
 * aelter als guide.research.stale_after_months, eine neuere gab es nicht.
 *
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property-read Source|null $source
 */
class Fact extends Model
{
    use TenantConnection;

    protected $table = 'guide_facts';

    protected $fillable = [
        'guide_topic_id',
        'key',
        'label',
        'value',
        'unit',
        'valid_from',
        'source_id',
        'stale_source',
        'first_seen_at',
        'last_seen_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'stale_source' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'guide_topic_id');
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
