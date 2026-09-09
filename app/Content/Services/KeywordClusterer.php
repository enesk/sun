<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\KeywordCluster;
use App\Content\Models\TopicCandidate;
use Illuminate\Support\Str;

/**
 * Buendelt Themenkandidaten zu Keyword-Clustern (#12).
 *
 * Ein Cluster ist die Antwort auf die Frage "ist das dasselbe Thema?".
 * Kandidaten mit einer Cosine-Aehnlichkeit ab
 * config('content.topics.similarity.cluster_merge') gehoeren zusammen; der
 * staerkste von ihnen (hoechster Gesamtscore) bestimmt Name und Hauptkeyword.
 *
 * Das Cluster ist danach an drei Stellen der Hebel: die Tagesauswahl bestraft
 * zwei Themen aus demselben Cluster (Diversitaet), der SERP-Dienst (#10) haengt
 * seinen Zwischenspeicher daran, und die Leistungsansicht (#25) rechnet je
 * Cluster statt je Artikel.
 *
 * Der Zentroid ist der Mittelwert der Mitgliedsvektoren und wird bei jedem
 * Zuordnen fortgeschrieben — so wandert ein Cluster mit seinem Inhalt mit,
 * ohne dass alle Artikel neu eingebettet werden muessen.
 */
class KeywordClusterer
{
    /**
     * Ordnet Kandidaten Clustern zu. Reihenfolge zaehlt: die Liste wird nach
     * Gesamtscore absteigend abgearbeitet, damit der staerkste Kandidat das
     * Cluster praegt und nicht der zufaellig erste.
     *
     * @param  array<int, array{topic: TopicCandidate, embedding: array<int, float>}>  $entries
     * @return array<int, KeywordCluster> cluster_id => Cluster
     */
    public function assign(array $entries): array
    {
        $threshold = (float) config('content.topics.similarity.cluster_merge', 0.92);

        usort(
            $entries,
            static fn (array $a, array $b): int => ($b['topic']->total_score ?? 0) <=> ($a['topic']->total_score ?? 0),
        );

        /** @var array<int, array{cluster: KeywordCluster, vectors: array<int, array<int, float>>}> $working */
        $working = [];
        $clusters = [];

        foreach ($entries as $entry) {
            $topic = $entry['topic'];
            $embedding = $entry['embedding'];

            if ($embedding === []) {
                continue;
            }

            $cluster = $this->matchWithinRun($working, $embedding, $threshold)
                ?? $this->matchExisting($embedding, $threshold);

            if ($cluster === null) {
                $cluster = $this->create($topic, $embedding);
            }

            $id = (int) $cluster->getKey();
            $working[$id] ??= ['cluster' => $cluster, 'vectors' => []];
            $working[$id]['vectors'][] = $embedding;

            $topic->forceFill(['cluster_id' => $id]);
            $clusters[$id] = $cluster;
        }

        foreach ($working as $id => $state) {
            $this->refresh($state['cluster'], $state['vectors']);
            $clusters[$id] = $state['cluster'];
        }

        return $clusters;
    }

    /**
     * Cluster, das in diesem Lauf schon einen aehnlichen Kandidaten
     * aufgenommen hat.
     *
     * @param  array<int, array{cluster: KeywordCluster, vectors: array<int, array<int, float>>}>  $working
     * @param  array<int, float>  $embedding
     */
    private function matchWithinRun(array $working, array $embedding, float $threshold): ?KeywordCluster
    {
        $best = null;
        $bestSimilarity = $threshold;

        foreach ($working as $state) {
            foreach ($state['vectors'] as $vector) {
                $similarity = DuplicateChecker::cosine($embedding, $vector);

                if ($similarity >= $bestSimilarity) {
                    $best = $state['cluster'];
                    $bestSimilarity = $similarity;
                }
            }
        }

        return $best;
    }

