<?php

namespace App\Http\Controllers\Portal;

use App\Guide\Services\PortalProfileService;
use App\Http\Controllers\Controller;
use App\Models\Portal\Post;
use Illuminate\View\View;

/**
 * Autorenseite /autor/{slug} (#18).
 *
 * Jedes Portal hat genau eine redaktionell verantwortliche Einheit (#27); ihr
 * Name steht in `tenant_content_settings.author_name`, der Slug leitet sich
 * daraus ab. Andere Slugs laufen bewusst in einen 404, damit keine leeren
 * Autorenseiten entstehen.
 */
class AuthorController extends Controller
{
    private const RECENT_POSTS = 12;

    public function __construct(
        private readonly PortalProfileService $profile,
    ) {}

    public function show(string $slug): View
    {
        abort_unless($slug === $this->profile->authorSlug(), 404);

        $posts = Post::published()
            ->with(['category', 'media'])
            ->latest('published_at')
            ->limit(self::RECENT_POSTS)
            ->get();

        return view('ratgeber.author', [
            'authorName' => $this->profile->authorName(),
            'authorBio' => $this->profile->authorBio(),
            'authorSameAs' => $this->profile->authorSameAs(),
            'authorUrl' => $this->profile->authorUrl(),
            'logoUrl' => $this->profile->logoUrl(),
            'organization' => $this->profile->organizationJsonLd(),
            'person' => $this->profile->personJsonLd(),
            'posts' => $posts,
            'postCount' => Post::published()->count(),
            'breadcrumb' => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Ratgeber', 'url' => route('guide.index')],
                ['label' => $this->profile->authorName()],
            ],
        ]);
    }
}
