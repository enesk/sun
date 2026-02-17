<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamTenantUser extends Pivot
{
    use CentralConnection;
    public $incrementing = true;
}
