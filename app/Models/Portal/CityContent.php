<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class CityContent extends Model
{
    use TenantConnection;

    protected $fillable = [
        'city_id',
        'intro_text',
        'meta_title',
        'meta_description',
        'is_generated',
        'generated_at',
    ];

    protected $casts = [
        'is_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
