<?php

declare(strict_types=1);

namespace App\Guide\Seo;

use App\Content\Services\ArticleSeoService;
use App\Guide\Models\Category;
use App\Guide\Models\LegacyArticle;
use App\Guide\Services\GuidePageData;
use App\Guide\Support\GuidePageCache;
use App\Http\Controllers\GuideController;
use App\Models\Portal\Post;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * /sitemap-ratgeber.xml (#18): Uebersicht, Kategorien und Artikel.
 *
 * Kein zweites Sitemap-System: GenerateTenantSitemapJob und
 * tenants:generate-sitemap nehmen die Datei in den Sitemap-Index auf, sobald
 * hasEntries() zutrifft. Ausgeliefert wird sie dynamisch aus dem
 * GuidePageCache — der Publisher verwirft ihn nach jeder Veroeffentlichung
 * und jedem Rollback (GuidePageCache::flush()), eine Datei muss dafuer nicht
 * neu geschrieben werden.
 *
 * Enthalten sind nur veroeffentlichte Beitraege und sichtbare Kategorien mit
 * so vielen Artikeln, dass die Kategorieseite indexierbar ist
 * (GuideController::MIN_INDEXABLE_TOPICS) — eine noindex-Seite in der
 * Sitemap waere ein Widerspruch. lastmod ist bei Ratgeber-Artikeln
 * guide_article_details.content_changed_at, nie last_checked_at
 * (docs/guide-system.md, §5); Bestandsbeitraege ohne Thema behalten das
 * Datum, das ihre Seite als dateModified ausweist (ArticleSeoService).
 *
 * Muss im Tenant-Kontext laufen.
 */
class GuideSitemapGenerator
{
    public const FILENAME = 'sitemap-ratgeber.xml';

    public const CACHE_TTL = GuidePageCache::TTL_SECONDS;

    public function __construct(
        private readonly GuidePageData $pages,
        private readonly ArticleSeoService $seo,
    ) {}

    /**
     * Fertiges XML, oder null wenn das Portal keine veroeffentlichten
     * Ratgeber hat.
     */
    public function xml(string $baseUrl): ?string
    {
        $xml = GuidePageCache::remember('sitemap', fn (): string => $this->render(rtrim($baseUrl, '/')) ?? '');

        return $xml !== '' ? $xml : null;
    }

    public function hasEntries(): bool
    {
        return Post::published()->exists();
    }

    private function render(string $baseUrl): ?string
    {
        if (! $this->hasEntries()) {
            return null;
        }

        $sitemap = Sitemap::create();

        $sitemap->add(
            Url::create("{$baseUrl}/ratgeber")
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
        );

        $sitemap->add(
            Url::create("{$baseUrl}/ratgeber/redaktion")
                ->setPriority(0.4)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
        );

        if ($this->pages->available()) {
            $this->addCategories($sitemap, $baseUrl);
        }

        $this->addArticles($sitemap, $baseUrl);

        return $sitemap->render();
    }

    private function addCategories(Sitemap $sitemap, string $baseUrl): void
    {
        $stats = Post::published()
            ->whereNotNull('posts.guide_category_id')
            ->leftJoin('guide_article_details as d', 'd.article_id', '=', 'posts.id')
            ->groupBy('posts.guide_category_id')
            ->selectRaw('posts.guide_category_id as category_id, count(*) as article_count, max(coalesce(d.content_changed_at, posts.published_at)) as last_at')
            ->toBase()
            ->get()
            ->keyBy('category_id');

        Category::query()
            ->visible()
            ->ordered()
            ->whereIn('id', $stats->keys()->all())
            ->get(['id', 'slug'])
            ->each(function (Category $category) use ($sitemap, $baseUrl, $stats): void {
                $row = $stats[$category->id];

                if ((int) $row->article_count < GuideController::MIN_INDEXABLE_TOPICS) {
                    return;
                }

                $url = Url::create("{$baseUrl}/ratgeber/kategorie/{$category->slug}")
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY);

                if ($row->last_at !== null) {
                    $url->setLastModificationDate(Carbon::parse($row->last_at));
                }

                $sitemap->add($url);
            });
    }

    private function addArticles(Sitemap $sitemap, string $baseUrl): void
    {
        $guide = $this->pages->available();

        $query = Post::published()->select('posts.*')->orderByDesc('posts.published_at');

        if ($guide) {
            $query->leftJoin('guide_article_details as d', 'd.article_id', '=', 'posts.id')
                ->addSelect('d.content_changed_at as guide_content_changed_at', 'd.id as guide_detail_id');
        }

        $query->chunk(200, function (Collection $posts) use ($sitemap, $baseUrl): void {
            $legacy = $posts->filter(fn (Post $post): bool => $post->getAttribute('guide_detail_id') === null && $post->getAttribute('guide_topic_id') === null)->keyBy('id');
            $blocks = $this->legacyFor($legacy->pluck('id')->all());

            foreach ($posts as $post) {
                $lastmod = $legacy->has($post->getKey())
                    ? $this->seo->modifiedAt($post, $blocks->get($post->id))
                    : $this->contentChangedAt($post);

                $sitemap->add(
                    Url::create("{$baseUrl}/ratgeber/{$post->slug}")
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setLastModificationDate($lastmod ?? $post->published_at)
                );
            }
        });
    }

    /**
     * Letzte inhaltliche Aenderung eines Ratgeber-Artikels; ohne Detailzeile
     * die Erstveroeffentlichung.
     */
    private function contentChangedAt(Post $post): ?CarbonInterface
    {
        $changed = $post->getAttribute('guide_content_changed_at');

        return $changed !== null ? Carbon::parse($changed) : $post->published_at;
    }

    /**
     * Altartikel-Bloecke der Bestandsbeitraege ohne Thema (#34).
     *
     * @param  list<int>  $postIds
     * @return Collection<int, LegacyArticle>
     */
    private function legacyFor(array $postIds): Collection
    {
        if ($postIds === [] || ! LegacyArticle::tableExists()) {
            return collect();
        }

        return LegacyArticle::query()
            ->whereIn('article_id', $postIds)
            ->get(['article_id', 'content_updated_at'])
            ->keyBy('article_id');
    }
}
