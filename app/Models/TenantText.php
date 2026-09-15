<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantText extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'locale',
        'group',
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::saved(fn (TenantText $text) => $text->forgetCache());
        static::deleted(fn (TenantText $text) => $text->forgetCache());
    }

    public static function cacheKey(int|string $tenantId, string $locale, string $group): string
    {
        return "tenant:{$tenantId}:texts:{$locale}:{$group}";
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = tenant()?->getKey();

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('tenant_id'), $tenantId);
    }

    /**
     * Leert den aktuellen und, nach einer Verschiebung auf anderen Tenant,
     * Sprache oder Gruppe, auch den vorherigen Cache-Eintrag.
     *
     * Der Loader (#4) schreibt im Tenant-Kontext, und dort zeigt der
     * Cache-Store woanders hin (SetTenantStorageUrl lenkt den file-Store auf
     * storage/tenant<uuid>). Gepflegt wird aber zentral im Admin-Panel —
     * deshalb wird im Kontext des jeweiligen Tenants geleert.
     */
    public function forgetCache(): void
    {
        self::forgetFor($this->tenant_id, $this->locale, $this->group);

        if (! $this->wasChanged(['tenant_id', 'locale', 'group'])) {
            return;
        }

        self::forgetFor(
            $this->getOriginal('tenant_id'),
            $this->getOriginal('locale'),
            $this->getOriginal('group'),
        );
    }

    protected static function forgetFor(int|string $tenantId, string $locale, string $group): void
    {
        $key = self::cacheKey($tenantId, $locale, $group);

        Cache::forget($key);

        if ((string) tenant()?->getKey() === (string) $tenantId) {
            return;
        }

        Tenant::query()->find($tenantId)?->run(fn () => Cache::forget($key));
    }
}
