<?php

declare(strict_types=1);

namespace App\Content\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Das Suchergebnisbild zu einem Zielkeyword (#10).
 *
 * Buendelt, was Scoring (#12) und Generator (#14) ueber die Konkurrenz zu
 * einem Keyword wissen muessen: wer auf Seite 1 steht und wie diese Seiten
 * gegliedert sind, welche Fragen Google selbst stellt, welche Varianten die
 * Autovervollstaendigung anbietet und wie gross die Nachfrage ist.
 *
 * Das DTO ist zugleich das Speicherformat: toArray() landet unverandert in
 * keyword_clusters.serp_json, fromArray() liest es zurueck. Deshalb sind die
 * Schluessel bewusst snake_case und ohne Objekte.
 */
final class SerpInsightDto
{
    /** Version des Speicherformats; aeltere Staende gelten als abgelaufen. */
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<int, string>  $paa  People-Also-Ask-Fragen, dedupliziert
     * @param  array<int, string>  $related  Related Searches
     * @param  array<int, string>  $autocomplete  Autocomplete-Varianten
     * @param  array<int, array{position: int, title: string, url: string, domain: string, own_network: bool, h2s: array<int, string>, word_count: int}>  $topResults
     */
    public function __construct(
        public readonly string $keyword,
        public readonly ?string $regionCode,
        public readonly int $locationCode,
        public readonly array $paa = [],
        public readonly array $related = [],
        public readonly array $autocomplete = [],
        public readonly array $topResults = [],
        public readonly ?int $searchVolume = null,
        public readonly ?float $cpc = null,
        public readonly ?string $competition = null,
        public readonly ?int $competitionIndex = null,
        public readonly ?CarbonImmutable $fetchedAt = null,
    ) {}

    /**
     * Ergebnisse, die auf eine Domain des eigenen Portalnetzes zeigen. Sie
     * zaehlen nicht als Wettbewerb, sondern als Kannibalisierungsrisiko (#12).
     *
     * @return array<int, array<string, mixed>>
     */
    public function ownNetworkResults(): array
    {
        return array_values(array_filter($this->topResults, static fn (array $result) => $result['own_network']));
    }

    /**
     * Ueberschriften der fremden Top-Ergebnisse, flach und dedupliziert —
     * die Gliederungsvorlage fuer die Outline (#14).
     *
     * @return array<int, string>
     */
    public function competitorHeadings(): array
    {
        $headings = [];

        foreach ($this->topResults as $result) {
            if ($result['own_network']) {
                continue;
            }

            foreach ($result['h2s'] as $heading) {
                $headings[mb_strtolower($heading)] = $heading;
            }
        }

        return array_values($headings);
    }

    /**
     * Median-Wortzahl der fremden Top-Ergebnisse, an der sich die Ziellaenge
     * des eigenen Artikels orientiert. Null, wenn keine Seite gelesen wurde.
     */
    public function medianWordCount(): ?int
    {
        $counts = array_values(array_filter(array_map(
            static fn (array $result) => $result['own_network'] ? 0 : $result['word_count'],
            $this->topResults,
        )));

        if ($counts === []) {
            return null;
        }

        sort($counts);
        $middle = intdiv(count($counts), 2);

        return count($counts) % 2 === 1
            ? $counts[$middle]
            : (int) round(($counts[$middle - 1] + $counts[$middle]) / 2);
    }

    public function isEmpty(): bool
    {
        return $this->topResults === [] && $this->paa === [] && $this->autocomplete === [];
    }

    /**
     * Frist der Zwischenspeicherung: aelter als $days Tage oder aus einer
     * frueheren Formatversion gilt als abgelaufen.
     */
    public function isFresh(int $days): bool
    {
        return $this->fetchedAt !== null && $this->fetchedAt->gt(CarbonImmutable::now()->subDays($days));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'keyword' => $this->keyword,
            'region_code' => $this->regionCode,
            'location_code' => $this->locationCode,
            'paa' => $this->paa,
            'related' => $this->related,
            'autocomplete' => $this->autocomplete,
            'top_results' => $this->topResults,
            'search_volume' => $this->searchVolume,
            'cpc' => $this->cpc,
            'competition' => $this->competition,
            'competition_index' => $this->competitionIndex,
            'fetched_at' => $this->fetchedAt?->toIso8601String(),
        ];
    }

    /**
     * Gegenstueck zu toArray(). Gibt null zurueck, wenn der Stand aus einer
     * anderen Formatversion stammt oder unbrauchbar ist.
     *
     * @param  array<string, mixed>|mixed  $data
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || (int) ($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            return null;
        }

        $keyword = is_string($data['keyword'] ?? null) ? trim($data['keyword']) : '';

        if ($keyword === '') {
            return null;
        }

        return new self(
            keyword: $keyword,
            regionCode: is_string($data['region_code'] ?? null) ? $data['region_code'] : null,
            locationCode: (int) ($data['location_code'] ?? 0),
            paa: self::strings($data['paa'] ?? []),
            related: self::strings($data['related'] ?? []),
            autocomplete: self::strings($data['autocomplete'] ?? []),
            topResults: self::results($data['top_results'] ?? []),
            searchVolume: isset($data['search_volume']) ? (int) $data['search_volume'] : null,
            cpc: isset($data['cpc']) ? (float) $data['cpc'] : null,
            competition: is_string($data['competition'] ?? null) ? $data['competition'] : null,
            competitionIndex: isset($data['competition_index']) ? (int) $data['competition_index'] : null,
            fetchedAt: is_string($data['fetched_at'] ?? null) ? CarbonImmutable::parse($data['fetched_at']) : null,
        );
    }

    /**
     * Ergebnis mit gesetztem Abrufzeitpunkt — der Service setzt ihn erst,
     * wenn wirklich abgerufen wurde.
     */
    public function withFetchedAt(DateTimeInterface $fetchedAt): self
    {
        return new self(
            $this->keyword,
            $this->regionCode,
            $this->locationCode,
            $this->paa,
            $this->related,
            $this->autocomplete,
            $this->topResults,
            $this->searchVolume,
            $this->cpc,
            $this->competition,
            $this->competitionIndex,
            CarbonImmutable::parse($fetchedAt),
        );
    }

    /**
     * @param  array<int, array{position: int, title: string, url: string, domain: string, own_network: bool, h2s: array<int, string>, word_count: int}>  $topResults
     */
    public function withTopResults(array $topResults): self
    {
        return new self(
            $this->keyword,
            $this->regionCode,
            $this->locationCode,
            $this->paa,
            $this->related,
            $this->autocomplete,
            $topResults,
            $this->searchVolume,
            $this->cpc,
            $this->competition,
            $this->competitionIndex,
            $this->fetchedAt,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($value) => is_string($value) ? trim($value) : '', $values),
            static fn (string $value) => $value !== '',
        ));
    }

    /**
     * @return array<int, array{position: int, title: string, url: string, domain: string, own_network: bool, h2s: array<int, string>, word_count: int}>
     */
    private static function results(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $results = [];

        foreach ($values as $value) {
            if (! is_array($value) || ! is_string($value['url'] ?? null)) {
                continue;
            }

            $results[] = [
                'position' => (int) ($value['position'] ?? 0),
                'title' => Str::limit((string) ($value['title'] ?? ''), 255, ''),
                'url' => $value['url'],
                'domain' => (string) ($value['domain'] ?? ''),
                'own_network' => (bool) ($value['own_network'] ?? false),
                'h2s' => self::strings($value['h2s'] ?? []),
                'word_count' => (int) ($value['word_count'] ?? 0),
            ];
        }

        return $results;
    }
}
