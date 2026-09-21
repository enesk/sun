<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Models\Portal\City;
use App\Models\Portal\Post;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Interne Linkziele eines Ratgeber-Artikels (#10). Ersetzt den
 * Embedding-Ansatz der alten Pipeline (entfernt mit #23).
 *
 *  - Portalseiten, mindestens guide.writing.links.min_portal: zuerst das
 *    CTA-Ziel der Kategorie aus tenant_guide_settings.category_cta_mapping_json
 *    ({slug: {url, label}}, dieselbe Quelle wie der CTA-Block im Frontend),
 *    dann Firmenverzeichnis, groesste Stadtseite, Kategorienuebersicht.
 *  - Verwandte Themen derselben Kategorie mit veroeffentlichtem Artikel,
 *    hoechstens guide.writing.links.max_related, zuletzt geaenderte zuerst.
 *
 * URLs sind wurzelrelativ: Der Job laeuft ohne HTTP-Anfrage, und eine
 * absolute Tenant-Domain bliebe bei einem Domainwechsel im Text stehen.
 * Anker ohne Jahreszahl, weil sie fest im gespeicherten HTML stehen.
 *
 * Laeuft im Tenant-Kontext.
 */
class InternalLinkResolver
{
    public const TYPE_PORTAL = 'portal';

    public const TYPE_TOPIC = 'topic';

    /**
     * @return array{portal: list<array{type: string, url: string, anchor: string}>, related: list<array{type: string, url: string, anchor: string}>}
     */
    public function forTopic(Topic $topic, TenantGuideSetting $setting): array
    {
        return [
            'portal' => $this->portalTargets($topic, $setting),
            'related' => $this->relatedTargets($topic),
        ];
    }

    /**
     * Verteilt die Ziele auf Abschnitte der Gliederung: CTA-Ziel in die erste
     * H2, das zweite Portalziel in die letzte H2, verwandte Themen reihum in
     * die H2 dazwischen. So steht jedes Ziel genau einmal im Artikel und kann
     * je Abschnitt nachgeprueft werden (HtmlAssembler::ensureLinks()).
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @param  array{portal: list<array{type: string, url: string, anchor: string}>, related: list<array{type: string, url: string, anchor: string}>}  $targets
     * @return array<string, list<array{type: string, url: string, anchor: string}>>
     */
    public function distribute(array $entries, array $targets): array
    {
        $h2 = array_values(array_map(
            fn (array $entry): string => $entry['id'],
            array_filter($entries, fn (array $entry): bool => $entry['level'] === 2),
        ));

        if ($h2 === []) {
            return [];
        }

        $plan = [];
        $last = count($h2) - 1;

        foreach ($targets['portal'] as $index => $target) {
            $sectionId = match (true) {
                $index === 0 => $h2[0],
                $index === 1 => $h2[$last],
                default => $h2[min($last, $index)],
            };
            $plan[$sectionId][] = $target;
        }

        $middle = count($h2) > 2 ? array_slice($h2, 1, -1) : $h2;

        foreach ($targets['related'] as $index => $target) {
            $plan[$middle[$index % count($middle)]][] = $target;
        }

        return $plan;
    }

    /**
     * @param  array{portal: list<array{type: string, url: string, anchor: string}>, related: list<array{type: string, url: string, anchor: string}>}  $targets
     * @return array<int, string>
     */
    public function urls(array $targets): array
    {
        return array_values(array_unique(array_map(
            fn (array $target): string => $target['url'],
            [...$targets['portal'], ...$targets['related']],
        )));
    }

    /**
     * @return list<array{type: string, url: string, anchor: string}>
     */
    private function portalTargets(Topic $topic, TenantGuideSetting $setting): array
    {
        $plural = trim((string) $setting->branch_plural) ?: 'Fachbetriebe';
        $candidates = [];

        $mapping = $topic->category?->slug !== null
            ? ($setting->category_cta_mapping_json[$topic->category->slug] ?? null)
            : null;

        if (is_array($mapping) && filled($mapping['url'] ?? null)) {
            $url = $this->relative((string) $mapping['url']);

            if ($url !== null) {
                $candidates[] = $this->target($url, filled($mapping['label'] ?? null) ? (string) $mapping['label'] : "{$plural} finden");
            }
        }

        $candidates[] = $this->target(route('portal.companies.index', [], false), "{$plural} in deiner Nähe vergleichen");

        $city = $this->largestCity();

        if ($city !== null) {
            $candidates[] = $this->target(route('portal.cities.show', $city['slug'], false), "{$plural} in {$city['name']}");
        }

        $candidates[] = $this->target(route('portal.categories.index', [], false), 'alle Leistungen im Überblick');

        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate['url']] ??= $candidate;
        }

        return array_slice(array_values($unique), 0, max(2, (int) config('guide.writing.links.min_portal', 2)));
    }

    /**
     * @return list<array{type: string, url: string, anchor: string}>
     */
    private function relatedTargets(Topic $topic): array
    {
        $max = max(0, (int) config('guide.writing.links.max_related', 3));

        if ($max === 0 || $topic->guide_category_id === null) {
            return [];
        }

        $related = Topic::query()
            ->select(['guide_topics.id', 'guide_topics.question', 'posts.slug as post_slug'])
            ->join('posts', 'posts.id', '=', 'guide_topics.article_id')
            ->where('guide_topics.guide_category_id', $topic->guide_category_id)
            ->whereKeyNot($topic->getKey())
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->orderByDesc('guide_topics.last_changed_at')
            ->orderBy('guide_topics.id')
            ->limit($max)
            ->get();

        return $related->map(fn (Topic $other): array => [
            'type' => self::TYPE_TOPIC,
            'url' => route('guide.show', (string) $other->getAttribute('post_slug'), false),
            'anchor' => rtrim((string) $other->question, '?'),
        ])->values()->all();
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function largestCity(): ?array
    {
        try {
            $city = City::query()
                ->withCount('companies')
                ->orderByDesc('companies_count')
                ->first(['id', 'name', 'slug']);
        } catch (Throwable $exception) {
            Log::info('Guide-Links: Stadtseite nicht ermittelbar.', ['error' => $exception->getMessage()]);

            return null;
        }

        if ($city === null || (int) ($city->companies_count ?? 0) === 0 || blank($city->slug)) {
            return null;
        }

        return ['slug' => (string) $city->slug, 'name' => (string) $city->name];
    }

    /**
     * CTA-Ziele stehen teils absolut in der Einstellung; im Text zaehlt nur
     * der Pfad.
     */
    private function relative(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? "?{$query}" : '');
    }

    /**
     * @return array{type: string, url: string, anchor: string}
     */
    private function target(string $url, string $anchor): array
    {
        return ['type' => self::TYPE_PORTAL, 'url' => $url, 'anchor' => $anchor];
    }
}
