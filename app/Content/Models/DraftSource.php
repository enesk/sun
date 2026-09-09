<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Belegte Quelle eines Entwurfs — Grundlage der Quellenzeile im Frontend (#27)
 * und des Faktenchecks (#15).
 *
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class DraftSource extends Model
{
    use TenantConnection;

    protected $fillable = [
        'article_draft_id',
        'source_item_id',
        'title',
        'url',
        'publisher',
        'snippet',
        'published_at',
        'is_cited',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_cited' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ArticleDraft::class, 'article_draft_id');
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }

    public function scopeCited(Builder $query): Builder
    {
        return $query->where('is_cited', true)->orderBy('sort_order');
    }
}
