<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Support\EvidenceMarks;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Baut aus den Stufenergebnissen den Artikel-Fliesstext (#14).
 *
 * Zwei Aufgaben:
 *
 *  1. Zusammenbau. Jede H2 beginnt mit ihrem Fazit-Satz als eigener Absatz —
 *     der Satz kommt aus `section.summary_sentence` und wird hier gerendert,
 *     nicht vom Modell in das Abschnitts-HTML geschrieben. So ist garantiert,
 *     dass er da ist und dass er vorne steht.
 *
 *  2. Saeuberung. Erlaubt sind ausschliesslich die Tags aus
 *     config('content.generation.allowed_tags'). Alles andere wird entpackt
 *     (Inhalt bleibt, Tag verschwindet), `script`/`style` samt Inhalt entfernt.
 *
 * Bewusst DOMDocument und keine HTMLPurifier-Konfiguration: die Whitelist ist
 * hier vier Zeilen lang und im Abnahmekriterium woertlich vorgegeben,
 * mews/purifier braeuchte dafuer eine eigene Konfigurationsdatei plus
 * beschreibbares Cache-Verzeichnis auf jedem Deploy-Ziel. Der Nutzen waere
 * derselbe, die Betriebslast hoeher.
 *
 * Links sind der sicherheitsrelevante Teil und werden zweifach gefiltert:
 * interne Ziele nur, wenn sie aus dem InternalLinkResolver stammen, externe
 * nur, wenn die URL in draft_sources steht. Alles andere wird entpackt — ein
 * Modell, das eine Quelle erfindet, erzeugt so keinen Link.
 */
class HtmlAssembler
{
    /** Tags, deren Inhalt mitentfernt wird. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'form', 'object', 'embed'];

    /**
     * Tags, die auf ein erlaubtes Gegenstueck umgeschrieben werden, statt
     * entpackt zu werden. Entpacken wuerde hier Struktur kosten: aus einem
     * <b> wuerde nackter Text, aus einem <h4> ein Absatz ohne Ueberschrift.
     * Die H1 gehoert dem Seitentitel, im Artikeltext wird sie zur H2.
     *
     * @var array<string, string>
     */
    private const RENAME = [
        'b' => 'strong',
        'i' => 'em',
        'h1' => 'h2',
        'h4' => 'h3',
        'h5' => 'h3',
        'h6' => 'h3',
    ];

    /**
     * @param  array<int, array{heading: string, summary_sentence: string, html: string, word_count: int, used_facts: array<int, int>, used_links: array<int, string>}>  $sections
     * @param  array{heading: string, intro: string, outro: string, html: string, facts: array<int, array{label: string, value: string}>, used_facts: array<int, int>}|null  $regional
     * @param  array<int, string>  $externalUrls  erlaubte externe Ziele (draft_sources)
     * @return array{html: string, word_count: int, internal_links: array<int, string>, external_links: array<int, string>}
     */
    public function assemble(
        GenerationContext $context,
        array $sections,
        ?array $regional,
        array $externalUrls = [],
    ): array {
        $internalUrls = array_map(
            static fn (array $target): string => $target['url'],
            $context->linkTargets,
        );

        $parts = [];

        foreach ($sections as $section) {
            $parts[] = $this->section($section, $internalUrls, $externalUrls);
        }

        if ($regional !== null && trim((string) $regional['html']) !== '') {
            $parts[] = $this->regionalSection($regional, $internalUrls, $externalUrls);
        }

        $html = implode("\n", array_filter($parts));
        $html = $this->ensurePortalLinks($html, $context);

        return [
            'html' => $html,
            'word_count' => $this->wordCount($html),
            'internal_links' => $this->links($html, internal: true),
            'external_links' => $this->links($html, internal: false),
        ];
    }

    /**
     * Key-Facts-Tabelle. Sie entsteht ohne Modellaufruf ausschliesslich aus
     * den Faktenschnipseln: eine Tabelle ist genau die Stelle, an der eine
     * erfundene Zahl am glaubwuerdigsten aussieht, und genau das darf nicht
     * passieren. Ein Schnipsel ohne Zahlenwert kommt nicht in die Tabelle.
     *
     * @param  array<int, int>  $usedFactIds  Faktennummern, die im Text vorkommen
     * @return array<int, array{label: string, value: string, source: string}>
     */
    public function keyFacts(GenerationContext $context, array $usedFactIds = []): array
    {
        $limit = max(3, (int) config('content.generation.key_facts_rows', 6));
        $rows = [];

        $snippets = $context->factSnippets
            ->sortByDesc(fn ($snippet): int => in_array((int) $snippet->getKey(), $usedFactIds, true) ? 1 : 0)
            ->values();

        $seen = [];

        foreach ($snippets as $snippet) {
            if (! $snippet->isTabular()) {
                continue;
            }

            $label = $snippet->displayLabel();
            $period = $snippet->displayPeriod();

            if ($period !== null) {
                $label .= " ({$period})";
            }

            // Zwei Schnipsel derselben Kennzahl fuer verschachtelte Regionen
            // (Stadt und Bundesland) wuerden sonst zwei Zeilen mit derselben
            // Beschriftung ergeben.
            if (isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;

            $rows[] = [
                'label' => $label,
                'value' => trim((string) $snippet->value.' '.(string) $snippet->unit),
                'source' => (string) ($snippet->source_name ?: 'Portaldaten'),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Saeubert HTML gegen die Whitelist. Oeffentlich, damit das Qualitaetsgate
     * (#15) und der Refresh-Loop (#24) dieselbe Fassung nutzen koennen.
     *
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    public function sanitize(string $html, array $internalUrls = [], array $externalUrls = []): string
    {
        // Belegmarken ("[F117]") steuern die Fakten-Zuordnung im Prompt und
        // gehoeren nicht in den Lesetext (#76).
        $html = trim(EvidenceMarks::strip($html));

        if ($html === '') {
            return '';
        }

        $allowed = array_map('strtolower', (array) config('content.generation.allowed_tags', []));

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="sanitize-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $this->root($document, 'sanitize-root');

        if ($root === null) {
            return '';
        }

        $this->dropForbidden($document);
        $this->cleanElement($root, $allowed, $internalUrls, $externalUrls);

        return trim($this->innerHtml($document, $root));
    }

    /**
     * Wortzahl des fertigen Fliesstexts — die Zahl, an der die Ziellaenge
     * gemessen wird.
     */
    public function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    /**
     * Alle Linkziele des Textes, getrennt nach intern (wurzelrelativ) und
     * extern.
     *
     * @return array<int, string>
     */
    public function links(string $html, bool $internal): array
    {
        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $matches);

        $urls = array_filter(
            $matches[1] ?? [],
            static fn (string $url): bool => str_starts_with($url, '/') === $internal,
        );

        return array_values(array_unique($urls));
    }

    /**
     * Eine H2 mit Fazit-Satz als erstem Absatz.
     *
     * @param  array{heading: string, summary_sentence: string, html: string, word_count: int, used_facts: array<int, int>, used_links: array<int, string>}  $section
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function section(array $section, array $internalUrls, array $externalUrls): string
    {
        $body = $this->sanitize($section['html'], $internalUrls, $externalUrls);

        if ($body === '') {
            return '';
        }

        $heading = e(trim(EvidenceMarks::strip($section['heading'])));
        $summary = trim(EvidenceMarks::strip($section['summary_sentence']));

        $lead = $summary === ''
            ? ''
            : '<p class="ratgeber-section__summary"><strong>'.e($summary).'</strong></p>';

        // Der Fazit-Satz darf nicht doppelt im Text stehen. Hat das Modell ihn
        // trotz Anweisung in html wiederholt, gewinnt der gerenderte Absatz.
        if ($summary !== '' && str_contains(strip_tags($body), $summary)) {
            $body = $this->removeParagraph($body, $summary, $internalUrls, $externalUrls);
        }

        return "<h2>{$heading}</h2>\n{$lead}\n{$body}";
    }

    /**
     * @param  array{heading: string, intro: string, outro: string, html: string, facts: array<int, array{label: string, value: string}>, used_facts: array<int, int>}  $regional
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function regionalSection(array $regional, array $internalUrls, array $externalUrls): string
    {
        $body = $this->sanitize($regional['html'], $internalUrls, $externalUrls);

        if ($body === '') {
            return '';
        }

        $parts = ['<h2>'.e(trim(EvidenceMarks::strip($regional['heading']))).'</h2>'];

        $intro = trim(EvidenceMarks::strip($regional['intro']));

        if ($intro !== '') {
            $parts[] = '<p><strong>'.e($intro).'</strong></p>';
        }

        $parts[] = $body;

        if ($regional['facts'] !== []) {
            $rows = collect($regional['facts'])->map(
                static fn (array $fact): string => '<tr><th scope="row">'.e($fact['label'])
                    .'</th><td>'.e($fact['value']).'</td></tr>'
            )->implode('');

            $parts[] = '<table><tbody>'.$rows.'</tbody></table>';
        }

        $outro = trim(EvidenceMarks::strip($regional['outro']));

        if ($outro !== '') {
            $parts[] = '<p>'.e($outro).'</p>';
        }

        return implode("\n", $parts);
    }

    /**
     * Sichert die Pflichtmenge interner Links zu. Das Modell setzt sie in der
     * Regel selbst; bleibt es darunter, haengt der Assembler die fehlenden
     * Ziele als eigenen Block an. Ein Ratgeber ohne Weg in das Verzeichnis
     * waere fuer den Mandanten wertlos.
     */
    private function ensurePortalLinks(string $html, GenerationContext $context): string
    {
        $required = max(0, (int) config('content.generation.internal_links.min_portal', 2));

        if ($required === 0) {
            return $html;
        }

        $portalTargets = array_values(array_filter(
            $context->linkTargets,
            static fn (array $target): bool => $target['type'] !== 'article',
        ));

        if ($portalTargets === []) {
            return $html;
        }

        $present = $this->links($html, internal: true);
        $portalUrls = array_map(static fn (array $target): string => $target['url'], $portalTargets);
        $set = count(array_intersect($present, $portalUrls));

        if ($set >= $required) {
            return $html;
        }

        $missing = array_values(array_filter(
            $portalTargets,
            static fn (array $target): bool => ! in_array($target['url'], $present, true),
        ));

        $missing = array_slice($missing, 0, max(1, $required - $set));

        if ($missing === []) {
            return $html;
        }

        $items = collect($missing)->map(
            static fn (array $target): string => '<li><a href="'.e($target['url']).'">'
                .e(Str::ucfirst($target['anchor'])).'</a></li>'
        )->implode('');

        // Bewusst ohne H2: jede H2 des Artikels beginnt mit ihrem Fazit-Satz,
        // und eine inhaltsleere Ueberschrift stuende sonst im
        // Inhaltsverzeichnis (#17).
        return $html."\n<p><strong>Passende Seiten im Portal:</strong></p>\n<ul>{$items}</ul>";
    }

    /**
     * Entfernt den Absatz, der den Fazit-Satz wiederholt.
     *
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function removeParagraph(
        string $html,
        string $sentence,
        array $internalUrls,
        array $externalUrls,
    ): string {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="dedupe-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $this->root($document, 'dedupe-root');

        if ($root === null) {
            return $html;
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('.//p', $root) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (trim($node->textContent) === $sentence) {
                $node->parentNode?->removeChild($node);

                break;
            }
        }

        return trim($this->innerHtml($document, $root));
    }

    private function dropForbidden(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (self::DROP_WITH_CONTENT as $tag) {
            foreach (iterator_to_array($xpath->query("//{$tag}") ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    /**
     * Raeumt einen Knoten und alle Kinder auf: unerlaubte Tags werden entpackt,
     * unerlaubte Attribute entfernt, Links gegen die Zielliste geprueft.
     *
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     */
    private function cleanElement(
        DOMNode $node,
        array $allowed,
        array $internalUrls,
        array $externalUrls,
    ): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $this->cleanElement($child, $allowed, $internalUrls, $externalUrls);

            $name = strtolower($child->nodeName);

            if (isset(self::RENAME[$name])) {
                $child = $this->rename($child, self::RENAME[$name]);
                $name = strtolower($child->nodeName);
            }

            if ($name === 'a' && ! $this->cleanLink($child, $internalUrls, $externalUrls)) {
                $this->unwrap($child);

                continue;
            }

            if (! in_array($name, $allowed, true)) {
                $this->unwrap($child);

                continue;
            }

            if ($name !== 'a') {
                $this->stripAttributes($child, $name === 'th' || $name === 'td' ? ['scope'] : []);
            }
        }
    }

    /**
     * Prueft und bereinigt einen Link. false = Link ist nicht zulaessig.
     *
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

        if (str_starts_with($href, '/')) {
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
        $element->setAttribute('rel', 'noopener nofollow');
        $element->setAttribute('target', '_blank');

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

    /**
     * Tag umschreiben, Inhalt und Reihenfolge behalten.
     */
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

    /**
     * Tag entfernen, Inhalt behalten.
     */
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

    /**
     * Der Wurzel-Container des geladenen Fragments. DOMDocument::getElementById
     * greift ohne DTD nicht zuverlaessig, deshalb per XPath.
     */
    private function root(DOMDocument $document, string $id): ?DOMElement
    {
        $found = (new DOMXPath($document))->query("//*[@id='{$id}']");
        $node = $found === false ? null : $found->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private function innerHtml(DOMDocument $document, DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
