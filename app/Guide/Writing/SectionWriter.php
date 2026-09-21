<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmClient;
use App\Guide\Research\SectionFactMap;

/**
 * Schreibt und aktualisiert einzelne Abschnitte (#10, Stufen
 * guide.write_section und guide.update_section) und bessert sie im
 * Fix-Durchlauf des Qualitaetsgates nach (#11, guide.fix_section).
 *
 * Jeder Aufruf betrifft genau einen Gliederungspunkt, damit er klein bleibt;
 * System-Prompt und Styleguide sind gecacht, das Fakten-Set steht in jedem
 * Aufruf gleich.
 *
 * Der Fazit-Satz einer H2 kommt als eigenes Feld (summary_sentence) und wird
 * vom HtmlAssembler als erster Absatz gerendert — so steht er garantiert
 * vorn und genau einmal. used_fact_keys zaehlen nur, wenn der Schluessel im
 * Fakten-Set des Themas steht (SectionFactMap).
 */
class SectionWriter
{
    public const TEMPLATE_WRITE = 'guide.write_section';

    public const TEMPLATE_UPDATE = 'guide.update_section';

    public const TEMPLATE_FIX = 'guide.fix_section';

    /** Abkuerzungen, an denen kein Satz endet. */
    private const ABBREVIATIONS = ['z. B.', 'z.B.', 'd. h.', 'd.h.', 'u. a.', 'u.a.', 'ca.', 'bzw.', 'inkl.', 'ggf.', 'evtl.', 'etc.', 'Nr.', 'Abs.', 'bspw.', 'max.', 'min.', 'mind.', 'zzgl.', 'Std.', 'Mio.', 'Mrd.'];

    public function __construct(
        private readonly LlmClient $client,
        private readonly HtmlAssembler $html,
    ) {}

