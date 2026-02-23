<?php

namespace App\Models\Portal;

use App\Models\User;
use Database\Factories\Portal\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CompanyUrlService;
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
        'description_source',
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
        'google_added_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'rating_count' => 'integer',
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'google_added_at' => 'datetime',
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
        return $query->whereIn('companies.id', function ($sub) use ($categoryId) {
            $sub->select('company_id')
                ->from('category_company')
                ->where('category_id', $categoryId);
        });
    }

    // ── URL ──

    /**
     * URL-Slug im Format: {id}-{name-slug}
     * z.B. "1234-rudiger-kurtz-heizungs-u-sanitartechnik"
     */
    public function getUrlSlugAttribute(): string
    {
        return $this->id . '-' . $this->slug;
    }

    /**
     * Parse die Company-ID aus einem URL-Slug wie "1234-firmen-name".
     */
    public static function findByUrlSlug(string $urlSlug): ?self
    {
        // Extrahiere die ID vor dem ersten Bindestrich
        $id = (int) Str::before($urlSlug, '-');

        if ($id <= 0) {
            return null;
        }

        return static::find($id);
    }

    /**
     * Vollständige Portal-URL basierend auf Tenant-URL-Pattern.
     */
    public function getPortalUrlAttribute(): string
    {
        return CompanyUrlService::url($this);
    }

    /**
     * Relativer Pfad basierend auf Tenant-URL-Pattern (für Sitemap).
     */
    public function getPortalPathAttribute(): string
    {
        return CompanyUrlService::path($this);
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
     * Cover/Banner-URL: Media Library → null
     */
    public function getCoverUrlAttribute(): ?string
    {
        if ($this->relationLoaded('media')) {
            $cover = $this->media->where('collection_name', 'cover')->first();
            if ($cover) {
                return $cover->getUrl('banner');
            }
            return null;
        }

        $mediaUrl = $this->getFirstMediaUrl('cover', 'banner');
        return $mediaUrl ?: null;
    }

    /**
     * Erstes Galerie-Bild für Card-Darstellung: Galerie → Logo → null.
     * Nutzt die eager-geladene media-Relation statt getMedia() (vermeidet N+1).
     */
    public function getCardImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('media')) {
            $gallery = $this->media->where('collection_name', 'gallery');
            if ($gallery->isNotEmpty()) {
                return $gallery->first()->getUrl('medium');
            }
        } else {
            $firstGallery = $this->getFirstMediaUrl('gallery', 'medium');
            if ($firstGallery) {
                return $firstGallery;
            }
        }

        return $this->logo_url;
    }

    // ── Methods ──

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('cover')
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

        $this->addMediaConversion('banner')
            ->width(1200)
            ->height(400)
            ->sharpen(5)
            ->nonQueued()
            ->performOnCollections('cover');
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
