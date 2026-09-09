<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\Central\ContentFingerprint;
use App\Content\Models\TopicCandidate;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Interne Linkziele fuer einen Artikelentwurf (#14).
 *
 * Zwei Arten von Zielen, mit unterschiedlicher Begruendung:
 *
 *  - Kategorie- und Stadtseiten des Portals. Sie sind der Grund, warum der
 *    Ratgeber ueberhaupt existiert: er soll Ratsuchende in das Verzeichnis
 *    fuehren. Davon verlangt das Abnahmekriterium mindestens zwei.
 *  - Bestehende Ratgeber desselben Mandanten, ausgewaehlt ueber die
 *    Aehnlichkeit der Embeddings in `content_fingerprints`. Hoechstens drei,
 *    und nur im Korridor zwischen 'thematisch verwandt' und 'Duplikat' — was
 *    zu aehnlich ist, waere kein Linkziel, sondern eine Kannibalisierung.
 *
 * Die URLs sind absichtlich wurzelrelativ ('/kategorien/heizung'). Der Job
 * laeuft ohne HTTP-Anfrage; ein absoluter Link muesste die Tenant-Domain
 * hart hineinschreiben und wuerde bei einem Domainwechsel im Artikeltext
 * stehen bleiben.
 *
 * Alle Methoden laufen im Tenant-Kontext.
 */
class InternalLinkResolver
{
    /**
     * Linkziele fuer ein Thema, wichtigste zuerst.
     *
     * @return array<int, array{type: string, label: string, url: string, anchor: string, note: string}>
     */
    public function forTopic(TopicCandidate $topic, string $branchLabel): array
    {
        $keywords = $this->keywords($topic);

        $targets = array_merge(
            $this->cityTargets($topic, $branchLabel),
            $this->categoryTargets($keywords, $branchLabel),
            $this->articleTargets($topic),
        );

        $unique = [];

        foreach ($targets as $target) {
            $unique[$target['url']] = $target;
        }

        return array_values($unique);
    }

    /**
     * Ziele, die zwingend im Text landen muessen: Kategorie- und Stadtseiten.
     *
     * @param  array<int, array{type: string, label: string, url: string, anchor: string, note: string}>  $targets
     * @return array<int, array{type: string, label: string, url: string, anchor: string, note: string}>
     */
    public function portalTargets(array $targets): array
    {
        return array_values(array_filter(
            $targets,
            static fn (array $target): bool => $target['type'] !== 'article',
        ));
    }

    /**
     * @return array<int, string>
     */
    private function keywords(TopicCandidate $topic): array
    {
        $keywords = [trim((string) $topic->primary_keyword)];

        foreach ((array) ($topic->secondary_keywords_json ?? []) as $keyword) {
            if (is_string($keyword) && trim($keyword) !== '') {
                $keywords[] = trim($keyword);
            }
        }

        return array_values(array_unique(array_filter($keywords)));
    }

    /**
     * Stadtseiten: bei regionalem Zuschnitt die Region selbst, danach die
     * Staedte mit dem groessten Betriebsbestand — dort bringt der Verweis dem
     * Verzeichnis am meisten.
     *
     * @return array<int, array{type: string, label: string, url: string, anchor: string, note: string}>
     */
    private function cityTargets(TopicCandidate $topic, string $branchLabel): array
    {
        $targets = [];
        $used = [];
        $code = trim((string) $topic->region_code);

        if ($topic->region_scope === 'city' && $code !== '') {
            $city = City::query()
                ->where('slug', Str::slug($code))
                ->orWhere('name', $code)
                ->first();

            if ($city !== null) {
                $targets[] = $this->cityTarget($city, $branchLabel);
                $used[] = (int) $city->getKey();
            }
        }

        $limit = max(1, 3 - count($targets));

        $cities = City::query()
            ->withCount('companies')
            ->whereKeyNot($used)
            ->orderByDesc('companies_count')
            ->limit($limit)
            ->get();

        foreach ($cities as $city) {
            if ((int) ($city->companies_count ?? 0) === 0) {
                continue;
            }

            $targets[] = $this->cityTarget($city, $branchLabel);
        }

        return $targets;
    }

    /**
     * @return array{type: string, label: string, url: string, anchor: string, note: string}
     */
    private function cityTarget(City $city, string $branchLabel): array
    {
        $name = (string) $city->name;
        $slug = (string) ($city->slug ?: Str::slug($name));

        return [
            'type' => 'city',
            'label' => $name,
            'url' => "/staedte/{$slug}",
            'anchor' => mb_strtolower($branchLabel).' in '.$name,
            'note' => "Stadtseite {$name}",
        ];
    }

    /**
     * Kategorieseiten, die zu den Keywords des Themas passen. Der Abgleich
     * laeuft ueber den Kategorienamen, nicht ueber eine Zuordnungstabelle:
     * die Kategorien des Verzeichnisses sind Branchenbegriffe und decken sich
     * mit den Suchbegriffen des Themas.
     *
     * @param  array<int, string>  $keywords
     * @return array<int, array{type: string, label: string, url: string, anchor: string, note: string}>
     */
    private function categoryTargets(array $keywords, string $branchLabel): array
    {
        $query = Category::query()->select(['id', 'name', 'slug']);

        if ($keywords !== []) {
            $query->where(function ($inner) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    foreach ($this->words($keyword) as $word) {
                        $inner->orWhere('name', 'like', "%{$word}%");
                    }
                }
            });
        }

        $categories = $query->limit(3)->get();

        if ($categories->isEmpty()) {
            // Ohne Treffer bleibt die Uebersicht: sie ist immer vorhanden und
            // fuehrt dennoch in das Verzeichnis.
            $categories = Category::query()
                ->select(['id', 'name', 'slug'])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->limit(2)
                ->get();
        }

        return $categories->map(fn (Category $category): array => [
            'type' => 'category',
            'label' => (string) $category->name,
            'url' => '/kategorien/'.(string) $category->slug,
            'anchor' => 'Betriebe fuer '.(string) $category->name,
            'note' => 'Kategorieseite '.(string) $category->name.' im '.$branchLabel.'-Verzeichnis',
        ])->all();
    }

    /**
     * Bestehende Ratgeber desselben Mandanten, nach Aehnlichkeit des
     * Embeddings. Ohne Embedding am Kandidaten (kein Voyage-Aufruf im
     * Scoring) bleibt die Liste leer — geraten wird hier nicht.
     *
     * @return array<int, array{type: string, label: string, url: string, anchor: string, note: string}>
     */
    private function articleTargets(TopicCandidate $topic): array
    {
        $embedding = array_map('floatval', (array) ($topic->embedding_json ?? []));

        if ($embedding === []) {
            return [];
        }

        $max = max(0, (int) config('content.generation.internal_links.max_articles', 3));

        if ($max === 0) {
            return [];
        }

        $minSimilarity = (float) config('content.generation.internal_links.min_similarity', 0.55);
        $maxSimilarity = (float) config('content.topics.similarity.tenant_duplicate', 0.80);

        $scored = $this->scoredFingerprints($embedding, $minSimilarity, $maxSimilarity);

        if ($scored === []) {
            return [];
        }

        $posts = Post::query()
            ->published()
            ->whereIn('id', array_keys($scored))
            ->get(['id', 'title', 'slug'])
            ->keyBy('id');

        $targets = [];

        foreach ($scored as $articleId => $similarity) {
            $post = $posts->get($articleId);

            if ($post === null) {
                continue;
            }

            $targets[] = [
                'type' => 'article',
                'label' => (string) $post->title,
                'url' => '/ratgeber/'.(string) $post->slug,
                'anchor' => Str::limit((string) $post->title, 70, ''),
                'note' => 'bestehender Ratgeber, Aehnlichkeit '.number_format($similarity, 2, ',', ''),
            ];

            if (count($targets) >= $max) {
                break;
            }
        }

        return $targets;
    }

    /**
     * Fingerprints des Mandanten im Aehnlichkeitskorridor, aehnlichste zuerst.
     *
     * Die Embeddings werden erst nach dem Vorfilter geladen: ein Vektor ist
     * einige Kilobyte gross, und ein Mandant hat nach einem Jahr mehrere
     * hundert Artikel.
     *
     * @param  array<int, float>  $embedding
     * @return array<int, float> article_id => similarity
     */
    private function scoredFingerprints(
        array $embedding,
        float $minSimilarity,
        float $maxSimilarity,
    ): array {
        /** @var Collection<int, ContentFingerprint> $fingerprints */
        $fingerprints = ContentFingerprint::query()
            ->forTenant((int) (tenant()?->getKey() ?? 0))
            ->whereNotNull('embedding_json')
            ->orderByDesc('published_at')
            ->limit(max(50, (int) config('content.generation.internal_links.candidate_pool', 300)))
            ->get(['id', 'article_id', 'embedding_json', 'primary_keyword']);

        $scored = [];

        foreach ($fingerprints as $fingerprint) {
            $other = array_map('floatval', (array) $fingerprint->embedding_json);

            if ($other === []) {
                continue;
            }

            $similarity = DuplicateChecker::cosine($embedding, $other);

            if ($similarity < $minSimilarity || $similarity >= $maxSimilarity) {
                continue;
            }

            $scored[(int) $fingerprint->article_id] = $similarity;
        }

        arsort($scored);

        return $scored;
    }

    /**
     * Wortstaemme eines Suchbegriffs, die fuer einen LIKE-Vergleich taugen.
     *
     * @return array<int, string>
     */
    private function words(string $keyword): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($keyword)) ?: [];

        return array_values(array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 4,
        ));
    }
}
