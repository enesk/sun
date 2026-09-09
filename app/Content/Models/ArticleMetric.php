<?php

declare(strict_types=1);

namespace App\Content\Models;

use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Tagesmetriken je veroeffentlichtem Ratgeber (#23, #24).
 *
 * Eine Zeile je Artikel und Erhebungstag; `date` ist das Ende des
 * Search-Console-Fensters, `window_days` dessen Laenge. `pageviews` und
 * `adsense_revenue_usd` sind null, solange keine AdSense-Zuordnung besteht —
 * null heisst "nicht gemessen", 0 heisst "kein Ertrag".
 */
class ArticleMetric extends Model
{
    use TenantConnection;

    protected $fillable = [
        'article_id',
        'article_draft_id',
        'date',
        'window_days',
        'impressions',
        'clicks',
        'ctr',
        'position',
        'pageviews',
        'adsense_revenue_usd',
        'is_d7',
        'is_d30',
        'is_d90',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'window_days' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'pageviews' => 'integer',
            'ctr' => 'float',
            'position' => 'float',
            'adsense_revenue_usd' => 'float',
            'is_d7' => 'boolean',
            'is_d30' => 'boolean',
            'is_d90' => 'boolean',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ArticleDraft::class, 'article_draft_id');
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForArticle(Builder $query, int $articleId): Builder
    {
        return $query->where('article_id', $articleId);
    }
}
