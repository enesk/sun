<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Baut body_html aus Abschnitten der gesperrten Gliederung (#10).
 *
 * Ein Abschnitt ist ein Gliederungspunkt (H2 oder H3) samt dem Text bis zur
 * naechsten Ueberschrift der Gliederung. Die Ueberschrift setzt immer dieser
 * Assembler aus outline_json — Text und id —, nie das Modell:
 *
 *   <h2 id="s1">Ueberschrift</h2>\n<p><strong>Fazit.</strong></p>...\n
 *
 * replace() zerlegt ein bestehendes body_html an den ids und ersetzt nur die
 * uebergebenen Abschnitte; alle anderen Segmente werden als Bytefolge
 * uebernommen. Das ist die Grundlage dafuer, dass ein Update nicht
 * betroffene Abschnitte byte-identisch laesst. Deshalb arbeiten split() und
 * replace() mit Stringpositionen und nicht mit DOMDocument (das wuerde beim
 * Speichern neu serialisieren).
 *
 * sanitize() prueft das Abschnitts-HTML des Modells gegen die Whitelist
 * guide.writing.allowed_tags: unbekannte Tags werden entpackt,
 * script/style samt Inhalt entfernt, Ueberschriften im Abschnittstext zu
 * Absaetzen (sonst stuende eine Ueberschrift ausserhalb der Gliederung im
 * Artikel). Interne Links nur auf Ziele des InternalLinkResolver, externe
 * nur auf URLs aus guide_sources, mit rel="nofollow noopener".
 *
 * sanitizeLegacy() gilt fuer Altartikel und Kategorie-Intros: gleiche
 * Whitelist, aber H2/H3 (mit id) bleiben stehen, interne Links auf jeden
 * wurzelrelativen Pfad, externe nur mit http(s).
 */
class HtmlAssembler
{
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'form', 'object', 'embed', 'svg', 'img'];

    /** @var array<string, string> */
    private const RENAME = [
        'b' => 'strong',
        'i' => 'em',
        'h1' => 'p',
        'h2' => 'p',
        'h3' => 'p',
        'h4' => 'p',
        'h5' => 'p',
        'h6' => 'p',
    ];

    private const HEADING_PATTERN = '/<h([23])\b[^>]*?\bid="([^"]*)"[^>]*>/i';

    /**
     * Ein Segment: Ueberschrift aus der Gliederung plus Abschnitts-HTML.
     *
     * @param  array{id: string, text: string, level: int}  $entry
     */
    public function segment(array $entry, string $body): string
    {
        $level = $entry['level'] === 3 ? 3 : 2;
        $body = trim($body);

        return "<h{$level} id=\"".e($entry['id']).'">'.e($entry['text'])."</h{$level}>\n".($body !== '' ? "{$body}\n" : '');
    }

    /**
     * Ganzer Artikel aus Abschnitts-HTML je id, in der Reihenfolge der Gliederung.
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @param  array<string, string>  $bodies  id => Abschnitts-HTML ohne Ueberschrift
     */
    public function assemble(array $entries, array $bodies): string
    {
        $html = '';

        foreach ($entries as $entry) {
            $html .= $this->segment($entry, $bodies[$entry['id']] ?? '');
        }

        return $html;
    }

    /**
     * Zerlegt body_html an den Ueberschriften mit id. Text vor der ersten
     * Ueberschrift steht unter dem Schluessel ''.
     *
     * @return array<string, string> id => Segment (Bytefolge inklusive Ueberschrift)
     */
    public function split(string $html): array
    {
        preg_match_all(self::HEADING_PATTERN, $html, $matches, PREG_OFFSET_CAPTURE);

        $segments = [];
        $starts = array_map(fn (array $match): int => (int) $match[1], $matches[0] ?? []);

        if ($starts === []) {
            return trim($html) === '' ? [] : ['' => $html];
        }

        if ($starts[0] > 0) {
            $segments[''] = substr($html, 0, $starts[0]);
        }

        foreach ($starts as $index => $start) {
            $end = $starts[$index + 1] ?? strlen($html);
            $id = html_entity_decode((string) $matches[2][$index][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Doppelte id: das erste Vorkommen zaehlt, der Rest haengt daran.
            if (isset($segments[$id])) {
                $segments[$id] .= substr($html, $start, $end - $start);

                continue;
            }

            $segments[$id] = substr($html, $start, $end - $start);
        }

        return $segments;
    }

    /**
     * Abschnitts-HTML eines Segments ohne die Ueberschrift.
     */
    public function body(string $segment): string
    {
        return trim((string) preg_replace('/^\s*<h[23]\b[^>]*>.*?<\/h[23]>/is', '', $segment, 1));
    }

    /**
     * Ersetzt die uebergebenen Abschnitte; alle anderen bleiben Byte fuer
     * Byte, wie sie sind. Die Reihenfolge ist die der Gliederung.
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @param  array<string, string>  $bodies  id => neues Abschnitts-HTML ohne Ueberschrift
     *
     * @throws \LogicException wenn ein Abschnitt weder im Bestand noch in $bodies steht
     */
    public function replace(string $html, array $entries, array $bodies): string
    {
        $segments = $this->split($html);
        $result = $segments[''] ?? '';

        foreach ($entries as $entry) {
            $id = $entry['id'];

            if (array_key_exists($id, $bodies)) {
                $result .= $this->segment($entry, $bodies[$id]);

                continue;
            }

            if (! isset($segments[$id])) {
                throw new \LogicException("Abschnitt '{$id}' fehlt im bestehenden Artikel und wurde nicht neu geschrieben.");
            }

            $result .= $segments[$id];
        }

        return $result;
    }

    /**
     * Abschnitte der Gliederung, die im body_html fehlen oder deren
     * Ueberschrift (Ebene, Text) nicht mehr der Gliederung entspricht.
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @return array<int, string>
     */
    public function mismatchedSections(string $html, array $entries): array
    {
        $segments = $this->split($html);
        $ids = [];

        foreach ($entries as $entry) {
            $segment = $segments[$entry['id']] ?? null;

            if ($segment === null || ! str_starts_with($segment, $this->segment($entry, ''))) {
                $ids[] = $entry['id'];
            }
        }

        return $ids;
    }

    /**
     * Prueft den fertigen Artikel gegen die Akzeptanzregeln: Ueberschriften
     * exakt wie die Gliederung, nur erlaubte Tags und Attribute, jede H2
     * beginnt mit einem Absatz (dem Fazit-Satz).
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @return array<int, string> Verstoesse, leer = in Ordnung
     */
    public function violations(string $html, array $entries): array
    {
        $errors = [];

        preg_match_all('/<h([1-6])\b([^>]*)>(.*?)<\/h\1>/is', $html, $headings, PREG_SET_ORDER);
        $found = array_map(function (array $heading): array {
            preg_match('/\bid="([^"]*)"/i', $heading[2], $id);

            return [
                'id' => html_entity_decode($id[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'level' => (int) $heading[1],
                'text' => html_entity_decode(strip_tags($heading[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }, $headings);

        $expected = array_map(fn (array $entry): array => ['id' => $entry['id'], 'level' => $entry['level'], 'text' => $entry['text']], $entries);

        if ($found !== $expected) {
            $errors[] = 'Ueberschriften im HTML weichen von der gesperrten Gliederung ab.';
        }

        $allowed = $this->allowedTags();

        preg_match_all('/<\/?([a-z][a-z0-9]*)\b([^>]*)>/i', $html, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            $name = strtolower($tag[1]);

            if (! in_array($name, $allowed, true)) {
                $errors[] = "Unerlaubtes Tag <{$name}>.";

                continue;
            }

            // Werte mitlesen, sonst zaehlte "?page=2" in einem href als Attribut.
            preg_match_all('/([a-z-]+)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', $tag[2], $attributes);
            $permitted = match ($name) {
                'h2', 'h3' => ['id'],
                'a' => ['href', 'rel'],
                'th', 'td' => ['scope'],
                default => [],
            };

            foreach ($attributes[1] ?? [] as $attribute) {
                if (! in_array(strtolower($attribute), $permitted, true)) {
                    $errors[] = "Unerlaubtes Attribut {$attribute} an <{$name}>.";
                }
            }
        }

        foreach ($this->split($html) as $id => $segment) {
            if (str_starts_with($segment, '<h2') && preg_match('/^<p>/', $this->body($segment)) !== 1) {
                $errors[] = "Abschnitt '{$id}' beginnt nicht mit einem Fazit-Satz.";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Fazit-Satz als eigener Absatz am Anfang einer H2.
     */
    public function lead(string $sentence): string
    {
        $sentence = trim((string) preg_replace('/\s+/u', ' ', strip_tags($sentence)));

        return $sentence === '' ? '' : '<p><strong>'.e($sentence).'</strong></p>';
    }

    /**
     * Saeubert Abschnitts-HTML des Modells gegen die Whitelist.
     *
     * @param  array<int, string>  $internalUrls  wurzelrelative Ziele des InternalLinkResolver
     * @param  array<int, string>  $externalUrls  URLs aus guide_sources
     */
    public function sanitize(string $html, array $internalUrls = [], array $externalUrls = []): string
    {
        return $this->purify($html, $internalUrls, $externalUrls, false);
    }

    /**
     * Saeubert fertiges Artikel-HTML ausserhalb des Writers (Altartikel,
     * Kategorie-Intro, Bildnachweis) gegen dieselbe Whitelist.
     */
    public function sanitizeLegacy(string $html): string
    {
        $external = array_values(array_filter(
            $this->links($html, false),
            fn (string $url): bool => preg_match('#^https?://#i', $url) === 1,
        ));

        return $this->purify($html, $this->links($html, true), $external, true);
    }

    /**
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function purify(string $html, array $internalUrls, array $externalUrls, bool $keepHeadings): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="guide-sanitize-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $this->root($document);

        if ($root === null) {
            return '';
        }

        $xpath = new DOMXPath($document);

        foreach (self::DROP_WITH_CONTENT as $tag) {
            foreach (iterator_to_array($xpath->query("//{$tag}") ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $this->clean($root, $this->allowedTags(), $internalUrls, $externalUrls, $keepHeadings);

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        // Leere Absaetze aus entpackten Elementen.
        return trim((string) preg_replace('#<p>\s*</p>#', '', $result));
    }

    /**
     * Haengt Linkziele an, die das Modell nicht gesetzt hat. So ist die
     * Pflichtmenge interner Links garantiert, ohne den Abschnitt neu
     * schreiben zu lassen.
     *
     * @param  array<int, array{url: string, anchor: string}>  $targets
     */
    public function ensureLinks(string $body, array $targets): string
    {
        $present = $this->links($body, internal: true);
        $missing = array_values(array_filter($targets, fn (array $target): bool => ! in_array($target['url'], $present, true)));

        if ($missing === []) {
            return $body;
        }

        $links = array_map(
            fn (array $target): string => '<a href="'.e($target['url']).'">'.e(Str::ucfirst($target['anchor'])).'</a>',
            $missing,
        );

        return rtrim($body)."\n<p>Mehr dazu: ".implode(', ', $links).'.</p>';
    }

    /**
     * Linkziele im HTML, getrennt nach intern (wurzelrelativ) und extern.
     *
     * @return array<int, string>
     */
    public function links(string $html, bool $internal): array
    {
        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $matches);

        $urls = array_map(
            fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches[1] ?? [],
        );

        return array_values(array_unique(array_filter(
            $urls,
            fn (string $url): bool => (str_starts_with($url, '/') && ! str_starts_with($url, '//')) === $internal,
        )));
    }

    /**
     * @return array<int, string>
     */
    private function allowedTags(): array
    {
        return array_map('strtolower', (array) config('guide.writing.allowed_tags', []));
    }

    /**
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function clean(DOMNode $node, array $allowed, array $internalUrls, array $externalUrls, bool $keepHeadings = false): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $this->clean($child, $allowed, $internalUrls, $externalUrls, $keepHeadings);

            $name = strtolower($child->nodeName);

            if ($keepHeadings && ($name === 'h2' || $name === 'h3')) {
                $this->stripAttributes($child, ['id']);

                continue;
            }

            if (isset(self::RENAME[$name])) {
                $child = $this->rename($child, self::RENAME[$name]);
                $name = self::RENAME[$name];
            }

            if ($name === 'a') {
                if (! $this->cleanLink($child, $internalUrls, $externalUrls)) {
                    $this->unwrap($child);
                }

                continue;
            }

            if (! in_array($name, $allowed, true) || $name === 'h2' || $name === 'h3') {
                $this->unwrap($child);

                continue;
            }

            $this->stripAttributes($child, match (true) {
                $name === 'th' || $name === 'td' => ['scope'],
                // Altartikel tragen Klassen wie ratgeber-section__summary.
                $keepHeadings && $name === 'p' => ['class'],
                default => [],
            });
        }
    }

    /**
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function cleanLink(DOMElement $element, array $internalUrls, array $externalUrls): bool
    {
        $href = trim($element->getAttribute('href'));
        $this->stripAttributes($element, []);

        if ($href === '' || trim($element->textContent) === '') {
            return false;
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            if (! in_array($href, $internalUrls, true)) {
                return false;
            }

            $element->setAttribute('href', $href);

            return true;
        }

        if (! in_array($href, $externalUrls, true)) {
            return false;
        }

        $element->setAttribute('href', $href);
        $element->setAttribute('rel', 'nofollow noopener');

        return true;
    }

    /**
     * @param  array<int, string>  $keep
     */
    private function stripAttributes(DOMElement $element, array $keep): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (! in_array(strtolower($attribute->nodeName), $keep, true)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }

    private function rename(DOMElement $element, string $tag): DOMElement
    {
        $document = $element->ownerDocument;
        $parent = $element->parentNode;

        if ($document === null || $parent === null) {
            return $element;
        }

        $replacement = $document->createElement($tag);

        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }

        $parent->replaceChild($replacement, $element);

        return $replacement;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function root(DOMDocument $document): ?DOMElement
    {
        $found = (new DOMXPath($document))->query("//*[@id='guide-sanitize-root']");
        $node = $found === false ? null : $found->item(0);

        return $node instanceof DOMElement ? $node : null;
    }
}
