<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Permission\Traits\HasRoles;

class TenantUser extends Pivot
{
    use CentralConnection;
    /* Adding the HasRoles trait to the TenantUser model allows us to use the Spatie Permission package to manage roles and permissions for tenant users. */

    use HasRoles;

    protected string $guard_name = 'web';

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_tenant_user', 'tenant_user_id', 'team_id')->withPivot('id')->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
