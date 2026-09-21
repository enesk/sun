<?php

declare(strict_types=1);

namespace App\Guide\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Erzeugt das Inhaltsverzeichnis serverseitig aus dem Artikel-HTML (#17).
 *
 * Die IDs werden dabei in die Ueberschriften injiziert, damit die Anker
 * stabil bleiben — sie haengen am Slug der Ueberschrift, nicht an ihrer
 * Position. Verschiebt der Refresh-Loop (#24) einen Abschnitt, bleibt der
 * geteilte Link gueltig.
 */
class TocBuilder
{
    /**
     * @return array{html: string, headings: list<array{id: string, text: string, level: int}>}
     */
    public function build(?string $html): array
    {
        $html = trim((string) $html);

        if ($html === '') {
            return ['html' => '', 'headings' => []];
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="toc-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $headings = [];
        $used = [];

        foreach ($xpath->query('//h2|//h3') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text = trim((string) $node->textContent);

            if ($text === '') {
                continue;
            }

            $id = $this->uniqueId($node->getAttribute('id') ?: Str::slug($text), $used);
            $used[$id] = true;
            $node->setAttribute('id', $id);

            $headings[] = [
                'id' => $id,
                'text' => $text,
                'level' => (int) substr($node->nodeName, 1),
            ];
        }

        return [
            'html' => $this->innerHtml($document),
            'headings' => $headings,
        ];
    }

    /**
     * Teilt das Artikel-HTML vor der Ueberschrift, hinter der der Regionalblock
     * stehen soll. Der Block gehoert laut Design in den Mittelteil, nie ans
     * Ende — bei vier oder mehr Abschnitten vor den dritten, sonst vor den
     * letzten. Reicht es fuer keinen Schnitt, bleibt der zweite Teil leer und
     * das Template haengt den Block hinter den Text.
     *
     * @param  list<array{id: string, text: string, level: int}>  $headings
     * @return array{0: string, 1: string}
     */
    public function splitForRegionalBlock(string $html, array $headings): array
    {
        $sections = array_values(array_filter($headings, fn (array $h): bool => $h['level'] === 2));

        if (count($sections) < 2) {
            return [$html, ''];
        }

        $target = $sections[count($sections) >= 4 ? 2 : count($sections) - 1];
        $position = strpos($html, '<h2 id="'.$target['id'].'"');

        if ($position === false) {
            return [$html, ''];
        }

        return [substr($html, 0, $position), substr($html, $position)];
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueId(string $candidate, array $used): string
    {
        $base = $candidate !== '' ? $candidate : 'abschnitt';
        $id = $base;
        $suffix = 2;

        while (isset($used[$id])) {
            $id = $base.'-'.$suffix;
            $suffix++;
        }

        return $id;
    }

    private function innerHtml(DOMDocument $document): string
    {
        $root = (new DOMXPath($document))->query('//div[@id="toc-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
