<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Kosten- und Token-Logging je Provider-Aufruf (#6). Grundlage des
 * Budget-Guards und der Kostenansicht (#25).
 */
class LlmUsageLog extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'provider',
        'model',
        'operation',
        'reference_type',
        'reference_id',
        'input_tokens',
        'output_tokens',
        'cache_write_tokens',
        'cache_read_tokens',
        'requests',
        'cost_usd',
        'duration_ms',
        'was_successful',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'requests' => 'integer',
            'cost_usd' => 'float',
            'duration_ms' => 'integer',
            'was_successful' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOnDay(Builder $query, ?\DateTimeInterface $day = null): Builder
    {
        return $query->whereDate('created_at', $day ?? now());
    }

    public function scopeForReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    /**
     * Bereits verbrauchter Betrag in USD — die Frage des Budget-Guards.
     */
    public static function costForDay(?\DateTimeInterface $day = null, ?string $provider = null, ?int $tenantId = null): float
    {
        return (float) static::query()
            ->onDay($day)
            ->when($provider !== null, fn (Builder $q) => $q->forProvider($provider))
            ->when($tenantId !== null, fn (Builder $q) => $q->forTenant($tenantId))
            ->sum('cost_usd');
    }

    /**
     * Verbrauch eines einzelnen Bezugsobjekts, etwa eines Entwurfs — Grundlage
     * der Reissleine content.budget.max_usd_per_article.
     */
    public static function costForReference(string $type, int $id): float
    {
        return (float) static::query()->forReference($type, $id)->sum('cost_usd');
    }
}
