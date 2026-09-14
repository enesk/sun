<?php

declare(strict_types=1);

namespace App\Services\Seo;

use InvalidArgumentException;

/**
 * Erkennt und entfernt Meta-Kommentare aus KI-Prompts in Firmenbeschreibungen
 * (#2), etwa "Optimiert fuer Suchanfragen wie ..." oder "SEO-Keywords: ...".
 *
 * Gearbeitet wird Zeile fuer Zeile mit den Mustern aus config('seo.ai_footprints.patterns'):
 * 'line' entfernt die Zeile, 'sentence' den Satz, 'tail' schneidet ab dem Treffer bis zum Textende ab,
 * 'trailing' greift nur auf die jeweils letzte Zeile. Ohne Treffer bleibt der
 * Text byte-gleich — Whitespace wird nur in bereinigten Texten normalisiert.
 */
final class AiFootprintDetector
{
    private const SCOPES = ['tail', 'line', 'sentence', 'trailing'];

    /**
     * @param  array<string, array{scope: string, regex: string}>  $patterns
     */
    public function __construct(private readonly array $patterns)
    {
        foreach ($patterns as $name => $pattern) {
            if (! in_array($pattern['scope'] ?? null, self::SCOPES, true)) {
                throw new InvalidArgumentException("KI-Footprint-Muster '{$name}' hat keinen gueltigen scope.");
            }

            if (@preg_match($pattern['regex'] ?? '', '') === false) {
                throw new InvalidArgumentException("KI-Footprint-Muster '{$name}' ist kein gueltiger regulaerer Ausdruck.");
            }
        }
    }

    public static function fromConfig(): self
    {
        return new self((array) config('seo.ai_footprints.patterns', []));
    }

    public function clean(string $text): AiFootprintResult
    {
        $lines = preg_split('/\R/u', $text);

        if ($lines === false) {
            return new AiFootprintResult($text, $text, []);
        }

        $kept = [];
        $matched = [];

        foreach ($lines as $line) {
            $line = $this->removeSentences($line, $matched);

            if ($line === null) {
                continue;
            }

            [$name, $scope, $offset] = $this->match($line, ['line', 'tail']) ?? [null, null, null];

            if ($name === null) {
                $kept[] = $line;

                continue;
            }

            $matched[] = $name;

            if ($scope === 'line') {
                continue;
            }

            // Steht vor dem Treffer noch ein eigener Satz, bleibt er stehen.
            $before = rtrim(substr($line, 0, $offset));

            if (preg_match('/\p{L}/u', $before) === 1) {
                $kept[] = $before;
            }

            break;
        }

        $kept = $this->stripTrailing($kept, $matched);

        if ($matched === []) {
            return new AiFootprintResult($text, $text, []);
        }

        // Nach entfernten Meta-Zeilen am Anfang bleiben oft Trennlinien stehen.
        while ($kept !== [] && preg_match('/^[^\p{L}\p{N}]*$/u', $kept[0]) === 1) {
            array_shift($kept);
        }

        return new AiFootprintResult($text, $this->normalize($kept), array_values(array_unique($matched)));
    }

    /**
     * Entfernt Saetze mit einem Treffer der Wirkung 'sentence'. Liefert null,
     * wenn von der Zeile nichts oder nur eine Beschriftung wie "Kontakt:" bleibt.
     *
     * @param  list<string>  $matched
     */
    private function removeSentences(string $line, array &$matched): ?string
    {
        $changed = false;

        // Obergrenze gegen Endlosschleifen bei ungluecklich gewaehlten Mustern.
        for ($i = 0; $i < 10; $i++) {
            $hit = $this->match($line, ['sentence']);

            if ($hit === null) {
                break;
            }

            [$name, , $offset, $length] = $hit;
            $matched[] = $name;
            $changed = true;

            $start = 0;

            if (preg_match_all('/[.!?…][*_]*\s+/u', substr($line, 0, $offset), $ends, PREG_OFFSET_CAPTURE) > 0) {
                $last = end($ends[0]);
                $start = $last[1] + strlen($last[0]);
            }

            $end = strlen($line);

            if (preg_match('/[.!?…][*_]*(?=\s|$)/u', $line, $stop, PREG_OFFSET_CAPTURE, $offset + $length) === 1) {
                $end = $stop[0][1] + strlen($stop[0][0]);
            }

            $line = rtrim(substr($line, 0, $start).ltrim(substr($line, $end)));
        }

        if (! $changed) {
            return $line;
        }

        if (preg_match('/^[^\p{L}\p{N}]*(?:[\p{L}\p{N}][^:]{0,30}:)?[^\p{L}\p{N}]*$/u', $line) === 1) {
            return null;
        }

        return $line;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $matched
     * @return list<string>
     */
    private function stripTrailing(array $lines, array &$matched): array
    {
        while ($lines !== []) {
            $last = (string) end($lines);

            // Leerzeilen, Trennlinien und reine Emoji-/Markdown-Reste am Ende.
            if (preg_match('/^[^\p{L}\p{N}]*$/u', $last) === 1) {
                array_pop($lines);

                continue;
            }

            $hit = $this->match($last, ['trailing']);

            if ($hit === null) {
                break;
            }

            $matched[] = $hit[0];
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $scopes
     * @return array{0: string, 1: string, 2: int, 3: int}|null
     */
    private function match(string $line, array $scopes): ?array
    {
        foreach ($this->patterns as $name => $pattern) {
            if (! in_array($pattern['scope'], $scopes, true)) {
                continue;
            }

            if (preg_match($pattern['regex'], $line, $hit, PREG_OFFSET_CAPTURE) === 1) {
                return [(string) $name, $pattern['scope'], (int) $hit[0][1], strlen($hit[0][0])];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function normalize(array $lines): string
    {
        $text = implode("\n", array_map(
            fn (string $line): string => trim($line) === '' ? '' : $line,
            $lines,
        ));

        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
