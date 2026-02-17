<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'transaction_id',
        'status',
        'filename',
    ];
}
