<?php

declare(strict_types=1);

namespace App\Guide\Quality;

use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Fact;
use App\Guide\Research\FactNormalizer;
use App\Guide\Writing\HtmlAssembler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stufe 2 des Qualitaetsgates (#11): Faktenabgleich gegen das Fakten-Set.
 *
 * Aus dem ganzen Artikel (alle Abschnitte, Kurzantwort, FAQ, Titel und Meta,
 * Key-Facts-Tabelle) werden Zahlen in deutscher Schreibweise (1.234,56 €,
 * 15 %, 30 Tage), Prozente und Daten (31.12.2026, 1. Maerz 2026, 03/2026,
 * Maerz 2026) gezogen. Jede Fundstelle muss einem is_current-Fakt des Themas
 * entsprechen:
 *
 *  - belegt: Wert steht so im Fakten-Set (Wert, valid_from oder
 *    Veroeffentlichungsdatum der Quelle bei Daten);
 *  - gerundet: Abweichung hoechstens guide_lint.fact_check.tolerance (2 %);
 *  - widerspruch: Wert entspricht nur einem abgeloesten Fakt (is_current =
 *    false) — der Artikel nennt einen veralteten Stand. In der
 *    Key-Facts-Tabelle zusaetzlich jede Zeile, deren Wert nicht mehr dem
 *    aktuellen Fakt gleichen Schluessels entspricht (FactNormalizer);
 *  - unbelegt: kein Fakt passt.
 *
 * widerspruch und unbelegt sind blockierend: ein solcher Artikel wird nie
 * automatisch veroeffentlicht.
 *
 * Ausgenommen: Jahreszahlen ohne Einheit, Aufzaehlungsnummern und Ordinalia
 * ("1.", "3. Schritt"), Zahlen ohne Einheit unter
 * guide_lint.fact_check.ignore_bare_below ("3 Angebote"), Kennungen hinter
 * guide_lint.fact_check.reference_prefixes ("DIN 18534", "§ 35a") und
 * Zahlen, die an Buchstaben haengen ("CO2", "35a"). Prozentangaben passen nur
 * zu Fakten, die selbst in Prozent angegeben sind.
 */
class FactChecker
{
    public const STATUS_VERIFIED = 'belegt';

    public const STATUS_ROUNDED = 'gerundet';

    public const STATUS_CONTRADICTION = 'widerspruch';

    public const STATUS_MISSING = 'unbelegt';

    private const MONTHS = [
        'januar' => 1, 'jänner' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6,
        'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12,
    ];

    private const MASK = "\u{E000}";

    public function __construct(
        private readonly HtmlAssembler $html,
        private readonly FactNormalizer $normalizer,
    ) {}

    /**
     * @param  Collection<int, Fact>  $current  aktuelle Fakten mit Quelle
     * @param  Collection<int, Fact>  $previous  abgeloeste Fakten des Themas
     * @return list<array{wert: string, fact_key: ?string, status: string, section_id: string, sentence: string, fact_value: ?string}>
     */
    public function check(ArticleVersion $version, Collection $current, Collection $previous): array
    {
        if (! (bool) config('guide_lint.fact_check.enabled', true)) {
            return [];
        }

        $currentEvidence = $this->evidence($current, withDates: true);
        $previousEvidence = $this->evidence($previous, withDates: false);
        $results = [];

        foreach ($this->parts($version) as $sectionId => $text) {
            $seen = [];

            foreach ([...$this->dates($text), ...$this->numbers($text)] as $finding) {
                if (isset($seen[$finding['label']])) {
                    continue;
                }

                $seen[$finding['label']] = true;
                $results[] = $this->classify($finding, (string) $sectionId, $currentEvidence, $previousEvidence);
            }
        }

        return [...$results, ...$this->keyFactRows((array) ($version->key_facts_json ?? []), $current)];
    }

    /**
     * Blockierende Befunde.
     *
     * @param  list<array{status: string}>  $results
     * @return list<array<string, mixed>>
     */
    public function failures(array $results): array
    {
        return array_values(array_filter(
            $results,
            fn (array $result): bool => in_array($result['status'], [self::STATUS_MISSING, self::STATUS_CONTRADICTION], true),
        ));
    }

    /**
     * Anteil belegter Fundstellen, 0 bis 100.
     *
     * @param  list<array{status: string}>  $results
     */
    public function score(array $results): float
    {
        if ($results === []) {
            return 100.0;
        }

        return round((count($results) - count($this->failures($results))) / count($results) * 100, 1);
    }

    /**
     * Pruefbare Teile des Artikels, Schluessel = Fundort (Abschnitts-id bzw.
     * short_answer, faq, meta).
     *
     * @return array<string, string>
     */
    private function parts(ArticleVersion $version): array
    {
        $parts = [];

        foreach ($this->html->split((string) $version->body_html) as $id => $segment) {
            $parts[$id === '' ? 'intro' : (string) $id] = Linter::plainText($segment);
        }

        $parts['short_answer'] = Linter::plainText((string) $version->short_answer);
        $parts['faq'] = Linter::plainText(implode("\n", array_map(
            fn (mixed $item): string => is_array($item) ? '<p>'.($item['question'] ?? '').'</p><p>'.($item['answer'] ?? '').'</p>' : '',
            (array) ($version->faq_json ?? []),
        )));
        $parts['meta'] = Linter::plainText('<p>'.$version->title.'</p><p>'.$version->meta_title.'</p><p>'.$version->meta_description.'</p>');

        return array_filter($parts, fn (string $text): bool => $text !== '');
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @return list<array{key: string, value: string, numbers: list<float>, percent: bool, dates: list<string>}>
     */
    private function evidence(Collection $facts, bool $withDates): array
    {
        return $facts->map(function (Fact $fact) use ($withDates): array {
            $text = trim(((string) $fact->value).' '.((string) $fact->unit));
            $dates = array_map(fn (array $date): string => $date['iso'], $this->dates($text));
            preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $text, $iso);
            $dates = [...$dates, ...($iso[0] ?? [])];

            if ($withDates) {
                $dates[] = (string) $fact->valid_from?->toDateString();
                $dates[] = (string) $fact->source?->published_at?->toDateString();
            }

            return [
                'key' => (string) $fact->key,
                'value' => $text,
                'numbers' => $this->plainNumbers($this->maskDates($text)),
                'percent' => preg_match('/%|prozent/iu', $text) === 1,
                'dates' => array_values(array_filter(array_unique($dates))),
            ];
        })->values()->all();
    }

    /**
     * @param  array{label: string, sentence: string, kind: string, value?: float, alt?: ?float, percent?: bool, iso?: string, month_only?: bool}  $finding
     * @param  list<array{key: string, value: string, numbers: list<float>, percent: bool, dates: list<string>}>  $current
     * @param  list<array{key: string, value: string, numbers: list<float>, percent: bool, dates: list<string>}>  $previous
     * @return array{wert: string, fact_key: ?string, status: string, section_id: string, sentence: string, fact_value: ?string}
     */
    private function classify(array $finding, string $sectionId, array $current, array $previous): array
    {
        $match = $this->match($finding, $current);
        $status = $match === null ? null : ($match['exact'] ? self::STATUS_VERIFIED : self::STATUS_ROUNDED);

        if ($match === null) {
            $match = $this->match($finding, $previous);
            $status = $match === null ? self::STATUS_MISSING : self::STATUS_CONTRADICTION;
        }

        return [
            'wert' => $finding['label'],
            'fact_key' => $match['key'] ?? null,
            'status' => $status,
            'section_id' => $sectionId,
            'sentence' => $finding['sentence'],
            'fact_value' => $match['value'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  list<array{key: string, value: string, numbers: list<float>, percent: bool, dates: list<string>}>  $evidence
     * @return array{key: string, value: string, exact: bool}|null
     */
    private function match(array $finding, array $evidence): ?array
    {
        $rounded = null;

        foreach ($evidence as $entry) {
            if ($finding['kind'] === 'date') {
                foreach ($entry['dates'] as $date) {
                    $same = $finding['month_only'] ? str_starts_with($date, $finding['iso']) : $date === $finding['iso'];

                    if ($same) {
                        return ['key' => $entry['key'], 'value' => $entry['value'], 'exact' => true];
                    }
                }

                continue;
            }

            if ($finding['percent'] && ! $entry['percent']) {
                continue;
            }

            foreach ($entry['numbers'] as $candidate) {
                foreach (array_filter([$finding['value'], $finding['alt']], fn (?float $value): bool => $value !== null) as $value) {
                    if (abs($candidate - $value) < 0.0001) {
                        return ['key' => $entry['key'], 'value' => $entry['value'], 'exact' => true];
                    }

                    $tolerance = (float) config('guide_lint.fact_check.tolerance', 0.02);

                    if ($value != 0.0 && abs($candidate - $value) / abs($value) <= $tolerance) {
                        $rounded ??= ['key' => $entry['key'], 'value' => $entry['value'], 'exact' => false];
                    }
                }
            }
        }

        return $rounded;
    }

    /**
     * Key-Facts-Zeilen muessen dem aktuellen Fakt ihres Schluessels
     * entsprechen; verglichen wird auf Normalform (FactNormalizer).
     *
     * @param  array<int, mixed>  $rows
     * @param  Collection<int, Fact>  $current
     * @return list<array{wert: string, fact_key: ?string, status: string, section_id: string, sentence: string, fact_value: ?string}>
     */
    private function keyFactRows(array $rows, Collection $current): array
    {
        $byKey = $current->keyBy('key');
        $results = [];

        foreach ($rows as $row) {
            if (! is_array($row) || trim((string) ($row['key'] ?? '')) === '') {
                continue;
            }

            $key = (string) $row['key'];
            $label = trim(((string) ($row['value'] ?? '')).' '.((string) ($row['unit'] ?? '')));
            /** @var Fact|null $fact */
            $fact = $byKey->get($key);
            $same = $fact !== null && $this->normalizer->same(
                ['value' => (string) ($row['value'] ?? ''), 'unit' => $row['unit'] ?? null],
                ['value' => (string) $fact->value, 'unit' => $fact->unit],
            );

            $results[] = [
                'wert' => $label,
                'fact_key' => $key,
                'status' => $same ? self::STATUS_VERIFIED : self::STATUS_CONTRADICTION,
                'section_id' => 'key_facts',
                'sentence' => trim(((string) ($row['label'] ?? $key)).': '.$label),
                'fact_value' => $fact === null ? null : trim(((string) $fact->value).' '.((string) $fact->unit)),
            ];
        }

        return $results;
    }

    /**
     * Daten im Text: TT.MM.JJJJ, "1. Maerz 2026", MM/JJJJ, "Maerz 2026".
     *
     * @return list<array{label: string, sentence: string, kind: string, iso: string, month_only: bool}>
     */
    private function dates(string $text): array
    {
        $months = implode('|', array_keys(self::MONTHS));
        $patterns = [
            '/(?<![\d.])(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4})(?!\d)/u' => fn (array $m): array => [(int) $m[3], (int) $m[2], (int) $m[1]],
            '/(?<![\d.])(\d{1,2})\.\s+('.$months.')\s+(\d{4})(?!\d)/iu' => fn (array $m): array => [(int) $m[3], self::MONTHS[mb_strtolower($m[2])], (int) $m[1]],
            '/(?<![\d.\/])(\d{1,2})\/(\d{4})(?!\d)/u' => fn (array $m): array => [(int) $m[2], (int) $m[1], null],
            '/(?<![\p{L}])('.$months.')\s+(\d{4})(?!\d)/iu' => fn (array $m): array => [(int) $m[2], self::MONTHS[mb_strtolower($m[1])], null],
        ];

        $found = [];
        $taken = [];

        foreach ($patterns as $pattern => $parts) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $offset = (int) $match[0][1];

                // Ein Treffer eines genaueren Musters ueberdeckt die Stelle schon.
                if ($this->overlaps($taken, $offset, strlen($match[0][0]))) {
                    continue;
                }

                [$year, $month, $day] = $parts(array_map(fn (array $group): string => (string) $group[0], $match));

                if ($month < 1 || $month > 12 || ($day !== null && ! checkdate($month, $day, $year))) {
                    continue;
                }

                $taken[] = [$offset, strlen($match[0][0])];
                $found[] = [
                    'label' => trim($match[0][0]),
                    'sentence' => $this->sentenceAt($text, $offset),
                    'kind' => 'date',
                    'iso' => $day === null ? sprintf('%04d-%02d', $year, $month) : Carbon::create($year, $month, $day)->toDateString(),
                    'month_only' => $day === null,
                ];
            }
        }

        return $found;
    }

    /**
     * Pruefpflichtige Zahlen eines Textes, Daten vorher ausgeblendet.
     *
     * @return list<array{label: string, sentence: string, kind: string, value: float, alt: ?float, percent: bool}>
     */
    private function numbers(string $text): array
    {
        $masked = $this->maskDates($text);
        [$minYear, $maxYear] = array_map('intval', (array) config('guide_lint.fact_check.year_range', [1900, 2100]));
        $ignoreBelow = (float) config('guide_lint.fact_check.ignore_bare_below', 13);
        $pattern = '/(?<![\p{L}\d.,\/])(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:,\d+)?)(?![\d])(?:\s?('.$this->unitPattern().')(?![\p{L}\d]))?/u';

        if (preg_match_all($pattern, $masked, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $found = [];

        foreach ($matches as $match) {
            $raw = (string) $match[1][0];
            $offset = (int) $match[0][1];
            $unit = trim((string) ($match[2][0] ?? ''));
            $after = substr($masked, $offset + strlen($match[0][0]), 2);
            $value = $this->parse($raw);

            if ($unit === '') {
                // An Buchstaben haengend ("35a", "2x"): Kennung, kein Wert.
                if (preg_match('/^\p{L}/u', $after) === 1) {
                    continue;
                }

                if ($value == (int) $value && $value >= $minYear && $value <= $maxYear && preg_match('/^\d{4}$/', $raw) === 1) {
                    continue;
                }

                if ($value < $ignoreBelow) {
                    continue;
                }

                // Ordinalzahl oder Aufzaehlung ("3. Schritt", "1. Antrag stellen").
                if ($value < 100 && str_starts_with($after, '.') && ! str_contains($raw, ',')) {
                    continue;
                }
            }

            if ($this->hasReferencePrefix(substr($masked, 0, $offset))) {
                continue;
            }

            $found[] = [
                'label' => trim("{$raw} {$unit}"),
                'sentence' => $this->sentenceAt($masked, $offset),
                'kind' => 'number',
                'value' => $value,
                'alt' => $this->scaled($value, $unit),
                'percent' => preg_match('/^(%|prozent)/iu', $unit) === 1,
            ];
        }

        return $found;
    }

    /**
     * "1,5 Mio." ist 1.500.000; beide Lesarten zaehlen als Beleg.
     */
    private function scaled(float $value, string $unit): ?float
    {
        return match (mb_strtolower($unit)) {
            'mio.', 'millionen' => $value * 1_000_000,
            'mrd.', 'milliarden' => $value * 1_000_000_000,
            default => null,
        };
    }

    private function hasReferencePrefix(string $before): bool
    {
        $before = rtrim($before);

        foreach ((array) config('guide_lint.fact_check.reference_prefixes', []) as $prefix) {
            $prefix = (string) $prefix;

            if ($prefix !== '' && preg_match('/(?<![\p{L}])'.preg_quote($prefix, '/').'$/iu', $before) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alle Zahlen einer Belegstelle, ohne Filter.
     *
     * @return list<float>
     */
    private function plainNumbers(string $text): array
    {
        preg_match_all('/(?<![\d.,])(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[.,]\d+)?)(?![\d])/u', $text, $matches);

        return array_values(array_unique(array_map(fn (string $raw): float => $this->parse($raw), $matches[1] ?? [])));
    }

    /**
     * Deutsche Schreibweise: Punkt = Tausender, Komma = Dezimaltrenner. Ein
     * einzelner Punkt mit ein bis zwei Nachkommastellen ("2.5") kommt aus
     * maschinellen Quellen und ist ein Dezimalpunkt.
     */
    private function parse(string $raw): float
    {
        if (preg_match('/^\d+\.\d{1,2}$/', $raw) === 1) {
            return (float) $raw;
        }

        return (float) str_replace(',', '.', str_replace('.', '', $raw));
    }

    private function maskDates(string $text): string
    {
        foreach (array_reverse($this->dates($text)) as $date) {
            $text = str_replace($date['label'], self::MASK, $text);
        }

        return $text;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $taken
     */
    private function overlaps(array $taken, int $offset, int $length): bool
    {
        foreach ($taken as [$start, $size]) {
            if ($offset < $start + $size && $start < $offset + $length) {
                return true;
            }
        }

        return false;
    }

    private function unitPattern(): string
    {
        $units = array_map(fn (mixed $unit): string => preg_quote((string) $unit, '/'), (array) config('guide_lint.fact_check.units', []));

        // Laengste Einheit zuerst, sonst gewinnt 'm' gegen 'm²'.
        usort($units, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $units === [] ? '(?!)' : implode('|', $units);
    }

    private function sentenceAt(string $text, int $offset): string
    {
        // Byte-Offsets aus PREG_OFFSET_CAPTURE; Mehrbyte-Grenzen raeumt mb_convert_encoding auf.
        $start = strrpos(substr($text, 0, $offset), '. ');
        $start = $start === false ? 0 : $start + 2;
        $end = strpos($text, '. ', $offset);
        $length = $end === false ? 240 : min(240, $end - $start + 1);
        $sentence = mb_convert_encoding(substr($text, $start, max(1, $length)), 'UTF-8', 'UTF-8');

        return Str::limit(trim(str_replace(self::MASK, '…', ltrim($sentence, '. '))), 200, '');
    }
}
