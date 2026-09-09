<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\ArticleDraft;

/**
 * Ueberarbeitet einzelne Abschnitte eines fertigen Entwurfs (#15).
 *
 * Der Fix-Durchlauf des Qualitaetsgates schreibt nicht den Artikel neu,
 * sondern genau die Abschnitte, die in `fix_instructions` genannt sind. Das
 * hat zwei Gruende: ein vollstaendiger Neulauf kostet ein Vielfaches, und er
 * wuerde Abschnitte anfassen, die bestanden haben — deren Bewertung waere
 * danach hinfaellig.
 *
 * Abschnittskennungen entstehen hier und nirgends sonst: `s0` ist der Text
 * vor der ersten H2 (in der Regel leer), danach `s1`, `s2`, ... in
 * Reihenfolge der H2 im `body_html`. Die Kennung ist damit aus dem Artikel
 * ableitbar und braucht keine eigene Spalte; sie wandert als Marker in den
 * Rubrik-Prompt (RubricEvaluator) und kommt in `fix_instructions` zurueck.
 *
 * Die Stufe hat zwei Betriebsarten. Sie unterscheiden sich in der Vorlage
 * und im Auftrag an das Modell, nicht im Ergebnis — beide liefern denselben
 * ueberarbeiteten Abschnitt, und beide fassen nur an, was ihnen genannt wird:
 *
 *  - MODE_FIX (#15): das Qualitaetsgate hat den Abschnitt beanstandet. Es
 *    gibt dafuer kein Prompt-Template; der Fall ist die Ausnahme und laeuft
 *    mit dem eingebauten Prompt. Faellt spaeter eines an, greift es ueber
 *    `templateKey()` ohne Codeaenderung.
 *  - MODE_REFRESH (#24): der Abschnitt ist veraltet — ein Beleg ist
 *    abgelaufen oder der Artikel verliert Sichtbarkeit. Vorlage
 *    `refresh_update`. Hier zaehlt zusaetzlich `change_note`: der Satz, den
 *    der Aenderungshinweis unter dem Artikel spaeter traegt.
 */
class FixSectionsStep extends GenerationStep
{
    /** Betriebsart: Beanstandung des Qualitaetsgates beheben (#15). */
    public const MODE_FIX = 'fix';

    /** Betriebsart: veralteten Abschnitt aktualisieren (#24). */
    public const MODE_REFRESH = 'refresh';

    /** Klasse des vom HtmlAssembler gerenderten Fazit-Absatzes. */
    private const SUMMARY_CLASS = 'ratgeber-section__summary';

    /**
     * Die Betriebsart des laufenden Aufrufs. Sie steht am Objekt, weil
     * `templateKey()` und `rules()` aus der Basisklasse ohne Argumente
     * aufgerufen werden; `run()` setzt sie zu Beginn jedes Aufrufs neu, und
     * die Stufe wird je Abschnitt nacheinander benutzt, nie nebenlaeufig.
     */
    private string $mode = self::MODE_FIX;

    public function templateKey(): string
    {
        return $this->mode === self::MODE_REFRESH ? 'refresh_update' : 'section_fix';
    }

    /**
     * Ein Abschnitt, ueberarbeitet nach den uebergebenen Hinweisen.
     *
     * `change_note` traegt nur der Refresh-Modus: der Satz geht in den
     * Aenderungshinweis unter dem Artikel. Im Fix-Modus bleibt er leer.
     *
     * @param  array{id: string, heading: string, summary: string, body: string}  $section
     * @param  array<int, string>  $instructions
     * @return array{id: string, heading: string, summary: string, body: string, change_note: string}
     */
    public function run(
        GenerationContext $context,
        ArticleDraft $draft,
        array $section,
        array $instructions,
        string $mode = self::MODE_FIX,
    ): array {
        $this->mode = $mode === self::MODE_REFRESH ? self::MODE_REFRESH : self::MODE_FIX;

        $result = $this->ask($context, $draft, [
            'section' => $this->sectionText($section, $instructions),
            'outline' => $this->outlineText($draft),
        ]);

        $summary = trim((string) ($result['summary_sentence'] ?? ''));
        $body = trim((string) ($result['html'] ?? ''));

        return [
            'id' => $section['id'],
            'heading' => trim((string) ($result['heading'] ?? '')) ?: $section['heading'],
            'summary' => $summary !== '' ? $summary : $section['summary'],
            'body' => $body !== '' ? $body : $section['body'],
            'change_note' => trim((string) ($result['change_note'] ?? '')),
        ];
    }

