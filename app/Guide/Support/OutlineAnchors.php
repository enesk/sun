<?php

declare(strict_types=1);

namespace App\Guide\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Anker des Ratgeber-Artikels aus der gesperrten Gliederung (#17).
 *
 * Die Sprungziele des Inhaltsverzeichnisses sind die festen Abschnitts-IDs
 * aus `guide_topics.outline_json` (s1, s1-1, ...), nie ein Slug der
 * Ueberschrift — so bleiben geteilte Links nach einer Umformulierung gueltig
 * (docs/guide-system.md, §4.1; design/guide-frontend.md, §3.0 Block 4).
 *
 * Traegt eine H2/H3 im Artikel-HTML bereits eine ID der Gliederung, bleibt sie
 * stehen. Ueberschriften ohne passende ID bekommen die naechste noch offene ID
 * der Gliederung, sofern die Ebene uebereinstimmt; so sind auch Fassungen
 * adressierbar, deren HTML die IDs nicht selbst mitbringt.
 */
final class OutlineAnchors
{
    /**
     * Gliederung in Lesereihenfolge.
     *
     * @param  array<int, mixed>|null  $outline
     * @return list<array{id: string, text: string, level: int}>
     */
    public static function flatten(?array $outline): array
    {
        $entries = [];

        $walk = function (array $sections, int $defaultLevel) use (&$walk, &$entries): void {
            foreach ($sections as $section) {
                if (! is_array($section) || ! isset($section['id'])) {
                    continue;
                }

                $entries[] = [
                    'id' => (string) $section['id'],
                    'text' => trim((string) ($section['heading'] ?? '')),
                    'level' => (int) ($section['level'] ?? $defaultLevel) === 3 ? 3 : 2,
                ];

                $walk((array) ($section['children'] ?? []), 3);
            }
        };

        $walk($outline ?? [], 2);

        return array_values(array_filter($entries, fn (array $entry): bool => $entry['id'] !== '' && $entry['text'] !== ''));
    }

    /**
     * Setzt die IDs der Gliederung in die Ueberschriften des Artikel-HTML.
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries  Ergebnis von flatten()
     */
    public static function apply(string $html, array $entries): string
    {
        $html = trim($html);

        if ($html === '' || $entries === []) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="guide-outline-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $positions = [];
        foreach ($entries as $index => $entry) {
            $positions[$entry['id']] = $index;
        }

        $next = 0;

        foreach ((new DOMXPath($document))->query('//h2|//h3') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $id = $node->getAttribute('id');

            if (isset($positions[$id])) {
                $next = $positions[$id] + 1;

                continue;
            }

            $level = (int) substr($node->nodeName, 1);

            if (isset($entries[$next]) && $entries[$next]['level'] === $level) {
                $node->setAttribute('id', $entries[$next]['id']);
                $next++;
            }
        }

        $root = $document->getElementById('guide-outline-root');

        if ($root === null) {
            return $html;
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }
}
