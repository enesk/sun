<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use Illuminate\Database\Eloquent\Model;

class VerificationProvider extends Model
{
    use CentralConnection;
    protected $fillable = [
        'name',
        'slug',
    ];
}
