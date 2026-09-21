<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Guide\Support\GuidePageCache;
use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Ratgeber-Kategorie des Portals (/ratgeber/{kategorie}). Der Import legt
 * fehlende Kategorien automatisch an (#6).
 */
class Category extends Model
{
    use TenantConnection;

    protected $table = 'guide_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'intro_html',
        'meta_title',
        'meta_description',
        'position',
        'refresh_interval_days',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'refresh_interval_days' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Ein geaenderter Slug hinterlaesst eine 301 von der alten auf die neue
     * Kategorieseite (#18) — egal, aus welchem Formular die Aenderung kommt.
     * Jede Aenderung verwirft die Ratgeber-Seiten samt Sitemap und llms.txt,
     * damit Name, Slug und Sichtbarkeit sofort ueberall stimmen.
     */
    protected static function booted(): void
    {
        static::saved(fn () => GuidePageCache::flush());
        static::deleted(fn () => GuidePageCache::flush());

        static::updated(function (self $category): void {
            $old = (string) $category->getOriginal('slug');

            if (! $category->wasChanged('slug') || $old === '' || $old === $category->slug) {
                return;
            }

            Redirect::record(
                route('guide.category', $old, false),
                route('guide.category', $category->slug, false),
                Redirect::REASON_CATEGORY_SLUG,
                (int) $category->getKey(),
            );
        });
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'guide_category_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Post::class, 'guide_category_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }
}
