<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Fingerprint eines veroeffentlichten Ratgebers — Grundlage der
 * tenantuebergreifenden Duplikats- und Kannibalisierungspruefung (#12, #21).
 *
 * MariaDB hat keinen Vektor-Index. Der Ablauf ist deshalb zweistufig:
 * grob ueber die SimHash-Hamming-Distanz vorfiltern, dann in PHP die
 * Cosine-Aehnlichkeit der Embeddings rechnen.
 */
class ContentFingerprint extends Model
{
    use CentralConnection;

    /** Maximale Hamming-Distanz, ab der zwei Texte als aehnlich gelten. */
    public const HAMMING_THRESHOLD = 12;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'url',
        'simhash',
        'embedding_json',
        'primary_keyword',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'article_id' => 'integer',
            'embedding_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithKeyword(Builder $query, string $keyword): Builder
    {
        return $query->where('primary_keyword', $keyword);
    }

    /**
     * Hamming-Distanz zweier 64-Bit-SimHashes.
     */
    public static function hammingDistance(int|string $a, int|string $b): int
    {
        return substr_count(decbin((int) $a ^ (int) $b), '1');
    }

    public function isSimilarTo(int|string $simhash, int $threshold = self::HAMMING_THRESHOLD): bool
    {
        return self::hammingDistance($this->simhash, $simhash) <= $threshold;
    }
}
