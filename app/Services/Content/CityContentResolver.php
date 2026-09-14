<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use App\Models\Portal\CityContentTemplate;
use App\Services\Seo\CityMetaTemplates;
use App\Themes\ThemeManager;

/**
 * Introtext, Stadtteile und FAQ des Local Hubs auf /staedte/{slug} (#11).
 *
 * Reihenfolge je Teil (Intro und FAQ getrennt): freigegebener Stadt-Override
 * (city_contents, is_published) > Tenant-Vorlage (city_content_templates) mit
 * Platzhaltern > kein Block. Overrides werden woertlich uebernommen, nur die
 * Vorlage kennt Platzhalter. Ohne Stadtteile entfallen die Saetze der Vorlage,
 * die {districts} enthalten.
 *
 * FAQ-Antworten sind Klartext mit Absaetzen (Leerzeile), kein HTML — sie gehen
 * 1:1 ins FAQPage-Schema.
 */
final class CityContentResolver
{
    /**
     * Platzhalter samt Erklaerung fuer die Oberflaeche.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{city}' => 'Name der Stadt.',
        '{trade}' => 'Branchenbezeichnung im Plural, z. B. "Elektriker".',
        '{trade_singular}' => 'Branchenbezeichnung im Singular, z. B. "Elektriker".',
        '{count}' => 'Anzahl aktiver Betriebe in der Stadt.',
        '{districts}' => 'Stadtteile der Stadt, z. B. "Mitte, Bad Cannstatt und Vaihingen" (höchstens '.self::DISTRICTS_IN_TEXT.'). Fehlen sie, entfällt der ganze Satz.',
    ];

    /**
     * Hoechstens so viele Stadtteile werden in {districts} genannt, die Liste
     * selbst (districts) bleibt vollstaendig.
     */
    public const DISTRICTS_IN_TEXT = 6;

    /**
     * Erlaubtes HTML im Introtext eines Stadt-Overrides.
     */
    public const INTRO_ALLOWED_HTML = 'p,br,strong,em,ul,ol,li,a[href]';

    /**
     * Werkzeugleiste des RichEditors passend zu INTRO_ALLOWED_HTML.
     *
     * @var list<list<string>>
     */
    public const INTRO_TOOLBAR = [
        ['bold', 'italic', 'link'],
        ['bulletList', 'orderedList'],
        ['undo', 'redo'],
    ];

    public function __construct(
        private readonly CityMetaTemplates $meta,
        private readonly ThemeManager $themes,
    ) {}

    /**
     * @return array{intro_html: ?string, intro_source: ?string, districts: list<string>, faqs: list<array{question: string, answer: string}>, faq_source: ?string}|null
     */
    public function forCity(City $city): ?array
    {
        return $this->resolve($city, $city->cityContent, CityContentTemplate::current());
    }

    /**
     * Wie forCity(), aber mit uebergebenem (auch ungespeichertem) Override und
     * Vorlage — fuer die Vorschau in Filament.
     *
     * @return array{intro_html: ?string, intro_source: ?string, districts: list<string>, faqs: list<array{question: string, answer: string}>, faq_source: ?string}|null
     */
    public function resolve(City $city, ?CityContent $override, ?CityContentTemplate $template): ?array
    {
        if ($override !== null && ! $override->is_published) {
            $override = null;
        }

        $districts = self::normalizeDistricts($override?->districts);
        $values = $this->placeholderValues($city, $districts);

        $intro = self::sanitizeIntro($override?->intro_text);
        $introSource = $intro !== null ? 'override' : null;

        if ($intro === null && filled($template?->intro_template)) {
            $intro = self::paragraphsToHtml($this->fill((string) $template->intro_template, $values, $districts !== []));
            $introSource = $intro !== null ? 'template' : null;
        }

        $faqs = self::normalizeFaqs($override?->faqs);
        $faqSource = $faqs !== [] ? 'override' : null;

        if ($faqs === []) {
            $faqs = $this->fillFaqs($template?->faq_templates, $values, $districts !== []);
            $faqSource = $faqs !== [] ? 'template' : null;
        }

        if ($intro === null && $faqs === [] && $districts === []) {
            return null;
        }

        return [
            'intro_html' => $intro,
            'intro_source' => $introSource,
            'districts' => $districts,
            'faqs' => $faqs,
            'faq_source' => $faqSource,
        ];
    }

