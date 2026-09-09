<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleMetricRaw;
use App\Content\Models\Central\SourceSetting;
use App\Content\Models\SeasonalTopic;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Bewertet Themenkandidaten (#12).
 *
 * Sechs Teilscores, alle auf einer Skala von 0 bis 100, gewichtet zum
 * Gesamtscore. Die Gewichte kommen aus
 * tenant_content_settings.scoring_weights_json, ersatzweise aus
 * config('content.topics.weights') — Trend 30, Nachfrage 25, Luecke 20,
 * Saison 15, Eigenstaendigkeit 10, Performance 10. Die Summe wird vor der
 * Rechnung auf 1,0 normiert.
 *
 *   trend       Wie stark und wie frisch sind die Signale hinter dem Thema?
 *               Grundlage ist signal_strength der Rohsignale, mit einem
 *               Altersabschlag: ein Trend von gestern zaehlt voll, einer von
 *               vor sieben Tagen halb.
 *   demand      Suchvolumen, ersatzweise Impressionen der Search Console,
 *               logarithmisch normiert. Logarithmisch, weil der Unterschied
 *               zwischen 50 und 500 Suchen im Monat mehr wiegt als der
 *               zwischen 5.000 und 5.450.
 *   gap         Fehlt zum Thema ein eigener Ratgeber? Volle Punktzahl, wenn
 *               keine eigene Seite dazu Impressionen hat; abnehmend, je
 *               besser der Bestand schon rankt.
 *   seasonal    Liegt das Thema im Fenster eines Saisonthemas (#13) inklusive
 *               Vorlauf?
 *   uniqueness  Abstand zum naechsten bekannten Artikel im Portalnetz
 *               (1 - Cosine), aus der DuplicateChecker-Pruefung.
 *   performance Wie gut haben Artikel desselben Clusters und derselben
 *               Region bisher abgeschnitten? Grundlage ist die woechentliche
 *               Lernschleife (#23) in
 *               tenant_content_settings.cluster_performance_json; ein eigener
 *               Abruf findet nicht statt.
 *
 * Alle Teilscores landen zusammen mit ihren Gewichten in
 * topic_candidates.score_breakdown_json — die Redaktion muss im Dashboard
 * sehen koennen, warum ein Thema oben steht.
 *
 * Der Scorer ruft selbst keine kostenpflichtigen Dienste auf. Suchvolumen
 * wird verwendet, wenn ein Rohsignal es mitgeliefert hat (DataForSEO-Trends,
 * #8/#10); ein eigener Abruf je Kandidat waere bei 30 Kandidaten x 20
 * Mandanten taeglich teurer als die Artikel selbst. Der Generator (#14) holt
 * das vollstaendige Suchergebnisbild ohnehin fuer die ausgewaehlten Themen.
 */
class TopicScorer
{
    /**
     * Teilscore ohne Erfahrungswert: weder Vorteil noch Nachteil.
     */
    private const PERFORMANCE_NEUTRAL = 50.0;

    /** @var array<int, SourceItem>|null */
    private ?array $sourceItems = null;

    /**
     * Gewicht je Quelle in Prozent (#55). Nur die Abweichungen; was fehlt,
     * zaehlt mit 100.
     *
     * @var array<string, int>|null
     */
    private ?array $sourceWeights = null;

    /** @var Collection<int, SeasonalTopic>|null */
    private ?Collection $seasonalTopics = null;

    /** @var array<string, mixed> */
    private array $performance = [];

    private bool $performanceLoaded = false;

    private ?string $metricsWindow = null;

    private bool $metricsWindowLoaded = false;

    public function __construct(
        private readonly TenantContentSetting $settings,
    ) {}

    /**
     * Berechnet alle Teilscores und den Gesamtscore und schreibt sie in den
     * Kandidaten. Gespeichert wird nicht — das macht der aufrufende Job in
     * einer Transaktion.
     *
     * @param  array<string, mixed>  $context  optional: 'similarity' (float 0..1)
     */
    public function score(TopicCandidate $topic, array $context = []): TopicCandidate
    {
        $items = $this->itemsOf($topic);

        $scores = [
            'trend' => $this->trendScore($items),
            'demand' => $this->demandScore($topic, $items),
            'gap' => $this->gapScore($topic, $items),
            'seasonal' => $this->seasonalScore($topic, $items),
            'uniqueness' => $this->uniquenessScore($context['similarity'] ?? null),
            'performance' => $this->performanceScore($topic),
        ];

        $weights = $this->settings->normalizedScoringWeights();
        $total = 0.0;
        $breakdown = [];

        foreach ($scores as $key => $value) {
            $weight = (float) ($weights[$key] ?? 0.0);
            $total += $value * $weight;

            $breakdown[$key] = [
                'score' => round($value, 2),
                'weight' => round($weight, 4),
                'contribution' => round($value * $weight, 2),
            ];
        }

        $topic->forceFill([
            'trend_score' => round($scores['trend'], 2),
            'demand_score' => round($scores['demand'], 2),
            'gap_score' => round($scores['gap'], 2),
            'seasonal_score' => round($scores['seasonal'], 2),
            'uniqueness_score' => round($scores['uniqueness'], 2),
            'performance_score' => round($scores['performance'], 2),
            'total_score' => round($total, 2),
            'score_breakdown_json' => $breakdown,
        ]);

        return $topic;
    }

    /**
     * Staerke der Signale hinter dem Thema, mit Altersabschlag ueber das
     * Fenster der Themenfindung.
     *
     * @param  Collection<int, SourceItem>  $items
     */
    private function trendScore(Collection $items): float
    {
        if ($items->isEmpty()) {
            return 0.0;
        }

        $lookback = max(1, (int) config('content.topics.discover.lookback_days', 7));
        $now = CarbonImmutable::now();
        $best = 0.0;

        foreach ($items as $item) {
            $reference = $item->published_at ?? $item->fetched_at;
            $ageDays = $reference !== null ? max(0.0, $now->diffInDays($reference, absolute: true)) : $lookback;

            // Linear vom vollen Wert auf die Haelfte ueber das Fenster.
            $freshness = max(0.5, 1.0 - ($ageDays / max(1, $lookback)) * 0.5);
            $best = max($best, (float) $item->signal_strength * $freshness);
        }

        // Mehrere unabhaengige Quellen zum selben Thema sind ein staerkeres
        // Signal als eine einzelne — bis zu einem Zuschlag von 25 Prozent.
        // Eine auf Gewicht 0 gestellte Quelle zaehlt hier nicht mit, sonst
        // wirkte sie ueber die Hintertuer doch (#55).
        $distinct = $items
            ->reject(fn (SourceItem $item): bool => $this->weightOf((string) $item->source_key) <= 0.0)
            ->pluck('source_key')
            ->unique()
            ->count();
        $bonus = min(0.25, max(0, $distinct - 1) * 0.08);

        return min(100.0, ($best + $bonus) * 100.0);
    }

    /**
     * @param  Collection<int, SourceItem>  $items
     */
    private function demandScore(TopicCandidate $topic, Collection $items): float
    {
        $volume = $topic->search_volume !== null ? (int) $topic->search_volume : $this->volumeFrom($items);

        if ($volume !== null && $volume > 0) {
            $topic->forceFill(['search_volume' => $volume]);

            return $this->logScore($volume, (int) config('content.topics.demand.reference_volume', 2000));
        }

        $impressions = $this->impressionsFor($topic);

        if ($impressions > 0) {
            return $this->logScore($impressions, (int) config('content.topics.demand.reference_impressions', 5000));
        }

        // Ohne eigene Zahl bleibt der Hinweis der Search-Console-Luecken: der
        // Gap-Connector legt seinen demand_score in signal_strength ab (#9).
        $gapSignal = $items
            ->where('source_key', 'gsc_gap')
            ->max('signal_strength');

        return $gapSignal !== null ? min(100.0, (float) $gapSignal * 100.0) : 0.0;
    }

    /**
     * Suchvolumen aus einem Rohsignal, falls eine Quelle es mitgeliefert hat.
     *
     * @param  Collection<int, SourceItem>  $items
     */
    private function volumeFrom(Collection $items): ?int
    {
        $best = 0;

        foreach ($items as $item) {
            $payload = (array) ($item->payload_json ?? []);

            foreach (['search_volume', 'volume', 'monthly_searches'] as $key) {
                if (isset($payload[$key]) && is_numeric($payload[$key])) {
                    $best = max($best, (int) $payload[$key]);
                }
            }
        }

        return $best > 0 ? $best : null;
    }

    /**
     * Impressionen des juengsten Search-Console-Fensters zum Hauptkeyword.
     */
    private function impressionsFor(TopicCandidate $topic): int
    {
        $window = $this->metricsWindow();

        if ($window === null) {
            return 0;
        }

        return (int) ArticleMetricRaw::query()
            ->whereDate('window_end', $window)
            ->where('query_hash', ArticleMetricRaw::hash((string) $topic->primary_keyword))
            ->sum('impressions');
    }

    /**
     * Luecke: gibt es zum Thema noch keinen eigenen Ratgeber, der Sichtbarkeit
     * hat? Der Gap-Connector (#9) liefert dieselbe Frage bereits beantwortet;
     * ohne seine Signale wird der eigene Bestand direkt geprueft.
     *
     * @param  Collection<int, SourceItem>  $items
     */
    private function gapScore(TopicCandidate $topic, Collection $items): float
    {
        $gapItems = $items->where('source_key', 'gsc_gap');

        if ($gapItems->isNotEmpty()) {
            $types = $gapItems
                ->map(static fn (SourceItem $item): string => (string) (($item->payload_json['gap_type'] ?? '')))
                ->filter()
                ->all();

            // Nachfrage ohne eigenen Ratgeber ist die groesste Luecke, eine
            // Seite kurz hinter Seite 1 die kleinste.
            if (in_array('content_gap', $types, true)) {
                return 100.0;
            }

            if (in_array('snippet_issue', $types, true)) {
                return 70.0;
            }

            if (in_array('ranking_chance', $types, true)) {
                return 55.0;
            }
        }

        $window = $this->metricsWindow();

        if ($window === null) {
            // Ohne Search-Console-Anbindung ist die Luecke nicht messbar. Ein
            // mittlerer Wert verzerrt die Rangfolge am wenigsten.
            return 50.0;
        }

        $position = ArticleMetricRaw::query()
            ->whereDate('window_end', $window)
            ->where('is_article', true)
            ->where('query_hash', ArticleMetricRaw::hash((string) $topic->primary_keyword))
            ->min('position');

        if ($position === null) {
            return 100.0;
        }

        // Position 1 = keine Luecke, ab Position 30 ist der Bestand praktisch
        // unsichtbar und die Luecke wieder voll da.
        return min(100.0, max(0.0, ((float) $position - 1.0) / 29.0 * 100.0));
    }

    /**
     * @param  Collection<int, SourceItem>  $items
     */
    private function seasonalScore(TopicCandidate $topic, Collection $items): float
    {
        $fromSignal = $items
            ->where('source_key', 'seasonal_calendar')
            ->max('signal_strength');

        $best = $fromSignal !== null ? (float) $fromSignal * 100.0 : 0.0;

        $haystack = mb_strtolower($topic->primary_keyword.' '.$topic->title);

        foreach ($this->seasonal() as $seasonal) {
            if (! $this->matchesSeasonal($haystack, $seasonal)) {
                continue;
            }

            $best = max($best, $this->seasonalWindowScore($seasonal));
        }

        return min(100.0, $best);
    }

    private function matchesSeasonal(string $haystack, SeasonalTopic $seasonal): bool
    {
        $needles = array_merge(
            [(string) $seasonal->primary_keyword],
            array_map(static fn ($keyword): string => (string) $keyword, (array) ($seasonal->keywords_json ?? [])),
        );

        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim($needle));

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Volle Punktzahl im Fenster, 70 Punkte im Vorlauf, sonst nichts. Der
     * Vorlauf ist der eigentliche Zweck des Kalenders: der Artikel muss
     * stehen, bevor die Nachfrage kommt.
     */
    private function seasonalWindowScore(SeasonalTopic $seasonal): float
    {
        $today = CarbonImmutable::now();
        $month = (int) $today->month;
        $start = (int) $seasonal->start_month;
        $end = (int) $seasonal->end_month;

        $inWindow = $start <= $end
            ? ($month >= $start && $month <= $end)
            : ($month >= $start || $month <= $end);

        if ($inWindow) {
            return 100.0 * (float) $seasonal->weight;
        }

        $leadDays = max(0, (int) $seasonal->lead_time_days);
        $windowStart = $today->startOfMonth()->setMonth($start === 0 ? 1 : $start);

        if ($windowStart->lt($today)) {
            $windowStart = $windowStart->addYear();
        }

        return $today->addDays($leadDays)->gte($windowStart) ? 70.0 * (float) $seasonal->weight : 0.0;
    }

    /**
     * Erfahrungswert der Lernschleife (#23).
     *
     * Der Aggregator hat je Cluster und je Region einen Score von 0 bis 100
     * abgelegt: den Anteil der Gruppe am besten Wert fuer Klicks je Artikel.
     * Liegen beide vor, zaehlt der Cluster doppelt — er beschreibt das Thema
     * genauer als der Regionszuschnitt.
     *
     * Ohne belastbare Zahlen (noch keine Lernschleife gelaufen, Gruppe zu
     * klein, Thema ohne Cluster) gilt 50: ein neues Thema soll weder
     * bevorzugt noch bestraft werden, nur weil es keine Historie hat.
     */
    private function performanceScore(TopicCandidate $topic): float
    {
        $performance = $this->performance();

        if ($performance === []) {
            return self::PERFORMANCE_NEUTRAL;
        }

        $clusterScore = null;
        $clusterId = $topic->cluster_id === null ? null : (int) $topic->cluster_id;

        if ($clusterId !== null) {
            foreach ((array) ($performance['clusters'] ?? []) as $cluster) {
                if ((int) ($cluster['cluster_id'] ?? 0) === $clusterId && is_numeric($cluster['score'] ?? null)) {
                    $clusterScore = (float) $cluster['score'];
                    break;
                }
            }
        }

        $regionScore = null;
        $scope = (string) $topic->region_scope;
        $code = $topic->region_code === null ? null : (string) $topic->region_code;

        foreach ((array) ($performance['regions'] ?? []) as $region) {
            if ((string) ($region['scope'] ?? '') !== $scope) {
                continue;
            }

            if (($region['code'] ?? null) !== $code) {
                continue;
            }

            if (is_numeric($region['score'] ?? null)) {
                $regionScore = (float) $region['score'];
            }

            break;
        }

        if ($clusterScore === null && $regionScore === null) {
            return self::PERFORMANCE_NEUTRAL;
        }

        if ($clusterScore === null) {
            return min(100.0, max(0.0, (float) $regionScore));
        }

        if ($regionScore === null) {
            return min(100.0, max(0.0, $clusterScore));
        }

        return min(100.0, max(0.0, ($clusterScore * 2 + $regionScore) / 3));
    }

    /**
     * Die Rangliste der Lernschleife, einmal je Lauf gelesen.
     *
     * @return array<string, mixed>
     */
    private function performance(): array
    {
        if ($this->performanceLoaded) {
            return $this->performance;
        }

        $this->performanceLoaded = true;

        return $this->performance = (array) ($this->settings->cluster_performance_json ?? []);
    }

    /**
     * Eigenstaendigkeit: 1 minus die groesste gefundene Aehnlichkeit. Ohne
     * Vergleichswert gilt das Thema als eigenstaendig — die Ablehnung als
     * Duplikat passiert an anderer Stelle und nicht ueber einen niedrigen
     * Score.
     */
    private function uniquenessScore(mixed $similarity): float
    {
        if (! is_numeric($similarity)) {
            return 100.0;
        }

        return min(100.0, max(0.0, (1.0 - (float) $similarity) * 100.0));
    }

    /**
     * Logarithmische Normierung auf 0..100.
     */
    private function logScore(int $value, int $reference): float
    {
        $reference = max(2, $reference);

        return min(100.0, log10(1 + max(0, $value)) / log10(1 + $reference) * 100.0);
    }

    /**
     * Die Rohsignale hinter einem Kandidaten. Sie werden einmal je Lauf
     * geladen und nicht je Kandidat — sonst entstehen 30 Abfragen fuer
     * dieselben Zeilen.
     *
     * @return Collection<int, SourceItem>
     */
    private function itemsOf(TopicCandidate $topic): Collection
    {
        $ids = array_map('intval', (array) ($topic->source_item_ids_json ?? []));

        if ($ids === []) {
            return collect();
        }

        if ($this->sourceItems === null) {
            $items = SourceItem::query()
                ->where('fetched_at', '>=', CarbonImmutable::now()->subDays(
                    max(1, (int) config('content.topics.discover.lookback_days', 7)) * 2,
                ))
                ->get();

            // Die einzige Stelle, an der das Quellen-Gewicht (#55,
            // design/content-dashboard.md, §7a) wirkt. Damit sehen Trend-,
            // Nachfrage-, Luecken- und Saison-Teilscore denselben gewichteten
            // Wert; es gibt keine zweite Rechenregel. Die Modelle werden nur
            // im Speicher veraendert und nie gespeichert.
            foreach ($items as $item) {
                $item->signal_strength = min(1.0, max(
                    0.0,
                    (float) $item->signal_strength * $this->weightOf((string) $item->source_key),
                ));
            }

            $this->sourceItems = $items->keyBy('id')->all();
        }

        $items = [];

        foreach ($ids as $id) {
            if (isset($this->sourceItems[$id])) {
                $items[] = $this->sourceItems[$id];
            }
        }

        return collect($items);
    }

    /**
     * Gewicht einer Quelle als Faktor. Ohne Datensatz gilt 1,0.
     */
    private function weightOf(string $sourceKey): float
    {
        if ($this->sourceWeights === null) {
            $this->sourceWeights = [];

            foreach (SourceSetting::allKeyed() as $key => $setting) {
                $this->sourceWeights[$key] = $setting->weight;
            }
        }

        $weight = $this->sourceWeights[$sourceKey] ?? SourceSetting::DEFAULT_WEIGHT;

        return max(0, $weight) / 100;
    }

    /**
     * @return Collection<int, SeasonalTopic>
     */
    private function seasonal(): Collection
    {
        return $this->seasonalTopics ??= SeasonalTopic::query()->where('is_active', true)->get();
    }

    /**
     * Enddatum des juengsten Search-Console-Fensters, oder null ohne Daten.
     */
    private function metricsWindow(): ?string
    {
        if ($this->metricsWindowLoaded) {
            return $this->metricsWindow;
        }

        $this->metricsWindowLoaded = true;

        if (! Schema::hasTable('article_metrics_raw')) {
            return $this->metricsWindow = null;
        }

        $window = ArticleMetricRaw::query()->max('window_end');

        return $this->metricsWindow = $window !== null ? (string) $window : null;
    }
}
