<?php

declare(strict_types=1);

namespace App\Guide\Import;

use App\Guide\Models\Central\TopicList;
use App\Guide\Models\Central\TopicListItem;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Liest eine Themenliste aus CSV, XLSX oder eingefuegtem Text und speichert
 * sie als guide_topic_list_items (Central-DB).
 *
 * Fehlerhafte Zeilen landen mit Zeilennummer und Grund im ImportReport, der
 * Import laeuft weiter. Duplikate (gleiche normalisierte Frage) innerhalb der
 * Quelle werden uebersprungen. Steht die Frage schon in der Liste, werden
 * Kategorie, Ueberschriften, Notizen, Prioritaet und Pruefintervall aus der
 * Quelle uebernommen (`updated`) oder, falls unveraendert, uebersprungen.
 */
class TopicListImporter
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_PASTE = 'paste';

    /**
     * Dateiendung => Format.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'csv' => self::FORMAT_CSV,
        'tsv' => self::FORMAT_CSV,
        'txt' => self::FORMAT_PASTE,
        'xlsx' => self::FORMAT_XLSX,
    ];

    public function __construct(
        private readonly TopicRowParser $parser,
        private readonly QuestionNormalizer $normalizer,
    ) {}

    public function importFile(TopicList $list, string $path): ImportReport
    {
        return $this->import($list, $this->readFile($path), $this->formatOf($path));
    }

    public function importText(TopicList $list, string $text): ImportReport
    {
        return $this->import($list, $this->readPasted($text), self::FORMAT_PASTE);
    }

    /**
     * Import mit fester Spaltenzuordnung aus dem Import-Wizard (#15) statt
     * der Erkennung ueber die Kopfzeile.
     *
     * @param  iterable<int, array<int, mixed>>  $rows  Zeilennummer => Zellen (readFile/readPasted)
     * @param  array<string, int>  $columnMap  Feld (TopicRowParser::FIELD_*) => Spaltenindex
     * @param  bool  $hasHeader  erste nicht leere Zeile ist eine Kopfzeile und wird uebersprungen
     */
    public function importMapped(TopicList $list, iterable $rows, string $format, array $columnMap, bool $hasHeader): ImportReport
    {
        return $this->import($list, $rows, $format, $columnMap, $hasHeader);
    }

    /**
     * Format einer Datei anhand der Endung.
     *
     * @throws InvalidArgumentException bei nicht unterstuetzter Endung
     */
    public function formatOf(string $path): string
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::EXTENSIONS[$extension]
            ?? throw new InvalidArgumentException("Nicht unterstütztes Dateiformat .{$extension} (erlaubt: csv, tsv, txt, xlsx).");
    }

    /**
     * Zeilen einer Datei, Zeilennummer => Zellen.
     *
     * @return iterable<int, array<int, mixed>>
     *
     * @throws InvalidArgumentException bei unlesbarer Datei oder falscher Endung
     */
    public function readFile(string $path): iterable
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Datei nicht lesbar: {$path}");
        }

        return match ($this->formatOf($path)) {
            self::FORMAT_XLSX => $this->xlsxRows($path),
            self::FORMAT_CSV => $this->csvRows($this->readText($path)),
            self::FORMAT_PASTE => $this->pastedRows($this->readText($path)),
        };
    }

    /**
     * Zeilen eines eingefuegten Textes, Zeilennummer => Zellen.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function readPasted(string $text): iterable
    {
        return $this->pastedRows($text);
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $rows  Zeilennummer => Zellen
     * @param  array<string, int>|null  $columnMap  feste Zuordnung; null = Kopfzeile erkennen
     */
    private function import(TopicList $list, iterable $rows, string $format, ?array $columnMap = null, bool $hasHeader = false): ImportReport
    {
        $report = new ImportReport;

        /** @var array<string, TopicListItem> $existing */
        $existing = [];

        /** @var TopicListItem $item */
        foreach ($list->items()->get() as $item) {
            $existing[$item->question_normalized ?? $this->normalizer->normalize($item->question)] ??= $item;
        }

        $position = (int) $list->items()->max('position');
        $seen = [];
        $headerMap = $columnMap;
        $firstRow = true;

        foreach ($rows as $line => $cells) {
            if ($firstRow && array_filter($cells, static fn (mixed $cell): bool => trim((string) $cell) !== '') !== []) {
                $firstRow = false;

                if ($columnMap === null) {
                    $headerMap = $this->parser->headerMap($cells);
                }

                // Kopfzeile: erkannt oder im Wizard als solche angegeben.
                if ($columnMap === null ? $headerMap !== null : $hasHeader) {
                    continue;
                }
            }

            try {
                $row = $this->parser->parse($cells, $headerMap, $line);
            } catch (InvalidTopicRow $e) {
                $report->addError($line, $e->getMessage());

                continue;
            }

            if ($row === null) {
                continue;
            }

            if (isset($seen[$row->normalizedQuestion])) {
                $report->skippedDuplicates++;

                continue;
            }

            $seen[$row->normalizedQuestion] = $line;
            $attributes = $this->attributes($row);

            if (isset($existing[$row->normalizedQuestion])) {
                $item = $existing[$row->normalizedQuestion];
                $item->fill($attributes);

                if (! $item->isDirty()) {
                    $report->skippedDuplicates++;

                    continue;
                }

                $item->save();
                $report->updated++;

                continue;
            }

            $list->items()->create([
                ...$attributes,
                'position' => ++$position,
                'question' => $row->question,
                'question_normalized' => $row->normalizedQuestion,
            ]);

            $report->imported++;
        }

        if ($list->source === null) {
            $list->update(['source' => $format]);
        }

        return $report;
    }

    /**
     * Felder, die ein erneuter Import einer vorhandenen Frage ueberschreibt.
     *
     * @return array<string, mixed>
     */
    private function attributes(TopicRow $row): array
    {
        return [
            'category_name' => $row->categoryName,
            'outline_json' => $row->outline,
            'notes' => $row->notes,
            'priority' => $row->priority,
            'refresh_interval_days' => $row->refreshIntervalDays,
        ];
    }

    /**
     * @return Generator<int, array<int, mixed>>
     */
    private function xlsxRows(string $path): Generator
    {
        $reader = new XlsxReader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    yield (int) $line => array_map(static fn (mixed $value): string => match (true) {
                        $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                        is_float($value) && floor($value) === $value => (string) (int) $value,
                        is_bool($value) => $value ? '1' : '0',
                        default => (string) $value,
                    }, $row->toArray());
                }

                // Nur das erste Tabellenblatt.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    private function csvRows(string $text): Generator
    {
        $firstLine = strtok($text, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $line = 0;

        try {
            while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                yield $line => $cells;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Eingefuegter Text: eine Frage je Zeile, Spalten mit Tab (aus Excel
     * kopiert) oder Semikolon getrennt.
     *
     * @return Generator<int, array<int, string|null>>
     */
    private function pastedRows(string $text): Generator
    {
        $lines = preg_split('/\R/u', $this->stripBom($text)) ?: [];

        foreach ($lines as $index => $content) {
            if (trim($content) === '' || str_starts_with(ltrim($content), '#')) {
                continue;
            }

            $delimiter = str_contains($content, "\t") ? "\t" : ';';

            yield $index + 1 => str_getcsv($content, $delimiter, '"', '');
        }
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            ',' => substr_count($line, ','),
        ];

        arsort($counts);

        return $counts[array_key_first($counts)] > 0 ? (string) array_key_first($counts) : ';';
    }

    /**
     * Datei als UTF-8; Excel speichert CSV unter Windows oft als Windows-1252.
     */
    private function readText(string $path): string
    {
        $text = $this->stripBom((string) file_get_contents($path));

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }
}
