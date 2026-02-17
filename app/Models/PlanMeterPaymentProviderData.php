<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanMeterPaymentProviderData extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'plan_meter_id',
        'payment_provider_id',
        'payment_provider_plan_meter_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
