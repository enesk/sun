<?php

declare(strict_types=1);

namespace App\Content\Quality;

use App\Content\Models\DraftSource;
use App\Content\Models\FactSnippet;
use App\Content\Support\EvidenceMarks;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stufe 2 des Qualitaetsgates: jede Zahl im Artikel braucht einen Beleg (#15).
 *
 * Aus dem Fliesstext werden alle Zahlen in deutscher Schreibweise gezogen
 * (1.234,56 €, 15 %, 30 Tage) und gegen die Faktenschnipsel und
 * Quellenausschnitte des Entwurfs gehalten. Wird eine Zahl dort nicht
 * wiedergefunden, ist sie blockierend — ein Artikel mit einer unbelegten Zahl
 * wird nie automatisch freigegeben.
 *
 * Bewusst ausgenommen:
 *
 *  - Jahreszahlen. Sie sind Zeitangaben, keine Sachangaben, und stehen so gut
 *    wie nie in einem Faktenschnipsel.
 *  - Nackte kleine Zahlen ohne Einheit ("in 3 Schritten"). Sie zaehlen
 *    Aufzaehlungen und waeren sonst der Hauptteil der Fundstellen.
 *
 * Gerundete Werte gelten als belegt, solange sie hoechstens
 * config('content_seo_rules.fact_check.tolerance') vom Beleg abweichen — "rund
 * 1.200 Betriebe" zu einem Beleg von 1.187 ist keine erfundene Zahl. Der
 * Bericht weist sie als 'gerundet' aus.
 */
final class FactChecker
{
    public const STATUS_VERIFIED = 'belegt';

    public const STATUS_ROUNDED = 'gerundet';

    public const STATUS_MISSING = 'unbelegt';

    /**
     * @param  Collection<int, FactSnippet>  $snippets
     * @param  Collection<int, DraftSource>  $sources
     * @return array{facts: array<int, array<string, mixed>>, blocking: array<int, string>, score: float, checked: int, missing: int}
     */
    public function check(string $html, Collection $snippets, Collection $sources): array
    {
        if (! (bool) config('content_seo_rules.fact_check.enabled', true)) {
            return ['facts' => [], 'blocking' => [], 'score' => 100.0, 'checked' => 0, 'missing' => 0];
        }

        $evidence = $this->evidence($snippets, $sources);
        $found = $this->numbers($this->plainText($html));

        $facts = [];
        $blocking = [];
        $verified = 0;

        foreach ($found as $number) {
            $match = $this->match($number['value'], $evidence);

            $status = match (true) {
                $match === null => self::STATUS_MISSING,
                $match['exact'] => self::STATUS_VERIFIED,
                default => self::STATUS_ROUNDED,
            };

            if ($status === self::STATUS_MISSING) {
                $blocking[] = __('Nicht belegte Zahl „:number" im Artikeltext: :sentence', [
                    'number' => $number['label'],
                    'sentence' => $number['sentence'],
                ]);
            } else {
                $verified++;
            }

            $facts[] = [
                // Schluessel der Abnahme (#15): Zahl, Fundstelle, Status.
                'zahl' => $number['label'],
                'gefunden_in_snippet' => $match['snippet'] ?? null,
                'status' => $status,

                // Zusaetzliche Schluessel fuer die Pruefflaeche (#20), die den
                // Bericht ueber den QualityReportPresenter liest.
                'statement' => $number['sentence'],
                'verdict' => $status,
                'source' => $match['source'] ?? null,
                'url' => $match['url'] ?? null,
                'note' => $match === null ? null : $match['value_label'],
                'stale' => $status === self::STATUS_MISSING,
            ];
        }

        $checked = count($facts);

        return [
            'facts' => $facts,
            'blocking' => array_values(array_unique($blocking)),
            'score' => $checked === 0 ? 100.0 : round(($verified / $checked) * 100, 1),
            'checked' => $checked,
            'missing' => $checked - $verified,
        ];
    }

    /**
     * Belegstellen: Faktenschnipsel zuerst, danach die Quellenausschnitte.
     * Jede Belegstelle traegt die Zahlen, die in ihr vorkommen.
     *
     * @param  Collection<int, FactSnippet>  $snippets
     * @param  Collection<int, DraftSource>  $sources
     * @return array<int, array{text: string, source: ?string, url: ?string, numbers: array<int, float>}>
     */
    private function evidence(Collection $snippets, Collection $sources): array
    {
        $evidence = [];

        foreach ($snippets as $snippet) {
            $text = trim((string) $snippet->statement.' '.(string) $snippet->value.' '.(string) $snippet->unit);

            $evidence[] = [
                'text' => $text,
                'source' => $snippet->source_name,
                'url' => $snippet->source_url,
                'numbers' => $this->plainNumbers($text),
            ];
        }

        foreach ($sources as $source) {
            $text = trim((string) $source->title.' '.(string) $source->snippet);

            $evidence[] = [
                'text' => $text,
                'source' => $source->publisher ?: $source->title,
                'url' => $source->url,
                'numbers' => $this->plainNumbers($text),
            ];
        }

        return $evidence;
    }

    /**
     * @param  array<int, array{text: string, source: ?string, url: ?string, numbers: array<int, float>}>  $evidence
     * @return array{snippet: string, source: ?string, url: ?string, exact: bool, value_label: string}|null
     */
    private function match(float $value, array $evidence): ?array
    {
        $tolerance = (float) config('content_seo_rules.fact_check.tolerance', 0.02);
        $rounded = null;

        foreach ($evidence as $entry) {
            foreach ($entry['numbers'] as $candidate) {
                $exact = abs($candidate - $value) < 0.0001;
                $within = $value != 0.0 && abs($candidate - $value) / max(abs($value), 0.0001) <= $tolerance;

                if (! $exact && ! $within) {
                    continue;
                }

                $hit = [
                    'snippet' => Str::limit(trim($entry['text']), 240, ''),
                    'source' => $entry['source'],
                    'url' => $entry['url'],
                    'exact' => $exact,
                    'value_label' => $this->format($candidate),
                ];

                if ($exact) {
                    return $hit;
                }

                $rounded ??= $hit;
            }
        }

        return $rounded;
    }

    /**
     * Pruefpflichtige Zahlen des Fliesstexts, jeweils mit dem Satz, in dem sie
     * stehen.
     *
     * @return array<int, array{label: string, value: float, sentence: string}>
     */
    private function numbers(string $text): array
    {
        $unitPattern = $this->unitPattern();
        [$minYear, $maxYear] = array_map('intval', (array) config('content_seo_rules.fact_check.year_range', [1900, 2100]));
        $ignoreBelow = (float) config('content_seo_rules.fact_check.ignore_bare_below', 20);

        $pattern = '/(?<![\d.,])(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:,\d+)?)(?![\d.,])(\s*(?:'.$unitPattern.'))?/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $numbers = [];
        $seen = [];

        foreach ($matches[1] as $index => $capture) {
            $raw = (string) $capture[0];
            $offset = (int) $capture[1];
            $unit = trim((string) ($matches[2][$index][0] ?? ''));
            $value = $this->parse($raw);

            // Jahreszahl ohne Einheit: Zeitangabe, kein Fakt.
            if ($unit === '' && ! str_contains($raw, ',') && ! str_contains($raw, '.')
                && $value >= $minYear && $value <= $maxYear && $value == (int) $value) {
                continue;
            }

            if ($unit === '' && $value < $ignoreBelow) {
                continue;
            }

            $label = trim($raw.' '.$unit);

            // Dieselbe Zahl mehrfach im Text ist ein Beleg, nicht zwei.
            if (isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;

            $numbers[] = [
                'label' => $label,
                'value' => $value,
                'sentence' => $this->sentenceAt($text, $offset),
            ];
        }

        return $numbers;
    }

    /**
     * Alle Zahlen einer Belegstelle, ohne Filter: hier zaehlt jede Zahl als
     * moeglicher Beleg.
     *
     * @return array<int, float>
     */
    private function plainNumbers(string $text): array
    {
        preg_match_all('/(?<![\d.,])(\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:[.,]\d+)?)(?![\d.,])/u', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $raw): float => $this->parse($raw),
            $matches[1] ?? [],
        )));
    }

    /**
     * Deutsche Schreibweise: Punkt ist Tausendertrenner, Komma ist
     * Dezimaltrenner. Ein einzelner Punkt mit ein bis zwei Nachkommastellen
     * ("2.5") kommt aus maschinellen Quellen und wird als Dezimalpunkt
     * gelesen.
     */
    private function parse(string $raw): float
    {
        if (preg_match('/^\d+\.\d{1,2}$/', $raw) === 1) {
            return (float) $raw;
        }

        return (float) str_replace(',', '.', str_replace('.', '', $raw));
    }

    private function format(float $value): string
    {
        return $value == (int) $value
            ? number_format($value, 0, ',', '.')
            : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function unitPattern(): string
    {
        $units = array_map(
            static fn ($unit): string => preg_quote((string) $unit, '/'),
            (array) config('content_seo_rules.fact_check.units', []),
        );

        // Laengste Einheit zuerst, sonst gewinnt 'm' gegen 'm²'.
        usort($units, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $units === [] ? '(?!)' : implode('|', $units);
    }

    private function sentenceAt(string $text, int $offset): string
    {
        // PREG_OFFSET_CAPTURE liefert Byte-Offsets, deshalb hier bewusst die
        // Byte-Funktionen; die Mehrbyte-Grenzen raeumt mb_convert_encoding auf.
        $start = strrpos(substr($text, 0, $offset), '. ');
        $start = $start === false ? 0 : $start + 2;

        $end = strpos($text, '. ', $offset);
        $length = $end === false ? 240 : min(240, $end - $start + 1);

        $sentence = mb_convert_encoding(substr($text, $start, max(1, $length)), 'UTF-8', 'UTF-8');

        return Str::limit(trim(ltrim($sentence, '. ')), 200, '');
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<\/(p|li|h2|h3|td|th|blockquote)>/i', '. ', $html) ?? $html;

        // Stehengebliebene Belegmarken zuerst: "[F117]" waere sonst die
        // unbelegte Zahl 117 (#76).
        $html = EvidenceMarks::strip($html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))) ?? '');
    }
}
