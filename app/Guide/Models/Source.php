<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Guide\Enums\TrustLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Bewertete Quelle eines Themas (#8).
 *
 * Seit #11 traegt die Quelle das Ergebnis des Link-Checks im Qualitaetsgate:
 * `broken_at` ist gesetzt, solange die URL mit 4xx/5xx antwortet.
 *
 * @property TrustLevel $trust_level
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $retrieved_at
 * @property \Illuminate\Support\Carbon|null $link_checked_at
 * @property int|null $link_status_code
 * @property \Illuminate\Support\Carbon|null $broken_at
 */
class Source extends Model
{
    use TenantConnection;

    protected $table = 'guide_sources';

    protected $fillable = [
        'guide_topic_id',
        'url',
        'title',
        'publisher',
        'published_at',
        'retrieved_at',
        'trust_level',
        'link_checked_at',
        'link_status_code',
        'broken_at',
    ];

    protected $attributes = [
        'trust_level' => 'other',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'retrieved_at' => 'datetime',
            'trust_level' => TrustLevel::class,
            'link_checked_at' => 'datetime',
            'link_status_code' => 'integer',
            'broken_at' => 'datetime',
        ];
    }

    public function isBroken(): bool
    {
        return $this->broken_at !== null;
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'guide_topic_id');
    }

    /**
     * @return HasMany<Fact, $this>
     */
    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class, 'source_id');
    }
}
