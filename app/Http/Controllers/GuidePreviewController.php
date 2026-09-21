<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Guide\Models\ArticleVersion;
use App\Guide\Services\GuidePageData;
use App\Guide\Services\GuideStructuredData;
use App\Guide\Services\PortalProfileService;
use App\Services\Seo\SeoService;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Vorschau einer Artikelfassung im echten Frontend-Template (#16).
 *
 * Route guide.preview auf der Portaldomain, signiert und nur fuer Konten mit
 * Zugang zum Content-Panel (EnsureContentPreviewAccess, dieselbe Grenze wie
 * die Vorschau der alten Pipeline). Gerendert wird derselbe View wie unter
 * guide.show; die Daten kommen aus GuidePageData::previewArticle() — also
 * genau das, was nach der Freigabe online stuende. Immer noindex (Meta-Tag
 * ueber den SeoService, Header aus der Middleware), nie im Seiten-Cache.
 */
class GuidePreviewController extends Controller
{
    public function __construct(
        private readonly GuidePageData $pages,
        private readonly SeoService $seo,
        private readonly PortalProfileService $profile,
        private readonly GuideStructuredData $structuredData,
    ) {}

    public function show(int $version): View
    {
        /** @var ArticleVersion $articleVersion */
        $articleVersion = ArticleVersion::query()->with('topic.category', 'topic.article')->findOrFail($version);
        $topic = $articleVersion->topic ?? abort(404);

        $article = $this->pages->previewArticle($topic, $articleVersion);

        $url = route('guide.show', $article['slug']);
        $siteName = $this->profile->siteName();
        $breadcrumb = array_values(array_filter([
            ['label' => 'Startseite', 'url' => route('home')],
            ['label' => 'Ratgeber', 'url' => route('guide.index')],
            $article['category'] !== null ? ['label' => $article['category']['name'], 'url' => $article['category']['url']] : null,
            ['label' => $article['title'], 'url' => $url],
        ]));
        $description = Str::limit((string) $article['meta_description'], 160);

        $this->seo->forGuidePage(
            title: Str::limit((string) $article['meta_title'], 65)." — {$siteName}",
            description: $description,
            canonical: $url,
            indexable: false,
            ogType: 'article',
            ogImage: $article['og_image'],
        );

        return view('guide.show', [
            'article' => $article,
            'breadcrumb' => $breadcrumb,
            'jsonLd' => $this->structuredData->article($article, $url, $description, $breadcrumb, $this->profile->organizationJsonLd(), $siteName),
            'authorName' => $this->profile->authorName(),
            'authorUrl' => $this->profile->authorUrl(),
            'logoUrl' => $this->profile->logoUrl(),
        ]);
    }
}
