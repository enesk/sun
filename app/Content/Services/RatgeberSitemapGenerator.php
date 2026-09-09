<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Models\Portal\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Eigene Sitemap der Ratgeber-Seiten (#18).
 *
 * Kein zweites Sitemap-System: die Datei wird vom bestehenden
 * GenerateTenantSitemapJob geschrieben und dort in den Sitemap-Index
 * aufgenommen. Enthalten sind ausschliesslich veroeffentlichte Beitraege;
 * lastmod ist dasselbe Datum, das die Artikelseite als dateModified ausweist
 * (ArticleSeoService), damit Suchmaschinen und KI-Crawler keine zwei
 * unterschiedlichen Aktualisierungsdaten sehen.
 *
 * Muss im Tenant-Kontext laufen.
 */
class RatgeberSitemapGenerator
{
    public const FILENAME = 'sitemap-ratgeber.xml';

    public function __construct(
        private readonly ArticleSeoService $seo,
    ) {}

    /**
     * Schreibt die Sitemap und liefert den Dateinamen, oder null wenn das
     * Portal keine veroeffentlichten Ratgeber hat.
     */
    public function generate(string $baseUrl, string $targetDir): ?string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (! Post::published()->exists()) {
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

        Post::published()
            ->orderByDesc('published_at')
            ->chunk(200, function (Collection $posts) use ($sitemap, $baseUrl): void {
                $drafts = $this->draftsFor($posts->pluck('id')->all());

                foreach ($posts as $post) {
                    $modified = $this->seo->modifiedAt($post, $drafts->get($post->id));

                    $sitemap->add(
                        Url::create("{$baseUrl}/ratgeber/{$post->slug}")
                            ->setPriority(0.7)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                            ->setLastModificationDate($modified ?? $post->published_at)
                    );
                }
            });

        $sitemap->writeToFile(rtrim($targetDir, '/').'/'.self::FILENAME);

        return self::FILENAME;
    }

    /**
     * @param  list<int>  $postIds
     * @return Collection<int, ArticleDraft>
     */
    private function draftsFor(array $postIds): Collection
    {
        if ($postIds === [] || ! Schema::connection((new ArticleDraft)->getConnectionName())->hasTable('article_drafts')) {
            return collect();
        }

        return ArticleDraft::query()
            ->whereIn('article_id', $postIds)
            ->orderBy('id')
            ->get()
            ->keyBy('article_id');
    }
}
