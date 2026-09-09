<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Unverdichtete Search-Console-Zeile: eine Suchanfrage auf einer Seite
 * innerhalb eines 28-Tage-Fensters (#9).
 *
 * Grundlage fuer den Metrik-Collector (#23) und den Refresh-Loop (#24); der
 * Gap-Connector schreibt sie beim taeglichen Abruf mit.
 */
class ArticleMetricRaw extends Model
{
    use TenantConnection;

    protected $table = 'article_metrics_raw';

    protected $fillable = [
        'window_end',
        'window_days',
        'property',
        'page',
        'page_path',
        'page_hash',
        'query',
        'query_hash',
        'impressions',
        'clicks',
        'ctr',
        'position',
        'is_article',
    ];

    protected function casts(): array
    {
        return [
            'window_end' => 'date',
            'window_days' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'ctr' => 'float',
            'position' => 'float',
            'is_article' => 'boolean',
        ];
    }

    public static function hash(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }

    public function scopeForWindow(Builder $query, \DateTimeInterface $windowEnd): Builder
    {
        return $query->whereDate('window_end', $windowEnd);
    }

    public function scopeForPage(Builder $query, string $page): Builder
    {
        return $query->where('page_hash', self::hash($page));
    }

    public function scopeArticlesOnly(Builder $query): Builder
    {
        return $query->where('is_article', true);
    }
}
