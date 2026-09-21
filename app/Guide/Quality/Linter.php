<?php

declare(strict_types=1);

namespace App\Guide\Quality;

use App\Guide\Models\ArticleVersion;
use App\Guide\Services\GuidePageData;
use App\Guide\Writing\HtmlAssembler;

/**
 * Stufe 1 des Qualitaetsgates (#11): deterministischer Lint einer Fassung.
 *
 * Die Regeln stehen als Liste in config/guide_lint.php (`rules`); jede
 * liefert genau eine Zeile im Bericht, bestanden oder nicht. Geprueft wird
 * immer der ganze Artikel, auch im Update-Modus: ein Update darf einen
 * bisher sauberen Artikel nicht beschaedigen.
 *
 * Die Laengen gelten fuer den gerenderten Text ({{year}} ersetzt), weil der
 * Leser genau den sieht. Gliederung, Fazit-Saetze und Tag-Whitelist sind
 * dieselben Regeln, die der HtmlAssembler (#10) schon vor dem Speichern
 * prueft; der Lint haelt sie im Bericht fest und faengt Fassungen ab, die
 * an ihm vorbei entstanden sind.
 */
class Linter
{
    public function __construct(
        private readonly HtmlAssembler $html,
    ) {}

    /**
     * @param  list<array{id: string, text: string, level: int}>  $entries  gesperrte Gliederung
     * @return list<array{rule: string, passed: bool, blocking: bool, fixable: bool, message: string, section_id: ?string}>
     */
    public function lint(ArticleVersion $version, array $entries, bool $isYmyl): array
    {
        $body = (string) $version->body_html;
        $results = [];

        foreach ((array) config('guide_lint.rules', []) as $rule => $options) {
            $options = (array) $options;

            if (! (bool) ($options['enabled'] ?? true) || ($rule === 'ymyl_disclaimer' && ! $isYmyl)) {
                continue;
            }

            $findings = match ($rule) {
                'title_length' => $this->length('Titel', GuidePageData::replaceYear((string) ($version->meta_title ?: $version->title)), $options, 'meta'),
                'meta_description_length' => $this->length('Meta-Description', (string) $version->meta_description, $options, 'meta'),
                'single_h1' => $this->singleH1($version, $body),
                'outline_match' => $this->outlineMatch($body, $entries),
                'section_lead' => $this->sectionLeads($body),
                'faq_count' => $this->faqCount((array) ($version->faq_json ?? []), $options),
                'short_answer' => $this->length('Kurzantwort', (string) $version->short_answer, $options, 'short_answer'),
                'internal_links' => $this->internalLinks($body, $options),
                'allowed_tags' => $this->allowedTags($body, $entries),
                'ymyl_disclaimer' => $this->disclaimer($body.' '.(string) $version->short_answer),
                default => [],
            };

            $blocking = (bool) ($options['blocking'] ?? true);
            $fixable = (bool) ($options['fixable'] ?? false);

            if ($findings === []) {
                $results[] = $this->row($rule, true, $blocking, $fixable, 'ok', null);

                continue;
            }

            foreach ($findings as $finding) {
                $results[] = $this->row($rule, false, $blocking, $fixable, $finding['message'], $finding['section_id']);
            }
        }

        return $results;
    }

    /**
     * Anteil bestandener Regeln, 0 bis 100.
     *
     * @param  list<array{rule: string, passed: bool}>  $results
     */
    public function score(array $results): float
    {
        $rules = [];

        foreach ($results as $result) {
            $rules[$result['rule']] = ($rules[$result['rule']] ?? true) && $result['passed'];
        }

        if ($rules === []) {
            return 100.0;
        }

        return round(count(array_filter($rules)) / count($rules) * 100, 1);
    }

    /**
     * Klartext eines Artikels, wie ihn FactChecker, ReadabilityScorer und die
     * Disclaimer-Pruefung lesen: Blockenden werden Satzenden, {{year}} ist
     * ersetzt.
     */
    public static function plainText(string $html): string
    {
        $html = preg_replace('/<\/(p|li|h[1-6]|td|th|blockquote)>/i', '. ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = GuidePageData::replaceYear($text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{message: string, section_id: ?string}>
     */
    private function length(string $label, string $text, array $options, string $sectionId): array
    {
        $length = mb_strlen(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))));
        $min = (int) ($options['min'] ?? 0);
        $max = (int) ($options['max'] ?? PHP_INT_MAX);

        if ($length === 0) {
            return [['message' => "{$label} fehlt.", 'section_id' => $sectionId]];
        }

        if ($length < $min || $length > $max) {
            return [['message' => "{$label} hat {$length} Zeichen, erlaubt sind {$min} bis {$max}.", 'section_id' => $sectionId]];
        }

