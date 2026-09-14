<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\PostCategory;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Footer von sun-v2: die Spalte "Ratgeber" zeigt die drei Ratgeber-Kategorien
 * mit den meisten veroeffentlichten Artikeln statt fester Adressen, die es
 * im Portal nicht gibt.
 */
final class FooterViewComposer
{
    public function compose(View $view): void
    {
        $categories = Cache::remember(TenantCache::key('sun-v2.footer.post_categories'), 3600, fn () => PostCategory::query()
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->having('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->take(3)
            ->get(['id', 'name', 'slug']));

        $view->with('sunFooterPostCategories', $categories);
    }
}
