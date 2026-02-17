<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionDiscount extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'subscription_id',
        'is_recurring',
        'type',
        'amount',
        'valid_until',
    ];
}
