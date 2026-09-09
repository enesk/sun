<?php

declare(strict_types=1);

namespace App\Content\Models;

use App\Content\Enums\DisplayStatus;
use App\Content\Enums\TopicStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Themenkandidat mit Scoring (#12).
 *
 * @property TopicStatus $status
 * @property KeywordCluster|null $cluster
 * @property array<int, int>|null $source_item_ids_json
 * @property \Illuminate\Support\Carbon|null $selected_for_date
 */
class TopicCandidate extends Model
{
    use TenantConnection;

    protected $fillable = [
        'title',
        'primary_keyword',
        'secondary_keywords_json',
        'source_item_ids_json',
        'cluster_id',
        'intent',
        'region_scope',
        'region_code',
        'region_reason',
        'region_evidence_json',
        'informational_only',
        'search_volume',
        'trend_score',
        'demand_score',
        'gap_score',
        'seasonal_score',
        'uniqueness_score',
        'total_score',
        'score_breakdown_json',
        'rationale',
        'simhash',
        'embedding_json',
        'status',
        'rejection_reason',
        'selected_for_date',
    ];

    protected function casts(): array
    {
        return [
            'secondary_keywords_json' => 'array',
            'source_item_ids_json' => 'array',
            'region_evidence_json' => 'array',
            'score_breakdown_json' => 'array',
            'embedding_json' => 'array',
            'informational_only' => 'boolean',
            'search_volume' => 'integer',
            'trend_score' => 'float',
            'demand_score' => 'float',
            'gap_score' => 'float',
            'seasonal_score' => 'float',
            'uniqueness_score' => 'float',
            'total_score' => 'float',
            'status' => TopicStatus::class,
            'selected_for_date' => 'date',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(KeywordCluster::class, 'cluster_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ArticleDraft::class);
    }

    public function factSnippets(): HasMany
    {
        return $this->hasMany(FactSnippet::class);
    }

    /**
     * Die Quellsignale hinter dem Thema. Die IDs liegen als JSON-Liste, weil ein
     * Thema aus beliebig vielen Signalen entsteht.
     */
    public function sourceItems(): \Illuminate\Database\Eloquent\Collection
    {
        return SourceItem::query()
            ->whereIn('id', $this->source_item_ids_json ?? [])
            ->get();
    }

    public function displayStatus(): DisplayStatus
    {
        return DisplayStatus::fromTopic($this->status);
    }

    public function scopeWithStatus(Builder $query, TopicStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeSelectedFor(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('status', TopicStatus::SELECTED->value)
            ->whereDate('selected_for_date', $date);
    }

    /**
     * Bewertete, noch nicht verplante Themen, beste zuerst.
     */
    public function scopeRanked(Builder $query): Builder
    {
        return $query->where('status', TopicStatus::SCORED->value)
            ->orderByDesc('total_score');
    }

    /**
     * Reserve-Kandidaten eines Tages, beste zuerst — die Nachrueckliste des
     * Generators (#14).
     */
    public function scopeReserveFor(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('status', TopicStatus::RESERVE->value)
            ->whereDate('selected_for_date', $date)
            ->orderByDesc('total_score');
    }

    public function scopeForRegion(Builder $query, string $scope, ?string $code = null): Builder
    {
        return $query->where('region_scope', $scope)
            ->when($code !== null, fn (Builder $q) => $q->where('region_code', $code));
    }
}