    /**
     * Sichtbarer Anfang des Introtexts fuer die Stadtseite (#12): ganze Bloecke
     * (p, ul, ol), bis $sentences Saetze erreicht sind; ein reiner Textabsatz
     * wird am Satzende geteilt. Der Rest geht in "Mehr anzeigen". Absaetze mit
     * Auszeichnung (Link, fett) bleiben ganz, damit kein Tag zerschnitten wird.
     *
     * @return array{lead: string, more: ?string}
     */
    public static function splitIntro(string $html, int $sentences = 3): array
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return ['lead' => $html, 'more' => null];
        }

        $lead = [];
        $more = [];
        $count = 0;

        foreach (iterator_to_array($root->childNodes) as $node) {
            $text = trim((string) $node->textContent);

            if ($text === '') {
                continue;
            }

            $markup = (string) $document->saveHTML($node);

            if ($count >= $sentences) {
                $more[] = $markup;

                continue;
            }

            $parts = self::sentences($text);
            $plainParagraph = $node->nodeName === 'p' && $node->childNodes->length === 1 && $node->firstChild instanceof \DOMText;

            if ($count + count($parts) > $sentences && $plainParagraph) {
                $take = $sentences - $count;
                $lead[] = '<p>'.e(implode(' ', array_slice($parts, 0, $take))).'</p>';
                $more[] = '<p>'.e(implode(' ', array_slice($parts, $take))).'</p>';
                $count = $sentences;

                continue;
            }

            if ($count > 0 && $count + count($parts) > $sentences) {
                $more[] = $markup;
                $count = $sentences;

                continue;
            }

            $lead[] = $markup;
            $count += count($parts);
        }

        return [
            'lead' => implode("\n", $lead),
            'more' => $more === [] ? null : implode("\n", $more),
        ];
    }

    /**
     * Saetze eines Klartexts. Satzende nur nach einem Wort mit mindestens drei
     * Zeichen und vor einem Grossbuchstaben, damit "z. B." oder "ca. 20" nicht
     * trennen.
     *
     * @return list<string>
     */
    private static function sentences(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $parts = preg_split('/(?<=[^\s.]{3}[.!?])\s+(?=[\p{Lu}„"])/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * Speicherpfad des Override-Introtexts: HTML auf INTRO_ALLOWED_HTML
     * reduzieren, leeres Ergebnis als null.
     */
    public static function sanitizeIntro(?string $html): ?string
    {
        if (blank($html) || blank(strip_tags((string) $html))) {
            return null;
        }

        $clean = trim((string) clean((string) $html, ['HTML.Allowed' => self::INTRO_ALLOWED_HTML]));

        return blank(strip_tags($clean)) ? null : $clean;
    }

    /**
     * Stadtteile: getrimmt, ohne Leerwerte und Dubletten.
     *
     * @return list<string>
     */
    public static function normalizeDistricts(mixed $districts): array
    {
        if (! is_array($districts)) {
            return [];
        }

        $names = array_map(fn (mixed $name): string => trim(strip_tags((string) $name)), $districts);

        return array_values(array_unique(array_filter($names, fn (string $name): bool => $name !== '')));
    }

    /**
     * FAQ-Paare als Klartext; Paare ohne Frage oder Antwort entfallen.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function normalizeFaqs(mixed $faqs): array
    {
        if (! is_array($faqs)) {
            return [];
        }

        $result = [];

        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $question = self::plainText((string) ($faq['question'] ?? ''));
            $answer = self::plainText((string) ($faq['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $result[] = ['question' => $question, 'answer' => $answer];
        }

        return $result;
    }

    /**
     * @param  list<string>  $districts
     * @return array<string, string>
     */
    private function placeholderValues(City $city, array $districts): array
    {
        $theme = $this->themes->active()?->slug;

        return [
            '{city}' => (string) $city->name,
            '{trade}' => $this->meta->tradePlural(),
            '{trade_singular}' => (string) (config("themes.{$theme}.search.branch_singular") ?: 'Firma'),
            '{count}' => number_format($city->exists ? $this->meta->activeCompanyCount($city) : 0, 0, ',', '.'),
            '{districts}' => self::joinList(array_slice($districts, 0, self::DISTRICTS_IN_TEXT)),
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @return list<array{question: string, answer: string}>
     */
    private function fillFaqs(mixed $templates, array $values, bool $hasDistricts): array
    {
        $result = [];

        foreach (self::normalizeFaqs($templates) as $faq) {
            if (! $hasDistricts && str_contains($faq['question'], '{districts}')) {
                continue;
            }

            $question = $this->fill($faq['question'], $values, true);
            $answer = $this->fill($faq['answer'], $values, $hasDistricts);

            if ($answer === '') {
                continue;
            }

            $result[] = ['question' => $question, 'answer' => $answer];
        }

        return $result;
    }

    /**
     * Setzt die Platzhalter ein. Ohne Stadtteile werden vorher alle Saetze
     * entfernt, die {districts} enthalten. Absaetze (Leerzeile) bleiben erhalten.
     *
     * @param  array<string, string>  $values
     */
    private function fill(string $text, array $values, bool $hasDistricts): string
    {
        $paragraphs = [];

        foreach (preg_split('/\R\s*\R/u', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim(preg_replace('/[ \t]+/u', ' ', $paragraph) ?? $paragraph);

            if (! $hasDistricts && str_contains($paragraph, '{districts}')) {
                $sentences = preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [];
                $paragraph = implode(' ', array_filter(
                    $sentences,
                    fn (string $sentence): bool => ! str_contains($sentence, '{districts}'),
                ));
            }

            $paragraph = trim(strtr($paragraph, $values));

            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }

        return implode("\n\n", $paragraphs);
    }

    private static function paragraphsToHtml(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $html = array_map(
            fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>',
            explode("\n\n", $text),
        );

        return implode("\n", $html);
    }

    /**
     * Klartext: Tags raus, Entities aufgeloest, Absaetze auf eine Leerzeile.
     */
    private static function plainText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\R */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<string>  $items
     */
    private static function joinList(array $items): string
    {
        if (count($items) < 2) {
            return (string) ($items[0] ?? '');
        }

        $last = array_pop($items);

        return implode(', ', $items).' und '.$last;
    }
}
