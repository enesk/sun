<?php

declare(strict_types=1);

namespace App\View\Components\Guide;

use App\Guide\Services\GuidePageData;
use App\Guide\Support\GuidePageCache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

/**
 * <x-guide.related :category="$category" /> — „Passende Ratgeber“ auf
 * Portal-Kategorie-, Stadt- und Profilseiten (#18).
 *
 * $category ist die Portal-Kategorie (Modell oder Slug) oder null; die
 * Zuordnung zu Ratgeber-Kategorien steht in
 * GuidePageData::relatedForPortalCategory(). Ohne passende Artikel rendert
 * die Komponente nichts. Die Daten haengen am GuidePageCache und werden mit
 * jeder Veroeffentlichung verworfen.
 */
class Related extends Component
{
    /** @var list<array{title: string, url: string, teaser: string, date_label: string, date: mixed}> */
    public array $topics;

    public function __construct(Model|string|null $category = null)
    {
        $slug = $category instanceof Model ? $category->getAttribute('slug') : $category;
        $slug = is_string($slug) && trim($slug) !== '' ? trim($slug) : null;

        $this->topics = GuidePageCache::remember(
            'related.portal.'.($slug ?? '_all'),
            fn (): array => app(GuidePageData::class)->relatedForPortalCategory($slug),
        );
    }

    public function shouldRender(): bool
    {
        return $this->topics !== [];
    }

    public function render(): View
    {
        return view('components.guide.related');
    }
}
