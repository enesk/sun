<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class PostTag extends Model
{
    use TenantConnection;

    protected $fillable = [
        'name',
        'slug',
    ];

    // ── Boot ──

    protected static function booted(): void
    {
        static::creating(function (self $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // ── Relationships ──

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag');
    }

    // ── Scopes ──

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    // ── Accessors ──

    public function getPublishedPostCountAttribute(): int
    {
        return $this->posts()->where('status', 'published')->count();
    }
}
