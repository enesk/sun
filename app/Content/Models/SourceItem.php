<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Normalisiertes Rohsignal eines Quell-Connectors (#7-#11).
 *
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property float $signal_strength
 * @property array<string, mixed>|null $payload_json
 */
class SourceItem extends Model
{
    use TenantConnection;

    protected $fillable = [
        'source_key',
        'source_type',
        'external_id',
        'fingerprint',
        'title',
        'summary',
        'body',
        'url',
        'language',
        'region_scope',
        'region_code',
        'keywords_json',
        'signal_strength',
        'payload_json',
        'published_at',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'signal_strength' => 'float',
            'payload_json' => 'array',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    public function draftSources(): HasMany
    {
        return $this->hasMany(DraftSource::class);
    }

    public function factSnippets(): HasMany
    {
        return $this->hasMany(FactSnippet::class);
    }

    public function scopeFromSource(Builder $query, string $sourceKey): Builder
    {
        return $query->where('source_key', $sourceKey);
    }

    /**
     * Signale, die frisch genug sind, um in die Themenfindung zu gehen.
     */
    public function scopeFetchedSince(Builder $query, \DateTimeInterface $since): Builder
    {
        return $query->where('fetched_at', '>=', $since);
    }

    public function scopeForRegion(Builder $query, string $scope, ?string $code = null): Builder
    {
        return $query->where('region_scope', $scope)
            ->when($code !== null, fn (Builder $q) => $q->where('region_code', $code));
    }
}
