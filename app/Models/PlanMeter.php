<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanMeter extends Model
{
    use CentralConnection;
    use HasFactory;

    protected $fillable = [
        'name',
    ];
}