    /**
     * @param  array{id: string, text: string, level: int}  $entry
     * @param  list<array{type: string, url: string, anchor: string}>  $targets  Linkziele dieses Abschnitts
     * @param  array<int, string>  $internalUrls  alle erlaubten internen Ziele des Artikels
     * @return array{html: string, used_fact_keys: array<int, string>}
     */
    public function write(WritingContext $context, array $entry, array $targets, array $internalUrls, SectionFactMap $map): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE_WRITE),
            $context->vars([
                'section' => $this->sectionJson($entry, $map),
                'links' => $this->linksText($targets),
            ]),
            self::writeSchema($entry['level']),
            $context->llmContext(),
        );

        $body = $this->html->sanitize((string) ($result['html'] ?? ''), $internalUrls, $context->externalUrls);

        if ($entry['level'] === 2) {
            $summary = self::firstSentence((string) ($result['summary_sentence'] ?? ''));
            $body = trim($this->html->lead($summary)."\n".$this->withoutParagraph($body, $summary));
        }

        return [
            'html' => $this->html->ensureLinks($body, $targets),
            'used_fact_keys' => $context->knownFactKeys((array) ($result['used_fact_keys'] ?? [])),
        ];
    }

    /**
     * Schreibt einen bestehenden Abschnitt fort. changed = false heisst: Das
     * bestehende HTML bleibt, wie es ist (der Aufrufer uebernimmt das
     * Segment unveraendert).
     *
     * @param  array{id: string, text: string, level: int}  $entry
     * @param  array<int, array<string, mixed>>  $changedFacts
     * @param  array<int, string>  $internalUrls
     * @return array{changed: bool, html: string, used_fact_keys: array<int, string>, edited_sentences: array<int, array<string, string>>}
     */
    public function update(WritingContext $context, array $entry, string $existingBody, array $changedFacts, array $internalUrls, SectionFactMap $map): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE_UPDATE),
            $context->vars([
                'section' => $this->sectionJson($entry, $map),
                'existing_section_html' => $existingBody,
                'changed_facts' => WritingContext::json($changedFacts),
            ]),
            self::updateSchema(),
            $context->llmContext(),
        );

        $unchanged = ['changed' => false, 'html' => $existingBody, 'used_fact_keys' => $map->sections[$entry['id']] ?? [], 'edited_sentences' => []];

        if (! (bool) ($result['changed'] ?? false)) {
            return $unchanged;
        }

        // Bestehende Links des Abschnitts bleiben erlaubt.
        $allowedInternal = array_values(array_unique([...$internalUrls, ...$this->html->links($existingBody, internal: true)]));
        $allowedExternal = array_values(array_unique([...$context->externalUrls, ...$this->html->links($existingBody, internal: false)]));
        $body = $this->html->sanitize((string) ($result['html'] ?? ''), $allowedInternal, $allowedExternal);

        if ($body === '' || $body === trim($existingBody)) {
            return $unchanged;
        }

        // Fazit-Satz nicht verlieren: Fehlt der Anfangsabsatz, bleibt der alte.
        if ($entry['level'] === 2 && ! str_starts_with($body, '<p>') && preg_match('#^\s*(<p>.*?</p>)#s', $existingBody, $lead) === 1) {
            $body = $lead[1]."\n".$body;
        }

        return [
            'changed' => true,
            'html' => $body,
            'used_fact_keys' => $context->knownFactKeys((array) ($result['used_fact_keys'] ?? [])),
            'edited_sentences' => array_values(array_filter((array) ($result['edited_sentences'] ?? []), 'is_array')),
        ];
    }

    /**
     * Fix-Durchlauf des Qualitaetsgates (#11): bessert einen Abschnitt nach
     * den Befunden nach. Links wie beim Schreiben; externe nur auf Quellen,
     * die nicht als broken markiert sind.
     *
     * @param  array{id: string, text: string, level: int}  $entry
     * @param  array<int, string>  $instructions
     * @param  list<array{type: string, url: string, anchor: string}>  $targets
     * @param  array<int, string>  $internalUrls
     * @param  array<int, string>  $externalUrls
     * @return array{html: string, used_fact_keys: array<int, string>}
     */
    public function fix(WritingContext $context, array $entry, string $existingBody, array $instructions, array $targets, array $internalUrls, array $externalUrls, SectionFactMap $map): array
    {
        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE_FIX),
            $context->vars([
                'section' => $this->sectionJson($entry, $map),
                'existing_section_html' => $existingBody,
                'fix_instructions' => implode("\n", array_map(fn (string $instruction): string => "- {$instruction}", $instructions)),
                'links' => $this->linksText($targets),
            ]),
            self::writeSchema($entry['level']),
            $context->llmContext(),
        );

        $body = $this->html->sanitize((string) ($result['html'] ?? ''), $internalUrls, $externalUrls);

        if ($entry['level'] === 2) {
            $summary = self::firstSentence((string) ($result['summary_sentence'] ?? ''));
            $body = trim($this->html->lead($summary)."\n".$this->withoutParagraph($body, $summary));
        }

        return [
            'html' => $this->html->ensureLinks($body, $targets),
            'used_fact_keys' => $context->knownFactKeys((array) ($result['used_fact_keys'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function writeSchema(int $level = 2): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['section_id', 'summary_sentence', 'html', 'used_fact_keys'],
            'properties' => [
                'section_id' => ['type' => 'string'],
                'summary_sentence' => $level === 2
                    ? ['type' => 'string', 'minLength' => 20, 'maxLength' => 260]
                    : ['type' => ['string', 'null']],
                'html' => ['type' => 'string', 'minLength' => 40],
                'used_fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['section_id', 'changed', 'html', 'used_fact_keys', 'edited_sentences'],
            'properties' => [
                'section_id' => ['type' => 'string'],
                'changed' => ['type' => 'boolean'],
                'html' => ['type' => 'string', 'minLength' => 40],
                'used_fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                'edited_sentences' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['old', 'new', 'fact_key'],
                        'properties' => [
                            'old' => ['type' => 'string'],
                            'new' => ['type' => 'string'],
                            'fact_key' => ['type' => 'string', 'maxLength' => 128],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Erster Satz eines Textes; Abkuerzungen wie "z. B." beenden keinen Satz.
     */
    public static function firstSentence(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($text === '') {
            return '';
        }

        $placeholders = [];

        foreach (self::ABBREVIATIONS as $index => $abbreviation) {
            $placeholders["\u{E000}{$index}\u{E001}"] = $abbreviation;
        }

        $protected = str_replace(array_values($placeholders), array_keys($placeholders), $text);
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}„"])/u', $protected, 2) ?: [$protected];

        return trim(str_replace(array_keys($placeholders), array_values($placeholders), $parts[0]));
    }

    /**
     * @param  array{id: string, text: string, level: int}  $entry
     */
    private function sectionJson(array $entry, SectionFactMap $map): string
    {
        return WritingContext::json([
            'id' => $entry['id'],
            'level' => $entry['level'],
            'heading' => $entry['text'],
            'fact_keys' => $map->sections[$entry['id']] ?? [],
        ]);
    }

    /**
     * @param  list<array{type: string, url: string, anchor: string}>  $targets
     */
    private function linksText(array $targets): string
    {
        if ($targets === []) {
            return 'keine';
        }

        return implode("\n", array_map(
            fn (array $target): string => '- <a href="'.$target['url'].'">'.$target['anchor'].'</a>',
            $targets,
        ));
    }

    /**
     * Hat das Modell den Fazit-Satz zusaetzlich in html geschrieben, faellt
     * der doppelte Absatz weg.
     */
    private function withoutParagraph(string $body, string $sentence): string
    {
        if ($sentence === '') {
            return $body;
        }

        return trim((string) preg_replace_callback(
            '#<p>(.*?)</p>\s*#s',
            function (array $match) use ($sentence): string {
                $text = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return $text === $sentence ? '' : $match[0];
            },
            $body,
            1,
        ));
    }
}
