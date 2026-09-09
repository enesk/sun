<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use App\Content\Enums\ContentRole;
use App\Models\Tenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Redaktions-Account des Content-Panels. Eigener Guard, eigene Tabelle —
 * bewusst getrennt von App\Models\User (docs/content-pipeline.md, §6).
 *
 * @property ContentRole $role
 */
class ContentUser extends Authenticatable implements FilamentUser
{
    use CentralConnection, Notifiable;

    protected $table = 'content_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'tenant_ids_json',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => ContentRole::class,
            'is_active' => 'boolean',
            'tenant_ids_json' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $panel->getId() === config('content.panel.id');
    }

    public function isOwner(): bool
    {
        return $this->role === ContentRole::OWNER;
    }

    public function isEditor(): bool
    {
        return $this->role === ContentRole::EDITOR;
    }

    public function canManageSettings(): bool
    {
        return $this->role->canManageSettings();
    }

    public function canSeeCosts(): bool
    {
        return $this->role->canSeeCosts();
    }

    /**
     * Leere Liste bedeutet: Zugriff auf alle Mandanten.
     */
    public function canAccessTenant(int $tenantId): bool
    {
        $allowed = $this->allowedTenantIds();

        return $allowed === null || in_array($tenantId, $allowed, true);
    }

    /**
     * @return array<int, int>|null null = alle Mandanten
     */
    public function allowedTenantIds(): ?array
    {
        $allowed = $this->tenant_ids_json;

        if (empty($allowed)) {
            return null;
        }

        return array_values(array_map('intval', $allowed));
    }

    /**
     * Die Portale, die dieser Account im Tenant-Switcher sehen darf.
     */
    public function accessibleTenants(): TenantCollection
    {
        $allowed = $this->allowedTenantIds();

        return Tenant::query()
            ->when($allowed !== null, fn (Builder $query) => $query->whereIn('id', $allowed))
            ->orderBy('name')
            ->get();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
