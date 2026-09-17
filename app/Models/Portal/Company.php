<?php

namespace App\Models\Portal;

use App\Enums\PlanTier;
use App\Models\User;
use App\Services\CompanyUrlService;
use App\Services\Seo\StructuredDataService;
use Database\Factories\Portal\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Company extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, TenantConnection;

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
        'social_facebook',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'video_url',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'rating_count' => 'integer',
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'google_added_at' => 'datetime',
        'plan_tier' => PlanTier::class,
        'plan_started_at' => 'datetime',
        'plan_ends_at' => 'datetime',
        'plan_grace_until' => 'datetime',
        'verified_at' => 'datetime',
        'monthly_report_opted_out_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
        });

        // JSON-LD des Profils neu bauen (#8); Bewertungs-Freigaben laufen ueber
        // Review -> recalculateRating() -> update() ebenfalls hier durch.
        static::saved(fn (Company $company) => StructuredDataService::forget($company->id));
        static::deleted(fn (Company $company) => StructuredDataService::forget($company->id));
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

    public function editSuggestions(): HasMany
    {
        return $this->hasMany(CompanyEditSuggestion::class);
    }

    public function pendingEditSuggestions(): HasMany
    {
        return $this->hasMany(CompanyEditSuggestion::class)->where('status', CompanyEditSuggestion::STATUS_PENDING);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function trackingDailyStats(): HasMany
    {
        return $this->hasMany(TrackingDailyStat::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(CompanyInquiry::class);
    }

    // Exklusive Anfragen aus dem Profil-Dialog (#9)
    public function leads(): HasMany
    {
        return $this->hasMany(CompanyLead::class);
    }

    public function featuredPlacements(): HasMany
    {
        return $this->hasMany(FeaturedPlacement::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(CompanyVerification::class);
    }

    public function leadQuotaUsages(): HasMany
    {
        return $this->hasMany(LeadQuotaUsage::class);
    }

    // Profil-Ausbau (#13)

    /**
     * @return HasMany<CompanyReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(CompanyReference::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompanyService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(CompanyService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeJobs(): HasMany
    {
        return $this->hasMany(Job::class)->where('is_active', true)->where('expires_at', '>', now());
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

    /**
     * Aktive Top-Platzierungen (#6) zuerst, nach Slot; die bisherige Sortierung
     * haengt der Aufrufer danach an. Gilt nur fuer die gebuchte Stadt: ohne
     * Stadt bleibt die Reihenfolge unveraendert, ohne Branche zaehlt jede
     * Platzierung des Betriebs in dieser Stadt.
     */
    public function scopeOrderFeaturedFirst($query, ?int $cityId, ?int $categoryId = null)
    {
        if ($cityId === null) {
            return $query;
        }

        $slot = $this->featuredSlotSubquery($cityId, $categoryId);

        return $query->orderByRaw("COALESCE(({$slot->toSql()}), 255)", $slot->getBindings());
    }

    /**
     * Liefert den aktiven Top-Platzierungs-Slot (#6) als Spalte featured_slot
     * mit, damit die Listenkarte (#7) ohne Einzelabfrage das Badge setzt.
     * Gleiche Regeln wie orderFeaturedFirst; ohne Stadt bleibt die Spalte weg.
     */
    public function scopeWithFeaturedSlot($query, ?int $cityId, ?int $categoryId = null)
    {
        if ($cityId === null) {
            return $query;
        }

        if ($query->getQuery()->columns === null) {
            $query->select('companies.*');
        }

        return $query->selectSub($this->featuredSlotSubquery($cityId, $categoryId), 'featured_slot');
    }

    private function featuredSlotSubquery(int $cityId, ?int $categoryId): \Illuminate\Database\Query\Builder
    {
        return FeaturedPlacement::query()
            ->selectRaw('MIN(featured_placements.slot)')
            ->whereColumn('featured_placements.company_id', 'companies.id')
            ->active()
            ->where('featured_placements.city_id', $cityId)
            ->when($categoryId !== null, fn ($sub) => $sub->where('featured_placements.category_id', $categoryId))
            ->toBase();
    }

    // ── URL ──

    /**
     * URL-Slug im Format: {id}-{name-slug}
     * z.B. "1234-rudiger-kurtz-heizungs-u-sanitartechnik"
     */
    public function getUrlSlugAttribute(): string
    {
        return $this->id.'-'.$this->slug;
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
        return $this->card_photo_url ?? $this->logo_url;
    }

    /**
     * Erstes Galerie-Bild ohne Logo-Fallback, z.B. fuer die Listenkarte (#7).
     */
    public function getCardPhotoUrlAttribute(): ?string
    {
        $media = $this->firstGalleryMedia();

        if (! $media) {
            return null;
        }

        // Kleine WebP-Fassung fuer Karten, solange sie nicht nachgeneriert ist 'medium'
        return $media->getUrl($media->hasGeneratedConversion('card') ? 'card' : 'medium');
    }

    /**
     * Erstes Galerie-Bild als Vorschau (150 px), z.B. als Avatar der Listenkarte (#7).
     */
    public function getCardPhotoThumbUrlAttribute(): ?string
    {
        return $this->firstGalleryMedia()?->getUrl('thumb');
    }

    // Nutzt die eager-geladene media-Relation statt getMedia() (vermeidet N+1)
    private function firstGalleryMedia(): ?Media
    {
        return $this->relationLoaded('media')
            ? $this->media->where('collection_name', 'gallery')->sortBy('order_column')->first()
            : $this->getFirstMedia('gallery');
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

        $this->addMediaConversion('card')
            ->fit(Fit::Crop, 480, 270)
            ->format('webp')
            ->quality(75)
            ->nonQueued()
            ->performOnCollections('gallery');

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
