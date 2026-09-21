<?php

declare(strict_types=1);

namespace App\Guide\Services;

use Illuminate\Support\Str;
use Jfcherng\Diff\SequenceMatcher;

/**
 * Gegenueberstellung zweier Artikelfassungen fuer die Pruefung (#20).
 *
 * Gebraucht wird sie, wenn ein bereits veroeffentlichter Artikel aktualisiert
 * wurde (Refresh-Loop, #24): der Pruefer soll nicht den ganzen Text noch
 * einmal lesen, sondern sehen, was sich geaendert hat.
 *
 * Die Zerlegung ist zweistufig (design/content-dashboard.md §4.1):
 *
 * Stufe 1 vergleicht auf Absatzebene, nicht auf Zeichenebene: der Fliesstext
 * liegt als HTML vor, und ein Vergleich innerhalb von Tags erzeugt kaputtes
 * Markup. Beide Seiten werden deshalb in Bloecke zerlegt und als Klartext
 * ueber die laengste gemeinsame Teilfolge abgeglichen.
 *
 * Stufe 2 zieht ein unmittelbar aufeinanderfolgendes Paar aus REMOVED und
 * ADDED zu einer CHANGED-Zeile zusammen, sobald die Bloecke einander auf
 * Wortebene aehnlich genug sind, und markiert darin nur die tatsaechlich
 * getauschten Woerter. Der Wortvergleich laeuft ueber jfcherng/php-diff und
 * ausschliesslich auf dem bereits per strip_tags() erzeugten Klartext; im
 * Ergebnis kommt als Markup nur <del> und <ins> vor, alles andere ist
 * entschaerft.
 */
final class ArticleDiffRenderer
{
    public const UNCHANGED = 'unchanged';

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    public const CHANGED = 'changed';

    public const COLLAPSED = 'collapsed';

    /**
     * Absaetze, die auf beiden Seiten unveraendert sind und weit von einer
     * Aenderung entfernt liegen, werden zusammengefasst.
     */
    private const CONTEXT_BLOCKS = 1;

    /**
     * Ab dieser Wort-Aehnlichkeit gilt ein Block als umformuliert und nicht
     * als ersetzt. Darunter bleiben es zwei getrennte Zeilen, denn dann hilft
     * keine Wortmarke mehr.
     */
    private const CHANGED_THRESHOLD = 0.5;

    /**
     * Kuerzung nur fuer unveraenderte Bloecke: bei einem geaenderten Absatz
     * kann die Kuerzung genau die geaenderte Stelle abschneiden.
     */
    private const UNCHANGED_LIMIT = 600;

    /**
     * @return array{rows: list<array{type: string, before: string|null, after: string|null, before_html: string|null, after_html: string|null, count: int|null}>, added: int, removed: int, changed: int, unchanged: int, identical: bool}
     */
    public function diff(?string $before, ?string $after): array
    {
        $left = $this->blocks($before);
        $right = $this->blocks($after);

        $rows = $this->pair($this->rows($left, $right));

        $added = $this->countType($rows, self::ADDED);
        $removed = $this->countType($rows, self::REMOVED);
        $changed = $this->countType($rows, self::CHANGED);

        return [
            'rows' => $this->collapse($rows),
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => count($rows) - $added - $removed - $changed,
            'identical' => $added === 0 && $removed === 0 && $changed === 0,
        ];
    }

