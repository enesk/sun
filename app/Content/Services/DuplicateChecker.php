<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Llm\EmbeddingClient;
use App\Content\Llm\LlmContext;
use App\Content\Models\ArticleMetricRaw;
use App\Content\Models\Central\ContentFingerprint;
use App\Content\Models\TopicCandidate;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Duplikats- und Kannibalisierungspruefung der Themenfindung (#12).
 *
 * Zwei getrennte Fragen, zwei getrennte Datenquellen:
 *
 *  - Duplikat: gibt es zu diesem Thema schon einen Artikel? Geprueft wird
 *    gegen die zentralen `content_fingerprints`, also gegen das ganze
 *    Portalnetz. Ein fremdes Portal darf naeher am Thema liegen als das
 *    eigene (0.88) — ein eigener Artikel schon bei 0.80, weil sich zwei
 *    Ratgeber desselben Portals gegenseitig die Sichtbarkeit nehmen.
 *
 *  - Kannibalisierung: rankt der Mandant zu diesem Keyword bereits gut? Dafuer
 *    braucht es keinen Text-, sondern einen Positionsvergleich. Grundlage sind
 *    die Search-Console-Rohzeilen in `article_metrics_raw` (#9) — kein
 *    zusaetzlicher API-Aufruf, damit das Scoring nichts kostet.
 *
 * MariaDB hat keinen Vektor-Index, deshalb zwei Stufen: grob ueber die
 * SimHash-Hamming-Distanz und die Branche vorfiltern, dann die Cosine-
 * Aehnlichkeit der Voyage-Embeddings in PHP rechnen.
 *
 * Der SimHash hier ist die massgebliche Fassung fuer die ganze Pipeline; der
 * Publisher (#21) registriert Fingerprints mit derselben Methode. Er nutzt
 * 60 der 64 Bits, damit der Wert auf jeder Plattform in einen vorzeichen-
 * behafteten PHP-Integer passt und nicht stillschweigend zu float wird.
 */
class DuplicateChecker
{
    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_CANNIBALIZATION = 'cannibalization';

    /** Genutzte Bits des SimHash. */
    private const SIMHASH_BITS = 60;

    /** @var array<string, array<int, int>>|null Branche => tenant_ids */
    private ?array $branchTenants = null;

    public function __construct(
        private readonly EmbeddingClient $embeddings,
    ) {}

    /**
     * Vektoren zu mehreren Kandidatentexten in einem Voyage-Aufruf.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> gleiche Reihenfolge wie die Eingabe
     */
    public function embedMany(array $texts, ?LlmContext $context = null): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->embeddings->embedMany($texts, EmbeddingClient::INPUT_DOCUMENT, $context);
    }

    /**
     * Der Text, aus dem Embedding und SimHash eines Kandidaten entstehen:
     * Hauptkeyword plus Nebenkeywords (Ticket #12), nicht der Titel — der
     * Titel ist Formulierung, das Keyword ist das Thema.
     */
    public function textFor(TopicCandidate $topic): string
    {
        $keywords = array_filter(array_map(
            static fn ($keyword): string => is_string($keyword) ? trim($keyword) : '',
            (array) ($topic->secondary_keywords_json ?? []),
        ));

        return trim((string) $topic->primary_keyword.' '.implode(' ', $keywords));
    }

    /**
     * Naechster Nachbar im Portalnetz und im eigenen Bestand.
     *
     * @param  array<int, float>  $embedding
     * @return array{reason: ?string, similarity: float, scope: ?string, match: ?string, article_id: ?int, tenant_id: ?int}
     */
    public function check(array $embedding, int $simhash, int $tenantId, ?string $branch = null): array
    {
        $best = [
            'reason' => null,
            'similarity' => 0.0,
            'scope' => null,
            'match' => null,
            'article_id' => null,
            'tenant_id' => null,
        ];

        if ($embedding === []) {
            return $best;
        }

        $networkThreshold = (float) config('content.topics.similarity.network_duplicate', 0.88);
        $tenantThreshold = (float) config('content.topics.similarity.tenant_duplicate', 0.80);

        foreach ($this->nearestFingerprints($embedding, $simhash, $tenantId, $branch) as $candidate) {
            if ($candidate['similarity'] <= $best['similarity']) {
                continue;
            }

            $own = (int) $candidate['tenant_id'] === $tenantId;
            $threshold = $own ? $tenantThreshold : $networkThreshold;

            $best = [
                'reason' => $candidate['similarity'] >= $threshold ? self::REASON_DUPLICATE : null,
                'similarity' => $candidate['similarity'],
                'scope' => $own ? 'tenant' : 'network',
                'match' => $candidate['primary_keyword'],
                'article_id' => $candidate['article_id'],
                'tenant_id' => $candidate['tenant_id'],
            ];
        }

        return $best;
    }

    /**
     * Rankt der Mandant zu diesem Thema schon auf Position <= 7? Dann waere
     * ein neuer Artikel Konkurrenz zum eigenen Bestand.
     *
     * Verglichen wird gegen die Suchanfragen des juengsten
     * Search-Console-Fensters, und nur gegen Zeilen eigener Ratgeber
     * (is_article). Ohne Search-Console-Daten gibt es keine Ablehnung —
     * fehlende Daten sind kein Beleg.
     *
     * @return array{position: float, impressions: int, query: string, page: string}|null
     */
    public function cannibalization(TopicCandidate $topic): ?array
    {
        if (! Schema::hasTable('article_metrics_raw')) {
            return null;
        }

        $windowEnd = ArticleMetricRaw::query()->max('window_end');

        if ($windowEnd === null) {
            return null;
        }

        $keyword = mb_strtolower(trim((string) $topic->primary_keyword));

        if ($keyword === '') {
            return null;
        }

        $row = ArticleMetricRaw::query()
            ->whereDate('window_end', $windowEnd)
            ->where('is_article', true)
            ->where('impressions', '>=', max(1, (int) config('content.topics.cannibalization.min_impressions', 10)))
            ->where('query_hash', ArticleMetricRaw::hash($keyword))
            ->orderBy('position')
            ->first();

        if ($row === null || $row->position === null) {
            return null;
        }

        $maxPosition = (float) config('content.topics.cannibalization.max_position', 7.0);

        if ((float) $row->position > $maxPosition) {
            return null;
        }

        return [
            'position' => (float) $row->position,
            'impressions' => (int) $row->impressions,
            'query' => (string) $row->query,
            'page' => (string) $row->page_path,
        ];
    }

    /**
     * Fingerprints innerhalb der Hamming-Grenze, mit ihrer Cosine-Aehnlichkeit.
     *
     * Der Vorfilter laeuft in zwei Schritten, weil die Embeddings gross sind:
     * erst nur id und simhash laden und die Hamming-Distanz rechnen, dann die
     * Embeddings der wenigen Treffer nachladen.
     *
     * @param  array<int, float>  $embedding
     * @return array<int, array{similarity: float, tenant_id: int, article_id: int, primary_keyword: string}>
     */
    private function nearestFingerprints(array $embedding, int $simhash, int $tenantId, ?string $branch): array
    {
        $threshold = max(0, (int) config('content.topics.similarity.hamming_prefilter', 12));
        $tenantIds = $this->tenantsInBranch($branch, $tenantId);

        $ids = [];

        ContentFingerprint::query()
            ->select(['id', 'simhash'])
            ->when($tenantIds !== [], fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->orderByDesc('id')
            ->cursor()
            ->each(function (ContentFingerprint $fingerprint) use (&$ids, $simhash, $threshold): void {
                if (ContentFingerprint::hammingDistance((int) $fingerprint->simhash, $simhash) <= $threshold) {
                    $ids[] = (int) $fingerprint->getKey();
                }
            });

        if ($ids === []) {
            return [];
        }

        $matches = [];

        ContentFingerprint::query()
            ->whereIn('id', $ids)
            ->cursor()
            ->each(function (ContentFingerprint $fingerprint) use (&$matches, $embedding): void {
                $other = $fingerprint->embedding_json;

                if (! is_array($other) || $other === []) {
                    return;
                }

                $matches[] = [
                    'similarity' => self::cosine($embedding, array_map(static fn ($value): float => (float) $value, $other)),
                    'tenant_id' => (int) $fingerprint->tenant_id,
                    'article_id' => (int) $fingerprint->article_id,
                    'primary_keyword' => (string) $fingerprint->primary_keyword,
                ];
            });

        return $matches;
    }

    /**
     * Mandanten derselben Branche. Ein Ratgeber ueber Waermepumpen kann kein
     * Duplikat eines Ratgebers ueber Zahnersatz sein, also muss er auch nicht
     * dagegen geprueft werden.
     *
     * @return array<int, int> leer = kein Branchenfilter moeglich
     */
    private function tenantsInBranch(?string $branch, int $tenantId): array
    {
        if ($branch === null) {
            return [];
        }

        if ($this->branchTenants !== null) {
            return $this->branchTenants[$branch] ?? [$tenantId];
        }

        $map = [];

        foreach (Tenant::all() as $tenant) {
            $key = BranchResolver::resolve($tenant);

            if ($key === null) {
                continue;
            }

            $map[$key][] = (int) $tenant->getKey();
        }

        $this->branchTenants = $map;

        return $map[$branch] ?? [$tenantId];
    }

    /**
     * 60-Bit-SimHash ueber die Woerter eines Textes. Gleiche Wortmengen
     * ergeben denselben Wert, eine geaenderte Formulierung nur wenige
     * Bitunterschiede — genau das braucht der Vorfilter.
     */
    public static function simhash(string $text): int
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return 0;
        }

        $vector = array_fill(0, self::SIMHASH_BITS, 0);

        foreach ($tokens as $token) {
            // 15 Hexstellen = 60 Bit; hexdec bleibt damit sicher im
            // Integer-Bereich und kippt nicht auf float.
            $hash = (int) hexdec(substr(hash('sha256', $token), 0, 15));

            for ($bit = 0; $bit < self::SIMHASH_BITS; $bit++) {
                $vector[$bit] += ($hash >> $bit) & 1 ? 1 : -1;
            }
        }

        $simhash = 0;

        for ($bit = 0; $bit < self::SIMHASH_BITS; $bit++) {
            if ($vector[$bit] > 0) {
                $simhash |= 1 << $bit;
            }
        }

        return $simhash;
    }

    /**
     * Cosine-Aehnlichkeit zweier gleich langer Vektoren, 0.0 bei
     * unterschiedlicher Laenge oder Nullvektor.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $length = count($a);

        if ($length === 0 || $length !== count($b)) {
            Log::debug('Cosine ohne Ergebnis: Vektorlaengen passen nicht.', [
                'a' => $length,
                'b' => count($b),
            ]);

            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
