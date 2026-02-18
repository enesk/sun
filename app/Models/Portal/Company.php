<?php

namespace App\Models\Portal;

use App\Models\User;
use Database\Factories\Portal\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Company extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'street',
        'house_no',
        'zipcode',
        'city_id',
        'tel',
        'email',
        'website',
        'google_places_id',
        'rating',
        'rating_count',
        'is_premium',
        'is_verified',
        'is_active',
        'logo_path',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'rating_count' => 'integer',
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
        });
    }

    // ── Relationships ──

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('moderation_status', Review::STATUS_APPROVED);
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(CompanyOpeningHour::class)->orderBy('day_of_week');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText(['name', 'description'], $term);
    }

    public function scopeInCity($query, int $cityId)
    {
        return $query->where('city_id', $cityId);
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
    }

    // ── Accessors ──

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            trim("{$this->street} {$this->house_no}"),
            trim("{$this->zipcode} {$this->city?->name}"),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Logo-URL mit Fallback-Kette: Media Library → logo_path → null
     */
    public function getLogoUrlAttribute(): ?string
    {
        // 1. Spatie Media Library (preferred)
        $mediaUrl = $this->getFirstMediaUrl('logo', 'medium');
        if ($mediaUrl) {
            return $mediaUrl;
        }

        // 2. Legacy logo_path column
        if ($this->logo_path) {
            return asset($this->logo_path);
        }

        return null;
    }

    /**
     * Thumbnail-URL für kleine Darstellungen (Cards, Listen)
     */
    public function getLogoThumbUrlAttribute(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('logo', 'thumb');
        if ($mediaUrl) {
            return $mediaUrl;
        }

        if ($this->logo_path) {
            return asset($this->logo_path);
        }

        return null;
    }

    /**
     * Zufälliges Bild für Card-Darstellung: Galerie → Logo → null
     */
    public function getCardImageUrlAttribute(): ?string
    {
        $galleryMedia = $this->getMedia('gallery');

        if ($galleryMedia->isNotEmpty()) {
            return $galleryMedia->random()->getUrl('medium');
        }

        return $this->logo_url;
    }

    // ── Methods ──

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(400)
            ->sharpen(5)
            ->nonQueued();
    }

    public function recalculateRating(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        $this->update([
            'rating' => round($stats->avg_rating ?? 0, 1),
            'rating_count' => $stats->total ?? 0,
        ]);
    }
}
