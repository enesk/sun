<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Projektreferenz eines Betriebs (#13, Feature references).
 * Fotos in der Media-Collection photos, hoechstens
 * premium.profile.reference_photos_max Stueck.
 *
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property int|null $year
 * @property int $sort_order
 * @property-read Company|null $company
 */
class CompanyReference extends Model implements HasMedia
{
    use InteractsWithMedia, TenantConnection;

    public const PHOTO_COLLECTION = 'photos';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'location',
        'year',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 480, 270)
            ->format('webp')
            ->quality(75)
            ->nonQueued();
    }
}
