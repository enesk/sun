<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class ChCity extends Model
{

    use HasFactory, TenantConnection;

    protected $table = 'ch_cities';

    protected $fillable = [
        'locality_name',
        'postal_code',
        'additional_digit',
        'zip_id',
        'municipality_name',
        'bfs_number',
        'canton',
        'address_share',
        'coord_east',
        'coord_north',
        'language',
        'valid_from',
        'checked',
    ];

    protected $casts = [
        'additional_digit' => 'integer',
        'zip_id' => 'integer',
        'bfs_number' => 'integer',
        'coord_east' => 'decimal:3',
        'coord_north' => 'decimal:3',
        'valid_from' => 'date',
        'checked' => 'boolean',
    ];

    public function scopeByPostalCode($query, string $postalCode)
    {
        return $query->where('postal_code', $postalCode);
    }

    public function scopeByCanton($query, string $canton)
    {
        return $query->where('canton', $canton);
    }

    public function scopeByMunicipality($query, string $municipality)
    {
        return $query->where('municipality_name', $municipality);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('locality_name', 'like', "{$term}%")
              ->orWhere('postal_code', 'like', "{$term}%")
              ->orWhere('municipality_name', 'like', "{$term}%");
        });
    }
}
