<?php

declare(strict_types=1);

namespace App\Guide\Import;

/**
 * Macht aus den Zellen einer Zeile (CSV, XLSX oder eingefuegter Text) eine
 * gepruefte TopicRow.
 *
 * Mit Kopfzeile werden die Spalten ueber ihren Namen zugeordnet (siehe
 * HEADER_ALIASES). Ohne Kopfzeile gilt die Reihenfolge
 * "Kategorie; Frage; Ueberschriften; Notizen; Prioritaet"; eine Zeile mit
 * nur einer Zelle ist die Frage allein.
 */
class TopicRowParser
{
    public const FIELD_QUESTION = 'question';

    public const FIELD_CATEGORY = 'category';

    public const FIELD_HEADINGS = 'headings';

    public const FIELD_NOTES = 'notes';

    public const FIELD_PRIORITY = 'priority';

    public const FIELD_REFRESH_INTERVAL = 'refresh_interval_days';

    private const QUESTION_MIN_LENGTH = 5;

    private const QUESTION_MAX_LENGTH = 500;

    private const CATEGORY_MAX_LENGTH = 120;

    private const NOTES_MAX_LENGTH = 5000;

    private const PRIORITY_MAX = 65535;

    private const REFRESH_INTERVAL_MAX = 365;

    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        self::FIELD_QUESTION => ['frage', 'question', 'thema', 'topic', 'titel'],
        self::FIELD_CATEGORY => ['kategorie', 'category', 'rubrik'],
        self::FIELD_HEADINGS => ['headings', 'ueberschriften', 'überschriften', 'gliederung', 'outline'],
        self::FIELD_NOTES => ['notes', 'notizen', 'notiz', 'hinweise', 'anmerkungen'],
        self::FIELD_PRIORITY => ['priority', 'prioritaet', 'priorität', 'prio'],
        self::FIELD_REFRESH_INTERVAL => ['refresh_interval_days', 'intervall', 'pruefintervall', 'prüfintervall'],
    ];

    /**
     * Spaltenfolge ohne Kopfzeile.
     *
     * @var array<int, string>
     */
    private const POSITIONAL = [
        self::FIELD_CATEGORY,
        self::FIELD_QUESTION,
        self::FIELD_HEADINGS,
        self::FIELD_NOTES,
        self::FIELD_PRIORITY,
        self::FIELD_REFRESH_INTERVAL,
    ];

    public function __construct(
        private readonly HeadingsParser $headings,
        private readonly PlaceholderResolver $placeholders,
        private readonly QuestionNormalizer $normalizer,
    ) {}

    /**
     * Spaltenzuordnung, wenn die Zeile eine Kopfzeile ist, sonst null.
     *
     * @param  array<int, mixed>  $cells
     * @return array<string, int>|null Feld => Spaltenindex
     */
    public function headerMap(array $cells): ?array
    {
        $map = [];

        foreach (array_values($cells) as $index => $cell) {
            $name = mb_strtolower(trim((string) $cell));

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($name, $aliases, true) && ! isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }

        return isset($map[self::FIELD_QUESTION]) ? $map : null;
    }

    /**
     * Zuordnung ohne Kopfzeile, wie parse() sie ohne headerMap anwendet:
     * eine Spalte ist die Frage, sonst die Reihenfolge aus POSITIONAL.
     *
     * @return array<string, int> Feld => Spaltenindex
     */
    public function defaultMap(int $columnCount): array
    {
        if ($columnCount <= 1) {
            return [self::FIELD_QUESTION => 0];
        }

        return array_flip(array_slice(self::POSITIONAL, 0, $columnCount));
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>|null  $headerMap
     * @return TopicRow|null null bei einer leeren Zeile
     *
     * @throws InvalidTopicRow
     */
    public function parse(array $cells, ?array $headerMap, int $line): ?TopicRow
    {
        $cells = array_map(static fn (mixed $cell): string => self::clean((string) $cell), array_values($cells));

        while ($cells !== [] && end($cells) === '') {
            array_pop($cells);
        }

        if ($cells === []) {
            return null;
        }

        $values = $this->assign($cells, $headerMap);

        $question = $this->placeholders->canonicalize($values[self::FIELD_QUESTION] ?? '');
        $category = $this->placeholders->canonicalize($values[self::FIELD_CATEGORY] ?? '');
        $headings = $this->placeholders->canonicalize($values[self::FIELD_HEADINGS] ?? '');
        $notes = $values[self::FIELD_NOTES] ?? '';

        if ($question === '') {
            throw new InvalidTopicRow('Frage fehlt.');
        }

        if (mb_strlen($question) < self::QUESTION_MIN_LENGTH) {
            throw new InvalidTopicRow(sprintf('Frage kürzer als %d Zeichen.', self::QUESTION_MIN_LENGTH));
        }

        if (mb_strlen($question) > self::QUESTION_MAX_LENGTH) {
            throw new InvalidTopicRow(sprintf('Frage länger als %d Zeichen.', self::QUESTION_MAX_LENGTH));
        }

        if (mb_strlen($category) > self::CATEGORY_MAX_LENGTH) {
            throw new InvalidTopicRow(sprintf('Kategorie länger als %d Zeichen.', self::CATEGORY_MAX_LENGTH));
        }

        if (mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
            throw new InvalidTopicRow(sprintf('Notizen länger als %d Zeichen.', self::NOTES_MAX_LENGTH));
        }

        $unknown = $this->placeholders->unknown("{$question} {$category} {$headings}");

        if ($unknown !== []) {
            throw new InvalidTopicRow('Unbekannter Platzhalter: '.implode(', ', $unknown).'.');
        }

        $normalized = $this->normalizer->normalize($question);

        if ($normalized === '') {
            throw new InvalidTopicRow('Frage besteht nur aus Füllwörtern.');
        }

        $outline = $headings !== '' ? $this->headings->parse($headings) : [];

        return new TopicRow(
            line: $line,
            question: $question,
            normalizedQuestion: $normalized,
            categoryName: $category !== '' ? $category : null,
            outline: $outline !== [] ? $outline : null,
            notes: $notes !== '' ? $notes : null,
            priority: $this->integer($values[self::FIELD_PRIORITY] ?? '', 'Priorität', 0, self::PRIORITY_MAX) ?? 0,
            refreshIntervalDays: $this->integer($values[self::FIELD_REFRESH_INTERVAL] ?? '', 'Prüfintervall', 1, self::REFRESH_INTERVAL_MAX),
        );
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>|null  $headerMap
     * @return array<string, string>
     */
    private function assign(array $cells, ?array $headerMap): array
    {
        if ($headerMap !== null) {
            return array_map(static fn (int $index): string => $cells[$index] ?? '', $headerMap);
        }

        if (count($cells) === 1) {
            return [self::FIELD_QUESTION => $cells[0]];
        }

        $values = [];

        foreach (self::POSITIONAL as $index => $field) {
            $values[$field] = $cells[$index] ?? '';
        }

        return $values;
    }

    private function integer(string $value, string $label, int $min, int $max): ?int
    {
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', $value) || (int) $value < $min || (int) $value > $max) {
            throw new InvalidTopicRow("{$label} muss eine ganze Zahl zwischen {$min} und {$max} sein, ist \"{$value}\".");
        }

        return (int) $value;
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
