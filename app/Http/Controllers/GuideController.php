<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Guide\Services\GuidePageData;
use App\Guide\Services\GuideStructuredData;
use App\Guide\Services\PortalProfileService;
use App\Guide\Support\GuidePageCache;
use App\Http\Controllers\Portal\PublicBlogController;
use App\Models\Portal\Post;
use App\Services\Seo\SeoService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Oeffentliches Ratgeber-Frontend des themengetriebenen Systems (#17):
 * Uebersicht /ratgeber, Kategorieseite /ratgeber/kategorie/{slug} und
 * Artikel /ratgeber/{slug}.
 *
 * Meta-Tags, Canonical, Robots und Open Graph setzt ausschliesslich der
 * SeoService (forGuidePage), die Views unter resources/views/guide/ schreiben
 * keine Meta-Angaben. Seitendaten liegen im GuidePageCache, den der Publisher
 * (#12) verwirft.
 *
 * Solange ein Portal keine Ratgeber-Kategorien hat (Tabellen fehlen oder noch
 * nichts veroeffentlicht), liefern Uebersicht und Kategorie die bisherigen
 * Blog-Seiten aus dem PublicBlogController — bis zur Migration der
 * Bestandsartikel in #19 bleibt so jede heutige Adresse erreichbar.
 */
class GuideController extends Controller
{
    /** Kategorien mit weniger aktiven Themen bleiben noindex (Regel #18) */
    public const MIN_INDEXABLE_TOPICS = 3;

    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(
        private readonly GuidePageData $pages,
        private readonly SeoService $seo,
        private readonly PortalProfileService $profile,
        private readonly GuideStructuredData $structuredData,
    ) {}

    /**
     * GET /ratgeber — Kategorie-Kacheln, „Zuletzt aktualisiert“, Suche (?q=).
     */
    public function index(Request $request): View
    {
        if (! $this->pages->available()) {
            return app(PublicBlogController::class)->index($request);
        }

        $tiles = GuidePageCache::remember('index.tiles', fn (): array => $this->pages->categoryTiles());

        if ($tiles === []) {
            return app(PublicBlogController::class)->index($request);
        }

        $term = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $searching = mb_strlen($term) >= self::MIN_SEARCH_LENGTH;
        $siteName = $this->profile->siteName();

        // Nur eine Kategorie: statt einer einzelnen Kachel direkt deren Themen.
        $singleCategory = count($tiles) === 1 ? $tiles[0] : null;

        $this->seo->forGuidePage(
            title: "Ratgeber — {$siteName}",
            description: "Antworten auf die häufigsten Fragen, regelmäßig geprüft und aktualisiert: der Ratgeber von {$siteName}.",
            canonical: route('guide.index'),
            // Suchergebnisse und Parameter-URLs nie indexieren
            indexable: $request->query() === [],
        );

        return view('guide.index', [
            'tiles' => $tiles,
            'singleCategory' => $singleCategory,
            'singleCategoryTopics' => $singleCategory !== null
                ? GuidePageCache::remember("category.{$singleCategory['id']}.1", fn (): array => $this->pages->categoryTopics($singleCategory['id'], 1))['items']
                : [],
            'recent' => GuidePageCache::remember('index.recent', fn (): array => $this->pages->recentlyUpdated()),
            'searchTerm' => $term,
            'searching' => $searching,
            'results' => $searching ? $this->pages->search($term) : [],
            'breadcrumb' => [
                ['label' => 'Startseite', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('guide.index')],
            ],
            'siteName' => $siteName,
            'jsonLd' => [$this->structuredData->breadcrumbList([
                ['label' => 'Startseite', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('guide.index')],
            ])],
        ]);
    }

    /**
     * GET /ratgeber/kategorie/{slug} — Einleitung, Themenliste, Pagination (?seite=).
     */
    public function category(Request $request, string $slug): View
    {
        $category = $this->pages->available() ? $this->pages->category($slug) : null;

        // Bestands-Kategorien (post_categories) bis zur Migration in #19
        if ($category === null) {
            return app(PublicBlogController::class)->category($slug);
        }

        $page = max(1, (int) $request->query('seite', 1));
        $topics = GuidePageCache::remember("category.{$category['id']}.{$page}", fn (): array => $this->pages->categoryTopics($category['id'], $page));

        if ($topics['total'] === 0 || $topics['items'] === []) {
            abort(404);
        }

        $url = route('guide.category', $category['slug']);
        $paginator = (new LengthAwarePaginator($topics['items'], $topics['total'], $topics['per_page'], $page, [
            'path' => $url,
            'pageName' => 'seite',
        ]));

        $siteName = $this->profile->siteName();
        $breadcrumb = [
            ['label' => 'Startseite', 'url' => route('home')],
            ['label' => 'Ratgeber', 'url' => route('guide.index')],
            ['label' => $category['name'], 'url' => $page > 1 ? "{$url}?seite={$page}" : $url],
        ];

        $this->seo->forGuidePage(
            title: GuidePageData::replaceYear($category['meta_title'] ?? $category['name']).($page > 1 ? " – Seite {$page}" : '')." — {$siteName}",
            description: Str::limit(
                GuidePageData::replaceYear($category['meta_description'] ?? $category['description'] ?? "{$category['name']}: {$topics['total']} Ratgeber mit kurzen Antworten, regelmäßig geprüft."),
                160,
            ),
            canonical: $page > 1 ? "{$url}?seite={$page}" : $url,
            indexable: $topics['total'] >= self::MIN_INDEXABLE_TOPICS && array_diff(array_keys($request->query()), ['seite']) === [],
        );

        return view('guide.category', [
            'category' => $category,
            'topics' => $paginator,
            'total' => $topics['total'],
            'updatedAt' => $topics['updated_at'],
            'otherCategories' => GuidePageCache::remember("category.others.{$category['id']}", fn (): array => $this->pages->otherCategories($category['id'])),
            'breadcrumb' => $breadcrumb,
            'jsonLd' => [$this->structuredData->breadcrumbList($breadcrumb)],
        ]);
    }

    /**
     * GET /ratgeber/{slug} — Artikel nach article_blueprint.
     */
    public function show(Request $request, string $slug): View
    {
        if (! $this->pages->available()) {
            return app()->call([app(PublicBlogController::class), 'show'], ['slug' => $slug]);
        }

        $post = Post::published()->where('slug', $slug)->firstOrFail();

        // View-Count einmal je Session, wie im bisherigen Blog
        $sessionKey = 'blog_viewed_'.$post->id;
        if (! $request->session()->has($sessionKey)) {
            $post->incrementViewCount();
            $request->session()->put($sessionKey, true);
        }

        // Schluessel ohne updated_at: incrementViewCount() setzt es bei jedem
        // Aufruf neu. Neue Fassungen verwirft der Publisher (#12), von Hand
        // gepflegte Beitraege laufen ueber die TTL aus.
        $article = GuidePageCache::remember("article.{$post->id}", fn (): array => $this->pages->article($post));

        $url = route('guide.show', $post->slug);
        $siteName = $this->profile->siteName();
        $breadcrumb = array_values(array_filter([
            ['label' => 'Startseite', 'url' => route('home')],
            ['label' => 'Ratgeber', 'url' => route('guide.index')],
            $article['category'] !== null ? ['label' => $article['category']['name'], 'url' => $article['category']['url']] : null,
            ['label' => $article['title'], 'url' => $url],
        ]));
        $description = Str::limit((string) $article['meta_description'], 160);
        $organization = $this->profile->organizationJsonLd();

        $this->seo->forGuidePage(
            title: Str::limit((string) $article['meta_title'], 65)." — {$siteName}",
            description: $description,
            canonical: $url,
            indexable: $request->query() === [],
            ogType: 'article',
            ogImage: $article['og_image'],
        );

        return view('guide.show', [
            'article' => $article,
            'breadcrumb' => $breadcrumb,
            'jsonLd' => $this->structuredData->article($article, $url, $description, $breadcrumb, $organization, $siteName),
            'authorName' => $this->profile->authorName(),
            'authorUrl' => $this->profile->authorUrl(),
            'logoUrl' => $this->profile->logoUrl(),
        ]);
    }
}
