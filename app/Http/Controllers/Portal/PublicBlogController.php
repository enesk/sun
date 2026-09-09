<?php

namespace App\Http\Controllers\Portal;

use App\Content\Models\ArticleDraft;
use App\Content\Services\ArticleBlockPresenter;
use App\Content\Services\ArticleSeoService;
use App\Content\Services\PortalProfileService;
use App\Content\Services\TocBuilder;
use App\Http\Controllers\Controller;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Portal\PostTag;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicBlogController extends Controller
{
    public function __construct(
        private readonly PortalProfileService $profile,
    ) {}

    /**
     * GET /ratgeber — Blog-Übersichtsseite mit Paginierung
     */
    public function index(Request $request): View
    {
        $query = Post::published()
            ->with(['category', 'tags', 'media'])
            ->latest('published_at');

        // ── Filter: Kategorie ──
        if ($request->filled('kategorie')) {
            $category = PostCategory::where('slug', $request->kategorie)->first();
            if ($category) {
                $query->byCategory($category->id);
            }
        }

        $posts = $query->paginate(12)->withQueryString();

        $sidebar = $this->getSidebarData();

        $breadcrumb = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Ratgeber'],
        ];

        return view('pages.blog.index', [
            'posts' => $posts,
            'organization' => $this->profile->organizationJsonLd(),
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'recentPosts' => $sidebar['recentPosts'],
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * GET /ratgeber/{slug} — Artikel-Detailseite
     */
    public function show(
        string $slug,
        TocBuilder $tocBuilder,
        ArticleBlockPresenter $blocks,
        ArticleSeoService $seo,
    ): View {
        $post = Post::published()
            ->where('slug', $slug)
            ->with(['category', 'tags', 'media'])
            ->firstOrFail();

        // View-Count (einmal pro Session)
        $sessionKey = 'blog_viewed_'.$post->id;
        if (! session()->has($sessionKey)) {
            $post->incrementViewCount();
            session()->put($sessionKey, true);
        }

        // Zusatzfelder des Ratgeber-Entwurfs (#3). Redaktionell von Hand
        // gepflegte Beitraege haben keinen Entwurf — dann entfallen die
        // zusaetzlichen Bloecke, der Artikel bleibt vollstaendig lesbar.
        $draft = $this->draftFor($post);

        // Related Posts: zuerst die Artikel, die beim Veroeffentlichen auf
        // diesen hier verlinkt haben (Backlinks, #21), danach die gleiche
        // Kategorie.
        $relatedPosts = $this->backlinkPosts($draft);

        if ($relatedPosts->count() < 3 && $post->category_id) {
            $relatedPosts = $relatedPosts->merge(
                Post::published()
                    ->where('id', '!=', $post->id)
                    ->whereNotIn('id', $relatedPosts->pluck('id')->all())
                    ->byCategory($post->category_id)
                    ->with(['category', 'media'])
                    ->latest('published_at')
                    ->limit(3 - $relatedPosts->count())
                    ->get()
            );
        }

        // Falls weniger als 3 Related: mit neuesten auffüllen
        if ($relatedPosts->count() < 3) {
            $excludeIds = $relatedPosts->pluck('id')->push($post->id)->toArray();
            $fillPosts = Post::published()
                ->whereNotIn('id', $excludeIds)
                ->with(['category', 'media'])
                ->latest('published_at')
                ->limit(3 - $relatedPosts->count())
                ->get();
            $relatedPosts = $relatedPosts->merge($fillPosts);
        }

        // Previous / Next
        $previousPost = Post::published()
            ->where('published_at', '<', $post->published_at)
            ->latest('published_at')
            ->select('id', 'title', 'slug')
            ->first();

        $nextPost = Post::published()
            ->where('published_at', '>', $post->published_at)
            ->oldest('published_at')
            ->select('id', 'title', 'slug')
            ->first();

        $sidebar = $this->getSidebarData();

        $breadcrumb = array_values(array_filter([
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
            $post->category ? ['label' => $post->category->name, 'url' => route('portal.blog.category', $post->category->slug)] : null,
            ['label' => $post->title],
        ]));

        $toc = $tocBuilder->build($this->bodyHtml($post, $draft));
        [$bodyBefore, $bodyAfter] = $tocBuilder->splitForRegionalBlock($toc['html'], $toc['headings']);

        $siteName = $this->siteName();
        $organization = $this->profile->organizationJsonLd();
        $faq = $blocks->faq($draft);
        $sources = $blocks->sources($draft);
        $howTo = $blocks->howTo($draft);
        $shortAnswer = $blocks->shortAnswer($draft);

        return view('pages.blog.show', [
            'post' => $post,
            'draft' => $draft,
            'relatedPosts' => $relatedPosts,
            'previousPost' => $previousPost,
            'nextPost' => $nextPost,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'breadcrumb' => $breadcrumb,
            'seo' => $seo->meta($post, $draft, $siteName),
            'jsonLd' => $seo->graph($post, $draft, $siteName, $breadcrumb, $faq, $howTo, $sources, $shortAnswer, $toc['html'], $organization),
            'organization' => $organization,
            'headings' => $toc['headings'],
            'bodyBefore' => $bodyBefore,
            'bodyAfter' => $bodyAfter,
            'shortAnswer' => $shortAnswer,
            'keyFacts' => $blocks->keyFacts($draft),
            'faq' => $faq,
            'sources' => $sources,
            'region' => $blocks->region($draft),
            'heroImage' => $blocks->heroImage($post, $draft),
            'infographic' => $blocks->infographic($draft),
            'authorName' => $this->profile->authorName(),
            'authorUrl' => $this->profile->authorUrl(),
            'changelog' => $blocks->changelog($draft),
        ]);
    }

    /**
     * Artikel, die beim Veroeffentlichen auf diesen Ratgeber verlinkt haben
     * (#21). Sie stehen im Protokoll des Entwurfs und sind die verlaesslichere
     * Empfehlung als „gleiche Kategorie": jemand hat den Verweis im Text
     * tatsaechlich gesetzt.
     *
     * @return \Illuminate\Support\Collection<int, Post>
     */
    private function backlinkPosts(?ArticleDraft $draft): Collection
    {
        $ids = $draft?->backlinkArticleIds() ?? [];

        if ($ids === []) {
            return collect();
        }

        return Post::published()
            ->whereIn('id', array_slice($ids, -3))
            ->with(['category', 'media'])
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    /**
     * Ratgeber-Entwurf zum veroeffentlichten Beitrag. Portale ohne
     * Content-Pipeline haben die Tabelle nicht — dort bleibt es beim Bestand.
     */
    private function draftFor(Post $post): ?ArticleDraft
    {
        if (! Schema::connection((new ArticleDraft)->getConnectionName())->hasTable('article_drafts')) {
            return null;
        }

        // Nur live stehende Fassungen. Eine Aktualisierung (#24) traegt vom
        // Anlegen an dieselbe article_id wie die Fassung, die sie ersetzt —
        // ohne diese Einschraenkung stuende der unfertige Entwurf ab dem
        // ersten Ueberarbeitungsschritt auf der oeffentlichen Seite, noch vor
        // Qualitaetsgate und Pruefung. Die juengste veroeffentlichte Fassung
        // ist die richtige: nach einem Refresh ist das die neue.
        return ArticleDraft::query()
            ->where('article_id', $post->id)
            ->published()
            ->latest('id')
            ->first();
    }

    /**
     * Der Entwurf liefert fertiges HTML, der Bestand Markdown.
     */
    private function bodyHtml(Post $post, ?ArticleDraft $draft): string
    {
        $html = trim((string) $draft?->body_html);

        return $html !== '' ? $html : (string) Str::markdown((string) $post->body);
    }

    private function siteName(): string
    {
        return view()->shared('currentTenant')?->name ?? config('app.name');
    }

    /**
     * GET /ratgeber/kategorie/{slug} — Kategorie-Seite
     */
    public function category(string $slug): View
    {
        $category = PostCategory::where('slug', $slug)->firstOrFail();

        $posts = Post::published()
            ->byCategory($category->id)
            ->with(['category', 'tags', 'media'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $sidebar = $this->getSidebarData();

        return view('pages.blog.category', [
            'category' => $category,
            'organization' => $this->profile->organizationJsonLd(),
            'posts' => $posts,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'recentPosts' => $sidebar['recentPosts'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
                ['label' => $category->name],
            ],
        ]);
    }

    /**
     * GET /ratgeber/tag/{slug} — Tag-Seite
     */
    public function tag(string $slug): View
    {
        $tag = PostTag::where('slug', $slug)->firstOrFail();

        $posts = Post::published()
            ->byTag($tag->id)
            ->with(['category', 'tags', 'media'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $sidebar = $this->getSidebarData();

        return view('pages.blog.tag', [
            'tag' => $tag,
            'organization' => $this->profile->organizationJsonLd(),
            'posts' => $posts,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'recentPosts' => $sidebar['recentPosts'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
                ['label' => '#'.$tag->name],
            ],
        ]);
    }

    /**
     * GET /ratgeber/suche — Blog-Suche
     */
    public function search(Request $request): View
    {
        $term = $request->input('q', '');

        $posts = Post::published()
            ->search($term)
            ->with(['category', 'tags', 'media'])
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $sidebar = $this->getSidebarData();

        return view('pages.blog.search', [
            'posts' => $posts,
            'searchTerm' => $term,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
                ['label' => 'Suche: '.$term],
            ],
        ]);
    }

    /**
     * Sidebar-Daten: Kategorien mit Count, Tags, Recent Posts (gecacht 1h)
     */
    private function getSidebarData(): array
    {
        return Cache::remember(TenantCache::key('portal.blog.sidebar'), 3600, function () {
            $categories = PostCategory::topLevel()
                ->ordered()
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->get();

            $popularTags = PostTag::ordered()
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderByDesc('posts_count')
                ->limit(20)
                ->get();

            $recentPosts = Post::published()
                ->with(['category'])
                ->latest('published_at')
                ->select('id', 'title', 'slug', 'published_at', 'category_id')
                ->limit(5)
                ->get();

            return compact('categories', 'popularTags', 'recentPosts');
        });
    }
}
