<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Models\ArticleDraft;
use App\Content\Services\ArticleBlockPresenter;
use App\Content\Services\ArticleMapper;
use App\Content\Services\ArticleSeoService;
use App\Content\Services\PortalProfileService;
use App\Content\Services\TocBuilder;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Vorschau eines Artikelentwurfs im echten Frontend-Template (#20).
 *
 * Die Route ist signiert und nur fuer Redaktions-Accounts erreichbar
 * (EnsureContentPreviewAccess). Gerendert wird derselbe View wie unter
 * `portal.blog.show` — der Pruefer beurteilt damit genau das, was der Leser
 * spaeter sieht, einschliesslich Typografie, Kurzantwort, Key-Facts,
 * Regionalblock, FAQ und Quellenzeile.
 *
 * Der Entwurf hat vor der Veroeffentlichung keinen Datensatz in `posts`.
 * Statt dafuer eine Zeile anzulegen (die bei jedem Abbruch als Karteileiche
 * bliebe), wird ein nicht gespeicherter Post aus dem ArticleMapper gefuellt —
 * also aus derselben Abbildung, die #21 spaeter zum Veroeffentlichen nutzt.
 * Was hier zu sehen ist, ist damit auch das, was veroeffentlicht wuerde.
 */
class RatgeberPreviewController extends Controller
{
    public function __construct(
        private readonly PortalProfileService $profile,
    ) {}

    public function show(
        int $draft,
        ArticleMapper $mapper,
        TocBuilder $tocBuilder,
        ArticleBlockPresenter $blocks,
        ArticleSeoService $seo,
    ): View {
        $articleDraft = ArticleDraft::query()->with('sources')->findOrFail($draft);

        $post = $this->previewPost($articleDraft, $mapper);
        /** @var PostCategory|null $category */
        $category = $post->category;

        $toc = $tocBuilder->build((string) $articleDraft->body_html);
        [$bodyBefore, $bodyAfter] = $tocBuilder->splitForRegionalBlock($toc['html'], $toc['headings']);

        $siteName = view()->shared('currentTenant')?->name ?? config('app.name');
        $organization = $this->profile->organizationJsonLd();
        $faq = $blocks->faq($articleDraft);
        $sources = $blocks->sources($articleDraft);
        $howTo = $blocks->howTo($articleDraft);
        $shortAnswer = $blocks->shortAnswer($articleDraft);

        $breadcrumb = array_values(array_filter([
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Ratgeber', 'url' => route('portal.blog.index')],
            $category !== null ? ['label' => $category->name, 'url' => route('portal.blog.category', $category->slug)] : null,
            ['label' => $post->title],
        ]));

        return view('pages.blog.show', [
            'post' => $post,
            'draft' => $articleDraft,
            // In der Vorschau gibt es keine Nachbarschaft: verwandte Artikel,
            // Vor-/Zurueck und die Seitenleiste haengen an veroeffentlichten
            // Beitraegen und wuerden hier nur Rauschen erzeugen.
            'relatedPosts' => collect(),
            'previousPost' => null,
            'nextPost' => null,
            'categories' => collect(),
            'popularTags' => collect(),
            'breadcrumb' => $breadcrumb,
            'seo' => $seo->meta($post, $articleDraft, $siteName),
            'jsonLd' => $seo->graph($post, $articleDraft, $siteName, $breadcrumb, $faq, $howTo, $sources, $shortAnswer, $toc['html'], $organization),
            'organization' => $organization,
            'headings' => $toc['headings'],
            'bodyBefore' => $bodyBefore,
            'bodyAfter' => $bodyAfter,
            'shortAnswer' => $shortAnswer,
            'keyFacts' => $blocks->keyFacts($articleDraft),
            'faq' => $faq,
            'sources' => $sources,
            'region' => $blocks->region($articleDraft),
            'heroImage' => $blocks->heroImage($post, $articleDraft),
            'infographic' => $blocks->infographic($articleDraft),
            'authorName' => $this->profile->authorName(),
            'authorUrl' => $this->profile->authorUrl(),
            // Setzt im Layout `<meta name="robots" content="noindex, nofollow">`.
            // Der X-Robots-Tag-Header kommt zusaetzlich aus der Middleware.
            'previewMode' => true,
        ]);
    }

    /**
     * Nicht gespeicherter Beitrag aus dem Entwurf. Beziehungen werden
     * ausdruecklich gesetzt: ein Modell ohne Schluessel darf im Template
     * keine Abfrage ausloesen.
     */
    private function previewPost(ArticleDraft $draft, ArticleMapper $mapper): Post
    {
        $attributes = $mapper->toAttributes($draft);

        $post = new Post;
        $post->forceFill($attributes);

        $category = $attributes['category_id'] !== null
            ? PostCategory::query()->find($attributes['category_id'])
            : null;

        $post->setRelation('category', $category);
        $post->setRelation('tags', new EloquentCollection);

        return $post;
    }
}