    /**
     * Zerlegt den Artikel in Abschnitte. Jeder Abschnitt beginnt mit seiner
     * H2; der Fazit-Absatz wird getrennt gefuehrt, weil er beim Zusammenbau
     * wieder eigens gerendert wird.
     *
     * @return array<int, array{id: string, heading: string, summary: string, body: string}>
     */
    public static function split(string $html): array
    {
        $chunks = preg_split('/(?=<h2[\s>])/i', trim($html), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sections = [];
        $index = 0;

        foreach ($chunks as $chunk) {
            $heading = '';
            $body = $chunk;

            if (preg_match('/^<h2[^>]*>(.*?)<\/h2>/is', $chunk, $matches) === 1) {
                $heading = trim(html_entity_decode(strip_tags((string) $matches[1])));
                $body = (string) substr($chunk, strlen((string) $matches[0]));
            }

            $summary = '';
            $pattern = '/^\s*<p[^>]*class="'.self::SUMMARY_CLASS.'"[^>]*>(.*?)<\/p>/is';

            if (preg_match($pattern, $body, $matches) === 1) {
                $summary = trim(html_entity_decode(strip_tags((string) $matches[1])));
                $body = (string) preg_replace($pattern, '', $body, 1);
            }

            $sections[] = [
                'id' => $heading === '' && $index === 0 ? 's0' : 's'.++$index,
                'heading' => $heading,
                'summary' => $summary,
                'body' => trim($body),
            ];
        }

        return $sections;
    }

    /**
     * Baut den Artikel aus den Abschnitten wieder zusammen — mit demselben
     * Aufbau, den der HtmlAssembler beim ersten Lauf erzeugt hat.
     *
     * @param  array<int, array{id: string, heading: string, summary: string, body: string}>  $sections
     */
    public static function join(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            $part = [];

            if ($section['heading'] !== '') {
                $part[] = '<h2>'.e($section['heading']).'</h2>';
            }

            if (trim($section['summary']) !== '') {
                $part[] = '<p class="'.self::SUMMARY_CLASS.'"><strong>'.e(trim($section['summary'])).'</strong></p>';
            }

            if (trim($section['body']) !== '') {
                $part[] = trim($section['body']);
            }

            $parts[] = implode("\n", $part);
        }

        return trim(implode("\n", array_filter($parts)));
    }