    /**
     * Bestehendes Cluster mit passendem Zentroid. Verglichen wird nur gegen
     * Cluster mit gespeichertem Zentroid — ein Cluster ohne Vektor kann nicht
     * beurteilt werden und bekommt keinen Zufallstreffer.
     *
     * @param  array<int, float>  $embedding
     */
    private function matchExisting(array $embedding, float $threshold): ?KeywordCluster
    {
        $best = null;
        $bestSimilarity = $threshold;

        KeywordCluster::query()
            ->whereNotNull('centroid_json')
            ->cursor()
            ->each(function (KeywordCluster $cluster) use ($embedding, &$best, &$bestSimilarity): void {
                $centroid = $cluster->centroid_json;

                if (! is_array($centroid) || $centroid === []) {
                    return;
                }

                $similarity = DuplicateChecker::cosine(
                    $embedding,
                    array_map(static fn ($value): float => (float) $value, $centroid),
                );

                if ($similarity >= $bestSimilarity) {
                    $best = $cluster;
                    $bestSimilarity = $similarity;
                }
            });

        return $best;
    }

    /**
     * @param  array<int, float>  $embedding
     */
    private function create(TopicCandidate $topic, array $embedding): KeywordCluster
    {
        $keyword = (string) $topic->primary_keyword;

        return KeywordCluster::create([
            'name' => Str::limit((string) ($topic->title ?: $keyword), 255, ''),
            'slug' => $this->uniqueSlug($keyword),
            'primary_keyword' => $keyword,
            'keywords_json' => $this->keywordsOf($topic),
            'centroid_json' => $embedding,
            'region_scope' => (string) $topic->region_scope,
            'region_code' => $topic->region_code,
        ]);
    }

    /**
     * Schreibt Zentroid und Keywordliste des Clusters fort.
     *
     * @param  array<int, array<int, float>>  $vectors
     */
    private function refresh(KeywordCluster $cluster, array $vectors): void
    {
        $centroid = $this->centroid(array_merge(
            $vectors,
            is_array($cluster->centroid_json) && $cluster->centroid_json !== []
                ? [array_map(static fn ($value): float => (float) $value, $cluster->centroid_json)]
                : [],
        ));

        $keywords = collect($cluster->topicCandidates()->pluck('primary_keyword'))
            ->merge((array) ($cluster->keywords_json ?? []))
            ->map(static fn ($keyword): string => mb_strtolower(trim((string) $keyword)))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        $cluster->forceFill([
            'centroid_json' => $centroid,
            'keywords_json' => $keywords,
        ])->save();
    }

    /**
     * @param  array<int, array<int, float>>  $vectors
     * @return array<int, float>
     */
    private function centroid(array $vectors): array
    {
        $vectors = array_values(array_filter($vectors, static fn (array $vector): bool => $vector !== []));

        if ($vectors === []) {
            return [];
        }

        $length = count($vectors[0]);
        $sum = array_fill(0, $length, 0.0);

        foreach ($vectors as $vector) {
            if (count($vector) !== $length) {
                continue;
            }

            for ($i = 0; $i < $length; $i++) {
                $sum[$i] += $vector[$i];
            }
        }

        $count = count($vectors);

        return array_map(static fn (float $value): float => round($value / $count, 6), $sum);
    }

    /**
     * @return array<int, string>
     */
    private function keywordsOf(TopicCandidate $topic): array
    {
        return collect([(string) $topic->primary_keyword])
            ->merge((array) ($topic->secondary_keywords_json ?? []))
            ->map(static fn ($keyword): string => mb_strtolower(trim((string) $keyword)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Der Slug ist in `keyword_clusters` eindeutig; ein zweites Cluster zum
     * gleichen Begriff bekommt einen Zaehler statt eines Fehlers.
     */
    private function uniqueSlug(string $keyword): string
    {
        $base = Str::limit(Str::slug($keyword) ?: 'cluster', 200, '');
        $slug = $base;
        $suffix = 2;

        while (KeywordCluster::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
