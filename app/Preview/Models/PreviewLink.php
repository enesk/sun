<?php

declare(strict_types=1);

namespace App\Preview\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Vorschau-Link (SUN-PV-001, Ticket #2).
 *
 * Liegt in der Central-DB. Der Klartext-Token wird nie gespeichert, nur
 * sein sha256-Hash ({@see \App\Preview\PreviewTokenService::hash()}).
 *
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property bool $applies_to_all_tenants
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_used_at
 * @property int $use_count
 * @property int|null $created_by
 */
class PreviewLink extends Model
{
    use CentralConnection;

    protected $table = 'preview_links';

    protected $fillable = [
        'name',
        'token_hash',
        'applies_to_all_tenants',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_all_tenants' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'preview_link_tenant');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Nicht abgelaufen und nicht widerrufen.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Gilt fuer alle Portale oder ist fuer dieses Portal ausgewaehlt.
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $query->where(function (Builder $query) use ($tenantId) {
            $query->where('applies_to_all_tenants', true)
                ->orWhereHas('tenants', fn (Builder $query) => $query->whereKey($tenantId));
        });
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