        return [];
    }

    /**
     * Die Seite rendert den Titel als H1; genau eine H1 heisst: Titel da und
     * keine weitere H1 im Fliesstext.
     *
     * @return list<array{message: string, section_id: ?string}>
     */
    private function singleH1(ArticleVersion $version, string $body): array
    {
        $findings = [];

        if (trim((string) $version->title) === '') {
            $findings[] = ['message' => 'Titel (H1) fehlt.', 'section_id' => 'meta'];
        }

        $count = preg_match_all('/<h1\b/i', $body);

        if ($count > 0) {
            $findings[] = ['message' => "body_html enthaelt {$count} H1; die H1 ist allein der Titel.", 'section_id' => null];
        }

        return $findings;
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @return list<array{message: string, section_id: ?string}>
     */
    private function outlineMatch(string $body, array $entries): array
    {
        if ($entries === []) {
            return [['message' => 'Thema hat keine gesperrte Gliederung.', 'section_id' => null]];
        }

        $findings = array_map(
            fn (string $id): array => ['message' => "Abschnitt '{$id}' fehlt oder weicht von der gesperrten Gliederung ab.", 'section_id' => $id],
            $this->html->mismatchedSections($body, $entries),
        );

        // Zusaetzliche Ueberschriften ausserhalb der Gliederung.
        $expected = array_flip(array_map(fn (array $entry): string => $entry['id'], $entries));
        preg_match_all('/<h[2-6]\b([^>]*)>/i', $body, $headings);

        foreach ($headings[1] ?? [] as $attributes) {
            preg_match('/\bid="([^"]*)"/i', $attributes, $id);
            $id = html_entity_decode($id[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (! isset($expected[$id])) {
                $findings[] = ['message' => 'Ueberschrift '.($id !== '' ? "'{$id}' " : '').'steht nicht in der gesperrten Gliederung.', 'section_id' => null];
            }
        }

        return $findings;
    }

    /**
     * @return list<array{message: string, section_id: ?string}>
     */
    private function sectionLeads(string $body): array
    {
        $findings = [];

        foreach ($this->html->split($body) as $id => $segment) {
            if (str_starts_with($segment, '<h2') && preg_match('/^<p>/', $this->html->body($segment)) !== 1) {
                $findings[] = ['message' => "Abschnitt '{$id}' beginnt nicht mit einem Fazit-Satz.", 'section_id' => (string) $id];
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, mixed>  $faq
     * @param  array<string, mixed>  $options
     * @return list<array{message: string, section_id: ?string}>
     */
    private function faqCount(array $faq, array $options): array
    {
        $count = count(array_filter($faq, fn (mixed $item): bool => is_array($item)
            && trim((string) ($item['question'] ?? '')) !== ''
            && trim((string) ($item['answer'] ?? '')) !== ''));
        $min = (int) ($options['min'] ?? 0);
        $max = (int) ($options['max'] ?? PHP_INT_MAX);

        if ($count < $min || $count > $max) {
            return [['message' => "FAQ hat {$count} vollstaendige Eintraege, erlaubt sind {$min} bis {$max}.", 'section_id' => 'faq']];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array{message: string, section_id: ?string}>
     */
    private function internalLinks(string $body, array $options): array
    {
        $count = count($this->html->links($body, internal: true));
        $min = (int) ($options['min'] ?? config('guide.writing.links.min_portal', 2));

        if ($count < $min) {
            return [['message' => "Nur {$count} interne Links im Artikel, mindestens {$min} verlangt.", 'section_id' => null]];
        }

        return [];
    }

    /**
     * Tag- und Attribut-Whitelist aus HtmlAssembler::violations(); die
     * Ueberschriften- und Fazit-Befunde daraus pruefen eigene Regeln.
     *
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @return list<array{message: string, section_id: ?string}>
     */
    private function allowedTags(string $body, array $entries): array
    {
        $messages = array_filter(
            $this->html->violations($body, $entries),
            fn (string $message): bool => str_starts_with($message, 'Unerlaubte'),
        );

        return array_values(array_map(fn (string $message): array => ['message' => $message, 'section_id' => null], $messages));
    }

    /**
     * @return list<array{message: string, section_id: ?string}>
     */
    private function disclaimer(string $html): array
    {
        $text = self::plainText($html);

        foreach ((array) config('guide_lint.ymyl_disclaimer.patterns', []) as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $text) === 1) {
                return [];
            }
        }

        return [['message' => 'YMYL-Portal: Pflicht-Disclaimer laut Styleguide fehlt (z. B. "ersetzt keine Beratung/Diagnose").', 'section_id' => $this->firstSectionId($html)]];
    }

    /**
     * Der Disclaimer gehoert nach docs/guide-prompts.md §7 in den Artikel,
     * nicht an eine bestimmte Stelle; der Fix-Durchlauf setzt ihn in den
     * ersten Abschnitt, gleich hinter die Antwort.
     */
    private function firstSectionId(string $html): ?string
    {
        foreach (array_keys($this->html->split($html)) as $id) {
            if ($id !== '') {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * @return array{rule: string, passed: bool, blocking: bool, fixable: bool, message: string, section_id: ?string}
     */
    private function row(string $rule, bool $passed, bool $blocking, bool $fixable, string $message, ?string $sectionId): array
    {
        return [
            'rule' => $rule,
            'passed' => $passed,
            'blocking' => $blocking,
            'fixable' => $fixable,
            'message' => $message,
            'section_id' => $sectionId,
        ];
    }
}
