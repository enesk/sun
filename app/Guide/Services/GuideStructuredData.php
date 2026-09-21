<?php

declare(strict_types=1);

namespace App\Guide\Services;

use Illuminate\Support\Str;

/**
 * Schema.org-Graph der Ratgeber-Seiten (#17): Article, FAQPage und
 * BreadcrumbList, dazu der Organization-Knoten des Portals als Herausgeber.
 *
 * dateModified ist die letzte inhaltliche Aenderung
 * (guide_article_details.content_changed_at), ohne sie das
 * Veroeffentlichungsdatum — nie das Pruefdatum (design/guide-frontend.md, §4.1).
 * Ausgegeben wird der Graph ueber resources/views/guide/partials/jsonld.blade.php.
 */
class GuideStructuredData
{
    /**
     * @param  array<string, mixed>  $article  Ergebnis von GuidePageData::article()
     * @param  list<array{label: string, url: string}>  $breadcrumb
     * @param  array<string, mixed>  $organization  PortalProfileService::organizationJsonLd()
     * @return list<array<string, mixed>>
     */
    public function article(array $article, string $url, string $description, array $breadcrumb, array $organization, string $siteName): array
    {
        $organizationNode = $organization !== [] ? $organization : [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $siteName,
            'url' => url('/'),
        ];

        // Autor und Herausgeber vollstaendig ausgeschrieben statt nur als
        // @id-Verweis: der Rich Results Test verlangt am Article einen
        // Autorennamen.
        $publisher = array_filter([
            '@type' => 'Organization',
            '@id' => $organizationNode['@id'] ?? null,
            'name' => $organizationNode['name'] ?? $siteName,
            'url' => $organizationNode['url'] ?? url('/'),
            'logo' => $organizationNode['logo'] ?? null,
        ]);

        $node = array_filter([
            '@type' => 'Article',
            '@id' => $url.'#article',
            'headline' => Str::limit((string) $article['title'], 107, '…'),
            'description' => $description,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
            'datePublished' => $article['published_at']?->toIso8601String(),
            'dateModified' => ($article['modified_at'] ?? $article['published_at'])?->toIso8601String(),
            'image' => $article['og_image'] ?? null,
            'inLanguage' => 'de-DE',
            'articleSection' => $article['category']['name'] ?? null,
            'author' => $publisher,
            'publisher' => $publisher,
            'citation' => array_values(array_filter(array_map(
                fn (array $source): ?string => $source['url'] ?? null,
                $article['sources'] ?? [],
            ))) ?: null,
        ], fn ($value) => $value !== null && $value !== []);

        $graph = [$node, $organizationNode, $this->breadcrumbList($breadcrumb)];

        if (($article['faq'] ?? []) !== []) {
            $graph[] = $this->faqPage($article['faq'], $url);
        }

        return $graph;
    }

    /**
     * BreadcrumbList; jeder Eintrag traegt eine absolute URL.
     *
     * @param  list<array{label: string, url: string}>  $breadcrumb
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $breadcrumb): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(
                fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['label'],
                    'item' => $crumb['url'],
                ],
                $breadcrumb,
                array_keys($breadcrumb),
            )),
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faq
     * @return array<string, mixed>
     */
    private function faqPage(array $faq, string $url): array
    {
        return [
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
}
