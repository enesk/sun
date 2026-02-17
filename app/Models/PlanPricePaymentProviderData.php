<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPricePaymentProviderData extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'plan_price_id',
        'payment_provider_id',
        'payment_provider_price_id',
        'type',
    ];
}
