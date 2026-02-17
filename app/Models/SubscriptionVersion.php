<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Mpociot\Versionable\Version;

class SubscriptionVersion extends Version
{
    use CentralConnection;
    public $table = 'subscription_versions';
}
