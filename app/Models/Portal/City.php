<?php

namespace App\Models\Portal;

use Database\Factories\Portal\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class City extends Model
{
    use HasFactory, TenantConnection;

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

    public function cityContent(): HasOne
    {
        return $this->hasOne(CityContent::class);
    }

    /**
     * Ortsnamen, die kein Ort sind (#4): Importskripte schreiben null als Text
     * ("None" aus Python, "null"/"undefined" aus JavaScript, "nan" aus pandas).
     * Verglichen wird getrimmt und kleingeschrieben.
     */
    public const PLACEHOLDER_NAMES = ['none', 'null', 'nan', 'undefined'];

    public static function isPlaceholderName(?string $name): bool
    {
        $normalized = mb_strtolower(trim((string) $name));

        return $normalized === '' || in_array($normalized, self::PLACEHOLDER_NAMES, true);
    }

    /**
     * Nur Orte mit echtem Namen — fuer alle oeffentlichen Ortslisten.
     */
    public function scopeNamed($query)
    {
        return $query
            ->whereNotNull($this->qualifyColumn('name'))
            ->whereRaw("TRIM({$this->qualifyColumn('name')}) <> ''")
            ->whereRaw("LOWER(TRIM({$this->qualifyColumn('name')})) NOT IN (".implode(',', array_fill(0, count(self::PLACEHOLDER_NAMES), '?')).')', self::PLACEHOLDER_NAMES);
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
