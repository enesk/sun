<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Portal\PostTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicBlogController extends Controller
{
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

        return view('pages.blog.index', [
            'posts' => $posts,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'recentPosts' => $sidebar['recentPosts'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber'],
            ],
        ]);
    }

    /**
     * GET /ratgeber/{slug} — Artikel-Detailseite
     */
    public function show(string $slug): View
    {
        $post = Post::published()
            ->where('slug', $slug)
            ->with(['category', 'tags', 'media'])
            ->firstOrFail();

        // View-Count (einmal pro Session)
        $sessionKey = 'blog_viewed_' . $post->id;
        if (! session()->has($sessionKey)) {
            $post->incrementViewCount();
            session()->put($sessionKey, true);
        }

        // Related Posts (gleiche Kategorie)
        $relatedPosts = collect();
        if ($post->category_id) {
            $relatedPosts = Post::published()
                ->where('id', '!=', $post->id)
                ->byCategory($post->category_id)
                ->with(['category', 'media'])
                ->latest('published_at')
                ->limit(3)
                ->get();
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

        return view('pages.blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'previousPost' => $previousPost,
            'nextPost' => $nextPost,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
                $post->category ? ['label' => $post->category->name, 'url' => route('portal.blog.category', $post->category->slug)] : null,
                ['label' => $post->title],
            ],
        ]);
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
            'posts' => $posts,
            'categories' => $sidebar['categories'],
            'popularTags' => $sidebar['popularTags'],
            'recentPosts' => $sidebar['recentPosts'],
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
                ['label' => '#' . $tag->name],
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
                ['label' => 'Suche: ' . $term],
            ],
        ]);
    }

    /**
     * Sidebar-Daten: Kategorien mit Count, Tags, Recent Posts (gecacht 1h)
     */
    private function getSidebarData(): array
    {
        return Cache::remember('portal.blog.sidebar', 3600, function () {
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
