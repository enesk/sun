<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Post extends Model implements HasMedia
{
    use TenantConnection, InteractsWithMedia;

    // ── Status-Konstanten ──

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Entwurf',
        self::STATUS_PUBLISHED => 'Veröffentlicht',
        self::STATUS_ARCHIVED => 'Archiviert',
    ];

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'featured_image',
        'category_id',
        'author_id',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'reading_time_minutes',
        'view_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reading_time_minutes' => 'integer',
        'view_count' => 'integer',
    ];

    // ── Boot ──

    protected static function booted(): void
    {
        static::creating(function (self $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }

            // Auto-calculate reading time
            if ($post->body && $post->reading_time_minutes === 0) {
                $post->reading_time_minutes = self::calculateReadingTime($post->body);
            }
        });

        static::updating(function (self $post) {
            if ($post->isDirty('body')) {
                $post->reading_time_minutes = self::calculateReadingTime($post->body);
            }

            // Auto-set published_at when publishing
            if ($post->isDirty('status') && $post->status === self::STATUS_PUBLISHED && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    // ── Media Library ──

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile();
    }

    // ── Relationships ──

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PostTag::class, 'post_tag');
    }

    // ── Scopes ──

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByTag($query, $tagId)
    {
        return $query->whereHas('tags', fn ($q) => $q->where('post_tags.id', $tagId));
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->whereFullText(['title', 'body'], $term, ['mode' => 'boolean']);
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    // ── Accessors ──

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at
            && $this->published_at->lte(now());
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getFormattedDateAttribute(): string
    {
        $date = $this->published_at ?? $this->created_at;

        return $date->format('d.m.Y');
    }

    public function getExcerptOrTruncatedAttribute(): string
    {
        if (! empty($this->excerpt)) {
            return $this->excerpt;
        }

        return Str::limit(strip_tags($this->body), 200);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured_image');

        if ($media) {
            return $media->getUrl();
        }

        return $this->featured_image;
    }

    // ── Methods ──

    public function publish(): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function unpublish(): void
    {
        $this->update(['status' => self::STATUS_DRAFT]);
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public static function calculateReadingTime(string $text): int
    {
        $wordCount = str_word_count(strip_tags($text));

        return max(1, (int) ceil($wordCount / 200)); // 200 WPM average
    }
}
