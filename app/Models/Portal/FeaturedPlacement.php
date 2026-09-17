<?php

namespace App\Models\Portal;

use App\Constants\FeaturedPlacementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Top-Platzierung eines Betriebs in Stadt x Branche (#3). Die Slot-Vergabe
 * (max. 3 aktive) regelt der Slot-Service; active_slot ist eine generierte Spalte.
 *
 * @property int $id
 * @property int $company_id
 * @property int $city_id
 * @property int $category_id
 * @property int $slot
 * @property FeaturedPlacementStatus $status
 * @property string|null $subscription_ref
 * @property-read Company|null $company
 */
class FeaturedPlacement extends Model
{
    use TenantConnection;

    public const MAX_SLOTS = 3;

    protected $fillable = [
        'company_id',
        'city_id',
        'category_id',
        'slot',
        'starts_at',
        'ends_at',
        'status',
        'subscription_ref',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'status' => FeaturedPlacementStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FeaturedPlacementStatus::ACTIVE);
    }

    public function scopeForCityAndCategory(Builder $query, int $cityId, int $categoryId): Builder
    {
        return $query->where('city_id', $cityId)->where('category_id', $categoryId);
    }
}