    /**
     * HTML in vergleichbare Klartextbloecke zerlegen. Ueberschriften bleiben
     * als eigene Bloecke stehen, damit sich Aenderungen zuordnen lassen.
     *
     * @return list<string>
     */
    private function blocks(?string $html): array
    {
        $text = (string) $html;

        if (trim($text) === '') {
            return [];
        }

        $text = preg_replace('/<\/(p|div|li|h[1-6]|tr|blockquote)>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $blocks = [];

        foreach (preg_split('/\R+/u', $text) ?: [] as $line) {
            $line = trim(preg_replace('/[ \t\x{00a0}]+/u', ' ', $line) ?? $line);

            if ($line !== '') {
                $blocks[] = $line;
            }
        }

        return $blocks;
    }

    /**
     * Laengste gemeinsame Teilfolge ueber die Bloecke. Bei sehr langen
     * Artikeln bleibt die Matrix beherrschbar: 2.600 Woerter ergeben
     * groessenordnungsmaessig 60 Bloecke je Seite.
     *
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<array{type: string, before: string|null, after: string|null}>
     */
    private function rows(array $left, array $right): array
    {
        $rows = count($left);
        $cols = count($right);

        $table = array_fill(0, $rows + 1, array_fill(0, $cols + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $cols - 1; $j >= 0; $j--) {
                $table[$i][$j] = $left[$i] === $right[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;

        while ($i < $rows && $j < $cols) {
            if ($left[$i] === $right[$j]) {
                $result[] = ['type' => self::UNCHANGED, 'before' => $left[$i], 'after' => $right[$j]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $result[] = ['type' => self::REMOVED, 'before' => $left[$i], 'after' => null];
                $i++;
            } else {
                $result[] = ['type' => self::ADDED, 'before' => null, 'after' => $right[$j]];
                $j++;
            }
        }

        while ($i < $rows) {
            $result[] = ['type' => self::REMOVED, 'before' => $left[$i], 'after' => null];
            $i++;
        }

        while ($j < $cols) {
            $result[] = ['type' => self::ADDED, 'before' => null, 'after' => $right[$j]];
            $j++;
        }

        return $result;
    }

    /**
     * Stufe 2: benachbarte Paare aus REMOVED und ADDED zusammenziehen. Ohne
     * das erscheint ein Absatz, in dem eine Jahreszahl getauscht wurde, als
     * zwei Zeilen — einmal ganz rot, einmal ganz gruen.
     *
     * @param  list<array{type: string, before: string|null, after: string|null}>  $rows
     * @return list<array{type: string, before: string|null, after: string|null, before_html?: string, after_html?: string}>
     */
    private function pair(array $rows): array
    {
        $paired = [];
        $count = count($rows);

        for ($index = 0; $index < $count; $index++) {
            $row = $rows[$index];
            $next = $rows[$index + 1] ?? null;

            $before = null;
            $after = null;

            if ($next !== null && $row['type'] === self::REMOVED && $next['type'] === self::ADDED) {
                $before = (string) $row['before'];
                $after = (string) $next['after'];
            } elseif ($next !== null && $row['type'] === self::ADDED && $next['type'] === self::REMOVED) {
                $before = (string) $next['before'];
                $after = (string) $row['after'];
            }

            if ($before !== null && $after !== null) {
                $marked = $this->words($before, $after);

                if ($marked !== null) {
                    $paired[] = [
                        'type' => self::CHANGED,
                        'before' => $before,
                        'after' => $after,
                        'before_html' => $marked['before_html'],
                        'after_html' => $marked['after_html'],
                    ];

                    $index++;

                    continue;
                }
            }

            $paired[] = $row;
        }

        return $paired;
    }

    /**
     * Wortvergleich innerhalb eines gepaarten Blocks. Liefert null, wenn die
     * beiden Bloecke einander zu unaehnlich sind — dann bleiben sie zwei
     * getrennte Zeilen.
     *
     * @return array{before_html: string, after_html: string}|null
     */
    private function words(string $before, string $after): ?array
    {
        $left = $this->tokens($before);
        $right = $this->tokens($after);

        if ($left === [] || $right === []) {
            return null;
        }

        $matcher = new SequenceMatcher($left, $right);
        $opcodes = $matcher->getOpcodes();

        $equal = 0;

        foreach ($opcodes as [$op, $i1, $i2]) {
            if ($op === SequenceMatcher::OP_EQ) {
                $equal += $i2 - $i1;
            }
        }

        $ratio = (2 * $equal) / (count($left) + count($right));

        if ($ratio < self::CHANGED_THRESHOLD) {
            return null;
        }

        $beforeParts = [];
        $afterParts = [];

        foreach ($opcodes as [$op, $i1, $i2, $j1, $j2]) {
            $removed = $this->escape(array_slice($left, $i1, $i2 - $i1));
            $added = $this->escape(array_slice($right, $j1, $j2 - $j1));

            if ($op === SequenceMatcher::OP_EQ) {
                $beforeParts[] = $removed;
                $afterParts[] = $added;

                continue;
            }

            if ($removed !== '') {
                $beforeParts[] = "<del>{$removed}</del>";
            }

            if ($added !== '') {
                $afterParts[] = "<ins>{$added}</ins>";
            }
        }

        return [
            'before_html' => implode(' ', $beforeParts),
            'after_html' => implode(' ', $afterParts),
        ];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $block): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $block) ?: [], fn (string $word): bool => $word !== ''));
    }

    /**
     * Der Klartext geht entschaerft in das gelieferte HTML — Marken sind das
     * einzige Markup, das dort vorkommen darf.
     *
     * @param  list<string>  $words
     */
    private function escape(array $words): string
    {
        return e(implode(' ', $words));
    }

    /**
     * @param  list<array{type: string, ...}>  $rows
     */
    private function countType(array $rows, string $type): int
    {
        return count(array_filter($rows, fn (array $row): bool => $row['type'] === $type));
    }

    /**
     * Unveraenderte Strecken auf Kontextzeilen kuerzen. Ohne das besteht die
     * Ansicht eines aktualisierten Artikels zu neunzig Prozent aus Text, den
     * niemand mehr lesen muss. Die uebersprungene Strecke wird zu einer
     * eigenen Trennzeile mit Zaehler, nicht zu Text in beiden Spalten.
     *
     * @param  list<array{type: string, before: string|null, after: string|null, before_html?: string, after_html?: string}>  $rows
     * @return list<array{type: string, before: string|null, after: string|null, before_html: string|null, after_html: string|null, count: int|null}>
     */
    private function collapse(array $rows): array
    {
        $keep = [];

        foreach ($rows as $index => $row) {
            if ($row['type'] !== self::UNCHANGED) {
                for ($offset = -self::CONTEXT_BLOCKS; $offset <= self::CONTEXT_BLOCKS; $offset++) {
                    $keep[$index + $offset] = true;
                }
            }
        }

        $collapsed = [];
        $skipped = 0;

        foreach ($rows as $index => $row) {
            if (! isset($keep[$index])) {
                $skipped++;

                continue;
            }

            if ($skipped > 0) {
                $collapsed[] = $this->collapsedRow($skipped);
                $skipped = 0;
            }

            $limit = $row['type'] === self::UNCHANGED;

            $collapsed[] = [
                'type' => $row['type'],
                'before' => $this->text($row['before'], $limit),
                'after' => $this->text($row['after'], $limit),
                'before_html' => $row['before_html'] ?? null,
                'after_html' => $row['after_html'] ?? null,
                'count' => null,
            ];
        }

        if ($skipped > 0) {
            $collapsed[] = $this->collapsedRow($skipped);
        }

        return $collapsed;
    }

    private function text(?string $value, bool $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return $limit ? Str::limit($value, self::UNCHANGED_LIMIT) : $value;
    }

    /**
     * @return array{type: string, before: null, after: null, before_html: null, after_html: null, count: int}
     */
    private function collapsedRow(int $count): array
    {
        return [
            'type' => self::COLLAPSED,
            'before' => null,
            'after' => null,
            'before_html' => null,
            'after_html' => null,
            'count' => $count,
        ];
    }
}
