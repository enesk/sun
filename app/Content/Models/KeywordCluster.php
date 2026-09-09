<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Keyword-Cluster: buendelt verwandte Themen und ist der Vorfilter der
 * Kannibalisierungspruefung (#12).
 */
class KeywordCluster extends Model
{
    use TenantConnection;

    protected $fillable = [
        'name',
        'slug',
        'primary_keyword',
        'keywords_json',
        'centroid_json',
        'serp_json',
        'serp_fetched_at',
        'region_scope',
        'region_code',
        'article_count',
        'last_article_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'centroid_json' => 'array',
            'serp_json' => 'array',
            'serp_fetched_at' => 'datetime',
            'article_count' => 'integer',
            'last_article_at' => 'datetime',
        ];
    }

    public function topicCandidates(): HasMany
    {
        return $this->hasMany(TopicCandidate::class, 'cluster_id');
    }

    public function scopeForRegion(Builder $query, string $scope, ?string $code = null): Builder
    {
        return $query->where('region_scope', $scope)
            ->when($code !== null, fn (Builder $q) => $q->where('region_code', $code));
    }

    /**
     * Cluster, die seit $days Tagen keinen Artikel bekommen haben — sie sind
     * die naechsten Kandidaten der Tagesauswahl.
     */
    public function scopeStale(Builder $query, int $days = 30): Builder
    {
        return $query->where(function (Builder $q) use ($days) {
            $q->whereNull('last_article_at')
                ->orWhere('last_article_at', '<', now()->subDays($days));
        });
    }
}