    /**
     * Ausgabeschema beider Betriebsarten — die Quelle, auf die sich der
     * Seeder und der Prompt-Editor (#58/#67) beziehen.
     *
     * `change_note` ist bewusst nicht Pflicht: nur der Refresh-Modus liest
     * ihn, und ein Pflichtfeld haette den Vertrag der Vorlage
     * betriebsartabhaengig gemacht.
     *
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['heading', 'summary_sentence', 'html', 'used_fact_ids'],
            'properties' => [
                'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                'summary_sentence' => ['type' => 'string', 'minLength' => 30, 'maxLength' => 300],
                'html' => ['type' => 'string', 'minLength' => 80],
                'used_fact_ids' => ['type' => 'array', 'maxItems' => 12, 'items' => ['type' => 'integer']],
                'change_note' => ['type' => 'string', 'maxLength' => 300],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return self::outputSchema();
    }

    protected function rules(GenerationContext $context): string
    {
        $mode = $this->mode === self::MODE_REFRESH
            ? '- Aendern Sie nur, was durch die genannten Gruende tatsaechlich veranlasst ist. Ein Satz, '
                ."den kein Grund betrifft, bleibt Wort fuer Wort stehen.\n"
                ."- Die Ueberschrift bleibt unveraendert, ausser eine Jahreszahl darin ist ueberholt.\n"
                .'- change_note ist ein Satz in der Sie-Form, der sagt, was aktualisiert wurde. '
                ."Beispiel: Foerderbetraege auf den Stand 2026 gebracht.\n"
            : '';

        return "Regeln fuer die Ausgabe:\n"
            .$mode
            ."- Ueberarbeiten Sie ausschliesslich diesen Abschnitt. Andere Abschnitte kennen Sie nicht und aendern Sie nicht.\n"
            ."- Behalten Sie Aufbau und Laenge bei, soweit die Hinweise nichts anderes verlangen.\n"
            .'- html enthaelt ausschliesslich <p>, <ul>, <ol>, <li>, <strong>, <em>, <h3> und <a>. '
            ."Keine <h2>, und der Fazit-Satz steht NICHT in html.\n"
            .'- Jede Zahl, jeder Preis und jede Frist stammt aus der Faktenliste. Streichen Sie eine Zahl, '
            ."die dort nicht steht, statt sie zu ersetzen. Verwendete Faktennummern nennen Sie in used_fact_ids.\n"
            .'- Externe Links nur auf die genannten Quellen, interne Links nur auf die vorgegebenen URLs.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        $auftrag = $this->mode === self::MODE_REFRESH
            ? 'Der folgende Abschnitt eines bereits veroeffentlichten Ratgebers ist veraltet. '
                .'Bringen Sie genau diesen Abschnitt auf den aktuellen Stand — nicht mehr und nicht weniger.'
            : 'Ein Qualitaetsgate hat den folgenden Abschnitt eines veroeffentlichungsreifen '
                .'Ratgebers beanstandet. Ueberarbeiten Sie genau diesen Abschnitt so, dass die '
                .'Beanstandungen behoben sind — nicht mehr und nicht weniger.';

        return <<<TEXT
            {$auftrag}

            Gliederung des Artikels (nur zur Orientierung):
            {$vars['outline']}

            Abschnitt und Hinweise:
            {$vars['section']}

            Branche: {$context->branchLabel}, Portal: {$context->tenantName}
            Haupt-Keyword: {$context->primaryKeyword}
            Regionsbezug: {$context->regionScope} ({$context->regionName})
            Belegte Fakten, die Sie zitieren duerfen:
            {$vars['fact_snippets']}
            Interne Linkziele:
            {$vars['internal_link_targets']}
            TEXT;
    }

    /**
     * @param  array{id: string, heading: string, summary: string, body: string}  $section
     * @param  array<int, string>  $instructions
     */
    private function sectionText(array $section, array $instructions): string
    {
        $lines = [
            "Abschnittskennung: {$section['id']}",
            'H2: '.($section['heading'] !== '' ? $section['heading'] : '(Einleitung ohne Ueberschrift)'),
            'Fazit-Satz: '.($section['summary'] !== '' ? $section['summary'] : '(noch keiner)'),
            '',
            'Bisheriges HTML des Abschnitts:',
            $section['body'],
            '',
            ($this->mode === self::MODE_REFRESH ? "Gruende fuer die Aktualisierung:\n- " : "Zu behebende Beanstandungen:\n- ")
                .implode("\n- ", $instructions),
        ];

        return implode("\n", $lines);
    }

    private function outlineText(ArticleDraft $draft): string
    {
        $headings = (array) (($draft->outline_json ?? [])['headings'] ?? []);

        if ($headings === []) {
            return 'liegt nicht vor';
        }

        return collect($headings)->map(
            static fn (array $item): string => 'H'.($item['level'] ?? 2).': '.($item['heading'] ?? '')
        )->implode("\n");
    }
}
