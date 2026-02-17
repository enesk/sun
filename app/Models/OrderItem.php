<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'order_id',
        'one_time_product_id',
        'quantity',
        'currency_id',
        'price_per_unit',
        'price_per_unit_after_discount',
        'discount_per_unit',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function oneTimeProduct(): BelongsTo
    {
        return $this->belongsTo(OneTimeProduct::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
