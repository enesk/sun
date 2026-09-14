<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\Company;
use App\Models\Portal\Post;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Ratgeber-Uebersicht /ratgeber im Theme sun-v2 (Vorlage
 * elektrikerportal-ratgeber-uebersicht.html): Einleitung mit echter
 * Artikelzahl, Symbol je Kategorie, "Meistgelesen" nach view_count und die
 * Betriebszahl fuer den CTA-Kasten.
 *
 * PublicBlogController::index() bleibt fuer alle Themes gleich.
 */
final class BlogIndexViewComposer
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $config = config('themes.sun-v2');
        $number = fn (int $value): string => number_format($value, 0, ',', '.');

        $counts = Cache::remember(TenantCache::key('sun-v2.blog.counts'), 3600, fn (): array => [
            'posts' => Post::published()->count(),
            'companies' => Company::active()->count(),
        ]);

        $featured = Cache::remember(TenantCache::key('sun-v2.blog.featured'), 3600, fn (): ?Post => Post::published()
            ->with('category')
            ->orderByDesc('view_count')
            ->latest('published_at')
            ->first());

        $icons = $config['blog']['category_icons'];

        $view->with('sunBlog', [
            'headline' => $config['blog']['headline'],
            'text' => strtr($config['blog']['text'], [':posts' => $number($counts['posts'])]),
            'searchPlaceholder' => $config['blog']['search_placeholder'],
            'featured' => $featured,
            'icon' => fn (?string $slug): string => $icons[$slug] ?? $config['brand_icon'],
            'cta' => [
                'headline' => $config['blog']['cta']['headline'],
                'text' => strtr($config['blog']['cta']['text'], [':companies' => $number($counts['companies'])]),
            ],
        ]);
    }
}
