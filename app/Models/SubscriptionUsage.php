<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Model;

class SubscriptionUsage extends Model
{
    use CentralConnection;
    protected $fillable = [
        'subscription_id',
        'unit_count',
    ];
}
