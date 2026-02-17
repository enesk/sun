<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Mpociot\Versionable\Version;

class TransactionVersion extends Version
{
    use CentralConnection;
    public $table = 'transaction_versions';
}
