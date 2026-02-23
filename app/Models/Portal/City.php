<?php

namespace App\Models\Portal;

use Database\Factories\Portal\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class City extends Model
{
    use HasFactory;

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

    protected $fillable = [
        'name',
        'zipcode',
        'administrative_area_level_1',
        'latitude',
        'longitude',
        'community',
        'slug',
        'checked',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'checked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (City $city) {
            if (empty($city->slug)) {
                $city->slug = Str::slug($city->name);
            }
        });
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function scopeByState($query, string $state)
    {
        return $query->where('administrative_area_level_1', $state);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "{$term}%")
              ->orWhere('zipcode', 'like', "{$term}%");
        });
    }

    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 25)
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance');
    }
}
