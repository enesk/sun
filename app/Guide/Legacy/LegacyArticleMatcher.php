<?php

declare(strict_types=1);

namespace App\Guide\Legacy;

use App\Guide\Enums\TopicStatus;
use App\Guide\Import\QuestionNormalizer;
use App\Guide\Models\Topic;
use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * Findet Paare (Thema, Altartikel), deren Titel sich so aehnlich sind, dass
 * das Thema den Altartikel inhaltlich abdecken duerfte (#19).
 *
 * Altartikel sind Beitraege ohne Thema (`posts.guide_topic_id` leer) — aus
 * der alten Pipeline oder von Hand in der Verwaltung angelegt. Verglichen
 * wird auf der Normalform des QuestionNormalizer (klein, ohne Satzzeichen,
 * Stoppwoerter und Jahreszahlen), einmal in Originalreihenfolge und einmal
 * mit sortierten Woertern, damit "Kosten Badsanierung" und "Badsanierung
 * Kosten" zusammenfinden. Je Fassung zaehlt der Mittelwert aus similar_text
 * und Levenshtein-Quote, das Paar bekommt den hoeheren der beiden Werte.
 * Erwartet einen initialisierten Tenant-Kontext.
 */
class LegacyArticleMatcher
{
    /**
     * Ab dieser Aehnlichkeit (0..100) wird ein Paar gemeldet. Umformulierte
     * Titel zum selben Thema ("Was kostet eine Badsanierung?" / "Badsanierung:
     * Kosten im Ueberblick") liegen um 65, fremde Themen derselben Branche
     * um 55; eine Meldung zu viel kostet nur einen Klick im Dashboard.
     */
    public const DEFAULT_THRESHOLD = 60.0;

    public function __construct(private readonly QuestionNormalizer $normalizer) {}

    /**
     * Beitraege ohne Thema, egal in welchem Status.
     *
     * @return Builder<Post>
     */
    public static function legacyPosts(): Builder
    {
        return Post::query()->whereNull('guide_topic_id');
    }

    /**
     * Paare ueber der Schwelle, aehnlichste zuerst.
     *
     * @return list<array{topic: Topic, post: Post, similarity: float}>
     */
    public function overlaps(float $threshold = self::DEFAULT_THRESHOLD): array
    {
        $posts = self::legacyPosts()
            ->published()
            ->get(['id', 'title', 'slug', 'status', 'published_at', 'guide_category_id'])
            ->map(fn (Post $post): array => ['post' => $post, 'forms' => $this->forms((string) $post->title)])
            ->filter(fn (array $entry): bool => $entry['forms'] !== [])
            ->all();

        if ($posts === []) {
            return [];
        }

        $pairs = [];

        $topics = Topic::query()
            ->where('status', '!=', TopicStatus::ARCHIVED->value)
            ->get(['id', 'question', 'slug', 'status', 'article_id', 'guide_category_id']);

        foreach ($topics as $topic) {
            $topicForms = $this->forms((string) $topic->question);

            if ($topicForms === []) {
                continue;
            }

            foreach ($posts as $entry) {
                $similarity = $this->bestSimilarity($topicForms, $entry['forms']);

                if ($similarity >= $threshold) {
                    $pairs[] = ['topic' => $topic, 'post' => $entry['post'], 'similarity' => $similarity];
                }
            }
        }

        usort($pairs, fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return $pairs;
    }

    /**
     * Aehnlichkeit zweier Titel auf 0..100, eine Nachkommastelle.
     */
    public function similarity(string $a, string $b): float
    {
        $formsA = $this->forms($a);
        $formsB = $this->forms($b);

        if ($formsA === [] || $formsB === []) {
            return 0.0;
        }

        return $this->bestSimilarity($formsA, $formsB);
    }

    /**
     * @param  array{0: string, 1: string}  $a
     * @param  array{0: string, 1: string}  $b
     */
    private function bestSimilarity(array $a, array $b): float
    {
        return round(max($this->score($a[0], $b[0]), $this->score($a[1], $b[1])), 1);
    }

    private function score(string $a, string $b): float
    {
        if ($a === $b) {
            return 100.0;
        }

        similar_text($a, $b, $percent);

        // levenshtein() und similar_text() zaehlen Bytes, also auch die Laenge in Bytes.
        $levenshtein = (1 - levenshtein($a, $b) / max(strlen($a), strlen($b))) * 100;

        return ($percent + $levenshtein) / 2;
    }

    /**
     * Normalform in Originalreihenfolge und mit sortierten Woertern; leer,
     * wenn vom Titel nach der Normalisierung nichts uebrig bleibt.
     *
     * @return array{0: string, 1: string}|array{}
     */
    private function forms(string $title): array
    {
        $normalized = $this->normalizer->normalize($title);

        if ($normalized === '') {
            return [];
        }

        $words = explode(' ', $normalized);
        sort($words);

        return [$normalized, implode(' ', $words)];
    }
}
