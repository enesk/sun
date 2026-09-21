<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Versioniertes Prompt-Template bzw. Branchen-Styleguide (#13).
 * Ein Template je Schluessel ist aktiv; tenantspezifische Templates schlagen
 * globale.
 */
class PromptTemplate extends Model
{
    use CentralConnection;

    protected $fillable = [
        'key',
        'tenant_id',
        'version',
        'name',
        'system_prompt',
        'user_prompt',
        'variables_json',
        'output_schema_json',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'variables_json' => 'array',
            'output_schema_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Das gueltige Template fuer einen Schluessel: tenantspezifisch vor global,
     * hoechste Version zuerst.
     */
    public function scopeResolve(Builder $query, string $key, ?int $tenantId = null): Builder
    {
        return $query->where('is_active', true)
            ->where('key', $key)
            ->where(function (Builder $q) use ($tenantId) {
                $q->whereNull('tenant_id')
                    ->when($tenantId !== null, fn (Builder $inner) => $inner->orWhere('tenant_id', $tenantId));
            })
            ->orderByRaw('tenant_id IS NULL')
            ->orderByDesc('version');
    }
}
