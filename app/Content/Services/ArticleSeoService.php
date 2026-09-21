<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Guide\Models\LegacyArticle;
use App\Models\Portal\Post;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Einzige Quelle fuer Meta-Angaben und strukturierte Daten der Ratgeber-Seiten
 * (#17). Die Blade-Templates schreiben keine Meta-Tags selbst, sie reichen die
 * hier gebauten Werte nur an die Layout-Sections weiter.
 */
class ArticleSeoService
{
    public function __construct(
        private readonly ArticleBlockPresenter $blocks = new ArticleBlockPresenter,
    ) {}

    /**
     * @return array{title: string, description: string, canonical: string, og_type: string, og_image: ?string, published_at: ?Carbon, modified_at: ?Carbon}
     */
    public function meta(Post $post, ?LegacyArticle $legacy, string $siteName): array
    {
        $title = $legacy?->meta_title ?: ($post->meta_title ?: $post->title);
        $description = $legacy?->meta_description ?: ($post->meta_description ?: $post->excerpt_or_truncated);

        return [
            'title' => $this->truncate($title, 65).' — '.$siteName,
            'description' => $this->truncate($description, 160),
            'canonical' => route('guide.show', $post->slug),
            'og_type' => 'article',
            // Vorschaubild ist die groesste Hero-Variante aus #16 (1200 Breite);
            // sie speist og:image, twitter:image und das Article-Markup.
            // Google verlangt fuer og:image und Article.image eine absolute
            // URL. In `assets_json.hero.variants` steht der Wert so, wie ihn
            // die Ablage beim Erzeugen geliefert hat — ohne gesetzte Disk-URL
            // ist das ein Pfad. Deshalb hier gegen den Host der Anfrage
            // aufloesen (#84).
            'og_image' => $this->absoluteUrl($this->blocks->heroImageUrl($legacy) ?: $post->featured_image_url),
            'published_at' => $post->published_at,
            'modified_at' => $this->modifiedAt($post, $legacy),
        ];
    }

    /**
     * Bild-URL fuer og:image und Article.image. Relative Pfade werden gegen
     * den Host der laufenden Anfrage aufgeloest, absolute Angaben bleiben
     * unangetastet.
     */
    private function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://', '//', 'data:'])) {
            return $url;
        }

        return url($url);
    }

    /**
     * Aktualisierungsdatum: die letzte redaktionelle Pruefung schlaegt die
     * technische Zeile in `posts`, sonst wuerde jede Cache-Beruehrung als
     * Aktualisierung gelten.
     */
    public function modifiedAt(Post $post, ?LegacyArticle $legacy): ?Carbon
    {
        $latest = null;

        foreach ([$legacy?->content_updated_at, $post->updated_at, $post->published_at] as $candidate) {
            if ($candidate instanceof Carbon && ($latest === null || $candidate->greaterThan($latest))) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /**
     * JSON-LD-Graph der Artikelseite: Article, BreadcrumbList, FAQPage und —
     * wenn der Altartikel Schritte mitbringt — HowTo.
     *
     * @param  list<array{label: string, url?: string}>  $breadcrumb
     * @param  list<array{question: string, answer: string}>  $faq
     * @param  array{name: string, steps: list<array{name: string, text: string}>}|null  $howTo
     * @param  list<array{title: string, url: ?string, publisher: ?string, published_at: ?Carbon, stale_year: ?string}>  $sources
     * @param  array<string, mixed>  $organization  Organization-Knoten aus PortalProfileService (#18)
     * @return list<array<string, mixed>>
     */
    public function graph(
        Post $post,
        ?LegacyArticle $legacy,
        string $siteName,
        array $breadcrumb,
        array $faq,
        ?array $howTo,
        array $sources,
        ?string $shortAnswer,
        string $bodyHtml,
        array $organization = [],
    ): array {
        $meta = $this->meta($post, $legacy, $siteName);
        $url = $meta['canonical'];

        // Herausgeber: der Organization-Knoten des Portals (#18) mit Logo und
        // sameAs. Im Article steht nur die Referenz darauf, damit derselbe
        // Herausgeber nicht zweimal im Graph beschrieben wird.
        $organizationNode = $organization !== [] ? $organization : [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $siteName,
            'url' => url('/'),
            'publishingPrinciples' => route('portal.blog.editorial'),
        ];

        $publisher = isset($organizationNode['@id'])
            ? ['@id' => $organizationNode['@id']]
            : $organizationNode;

        $article = array_filter([
            '@type' => 'Article',
            '@id' => $url.'#article',
            'headline' => $this->truncate($post->title, 110),
            'description' => $meta['description'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
            'datePublished' => $meta['published_at']?->toIso8601String(),
            'dateModified' => $meta['modified_at']?->toIso8601String(),
            'image' => $meta['og_image'],
            'inLanguage' => 'de-DE',
            'wordCount' => str_word_count(strip_tags($bodyHtml)) ?: null,
            'articleSection' => $post->category?->getAttribute('name'),
            'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
            'author' => $publisher,
            'publisher' => $publisher,
            'publishingPrinciples' => route('portal.blog.editorial'),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
            ],
            'speakable' => $shortAnswer ? [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['.ratgeber-short-answer'],
            ] : null,
            'citation' => $this->citations($sources),
        ], fn ($value) => $value !== null && $value !== []);

        $graph = [$article, $organizationNode, $this->breadcrumbList($breadcrumb, $url)];

        if ($faq !== []) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => array_map(fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ], $faq),
            ];
        }

        if ($howTo !== null) {
            $graph[] = [
                '@type' => 'HowTo',
                '@id' => $url.'#howto',
                'name' => $howTo['name'],
                'step' => array_map(fn (array $step, int $index): array => [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'name' => $step['name'],
                    'text' => $step['text'],
                ], $howTo['steps'], array_keys($howTo['steps'])),
            ];
        }

        return $graph;
    }

    /**
     * @param  list<array{label: string, url?: string}>  $breadcrumb
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $breadcrumb, string $currentUrl): array
    {
        $items = [];
        $position = 1;

        foreach (array_values(array_filter($breadcrumb)) as $index => $entry) {
            $items[] = array_filter([
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $entry['label'],
                'item' => $entry['url'] ?? ($index === count($breadcrumb) - 1 ? $currentUrl : null),
            ], fn ($value) => $value !== null);
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param  list<array{title: string, url: ?string, publisher: ?string, published_at: ?Carbon, stale_year: ?string}>  $sources
     * @return list<array<string, mixed>>
     */
    private function citations(array $sources): array
    {
        return array_values(array_map(fn (array $source): array => array_filter([
            '@type' => 'CreativeWork',
            'name' => $source['title'],
            'url' => $source['url'],
            'publisher' => $source['publisher'] ? ['@type' => 'Organization', 'name' => $source['publisher']] : null,
            'datePublished' => $source['published_at']?->toDateString(),
        ], fn ($value) => $value !== null), $sources));
    }

    private function truncate(string $value, int $length): string
    {
        return Str::limit(trim(strip_tags($value)), $length, '');
    }
}
