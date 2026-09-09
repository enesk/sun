<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class FrCity extends Model
{
    use TenantConnection;

    protected $table = 'fr_cities';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'postal_code',
        'region_id',
        'insee_id',
        'checked',
    ];

    protected $casts = [
        'region_id' => 'integer',
        'checked' => 'boolean',
    ];
}
