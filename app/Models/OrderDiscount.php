<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDiscount extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'order_id',
        'type',
        'amount',
        'valid_until',
    ];
}
