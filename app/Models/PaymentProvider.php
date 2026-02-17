<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'type',
        'slug',
        'sort',
        'is_enabled_for_new_payments',
    ];
}
