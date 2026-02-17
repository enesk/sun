<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission {
    use CentralConnection;}
