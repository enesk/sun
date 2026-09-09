<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Generation\Exceptions\MissingRegionalFactsException;
use App\Content\Models\ArticleDraft;
use App\Content\Models\FactSnippet;
use Illuminate\Support\Collection;

/**
 * Regionalblock (#14) — nur bei region_scope != national.
 *
 * Der Block ist der Teil, den kein Wettbewerber schreiben kann: Portalzahlen
 * der Region plus regionale Foerderung und regionales Recht. Genau deshalb
 * darf er nicht aus dem Modellwissen entstehen. Fehlt jeder regionale
 * Faktenschnipsel, bricht die Stufe mit 'missing_regional_facts' ab; der
 * Aufrufer setzt den Kandidaten dann auf 'national' zurueck und schreibt einen
 * bundesweiten Artikel, statt eine Doorway-Seite zu erzeugen.
 *
 * Die Ausgabe ist absichtlich zweigleisig: `intro`/`outro` als Klartext plus
 * `html` als Fliesstext. Das Ratgeber-Template (#17) rendert bei
 * Stadt-Zuschnitt einen eigenen Block aus Klartext und Faktenpaaren; bei
 * Landes-Zuschnitt gibt es diesen Block nicht, dort wandert das HTML als
 * Abschnitt in den Fliesstext. Beides aus einem Aufruf.
 */
class RegionalBlockStep extends GenerationStep
{
    public function templateKey(): string
    {
        return 'regional_block';
    }

    /**
     * @param  array<int, array{heading: string, level: int, key_points: array<int, string>}>  $outline
     * @return array{heading: string, intro: string, outro: string, html: string, facts: array<int, array{label: string, value: string}>, used_facts: array<int, int>}
     *
     * @throws MissingRegionalFactsException
     */
    public function run(GenerationContext $context, ArticleDraft $draft, array $outline): array
    {
        $regional = $context->regionalFactSnippets();

        if ($regional->isEmpty()) {
            throw MissingRegionalFactsException::for($context->regionScope, $context->regionCode);
        }

        $result = $this->ask($context, $draft, [
            'outline' => collect($outline)->map(
                static fn (array $item): string => 'H'.$item['level'].': '.$item['heading']
            )->implode("\n"),
        ]);

        return [
            'heading' => trim((string) ($result['heading'] ?? '')) ?: "Was in {$context->regionName} gilt",
            'intro' => trim((string) ($result['intro'] ?? '')),
            'outro' => trim((string) ($result['outro'] ?? '')),
            'html' => trim((string) ($result['html'] ?? '')),
            'facts' => $this->facts($context, $regional),
            'used_facts' => $regional->pluck('id')->map('intval')->all(),
        ];
    }

    /**
     * Die Faktenpaare des Blocks entstehen ohne Modellaufruf: Portalzahl und
     * regionale Schnipsel liegen bereits strukturiert vor, und ein
     * Modellaufruf koennte sie nur verfaelschen.
     *
     * @param  Collection<int, FactSnippet>  $regional
     * @return array<int, array{label: string, value: string}>
     */
    private function facts(GenerationContext $context, Collection $regional): array
    {
        $facts = [];
        $portal = $context->portalData;

        if ($portal !== null && (int) ($portal['provider_count'] ?? 0) > 0) {
            $facts[] = [
                'label' => 'Gelistete Betriebe',
                'value' => number_format((int) $portal['provider_count'], 0, ',', '.'),
            ];

            if (($portal['avg_rating'] ?? null) !== null) {
                $facts[] = [
                    'label' => 'Durchschnittsbewertung',
                    'value' => number_format((float) $portal['avg_rating'], 1, ',', '.')
                        .' von 5 ('.number_format((int) ($portal['review_count'] ?? 0), 0, ',', '.').' Bewertungen)',
                ];
            }
        }

        $seen = array_flip(array_column($facts, 'label'));

        foreach ($regional as $snippet) {
            if (! $snippet->isTabular()) {
                continue;
            }

            $label = $snippet->displayLabel();

            if (isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;

            $facts[] = [
                'label' => $label,
                'value' => trim((string) $snippet->value.' '.(string) $snippet->unit),
            ];

            if (count($facts) >= 6) {
                break;
            }
        }

        return $facts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(GenerationContext $context): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['heading', 'intro', 'html', 'outro'],
            'properties' => [
                'heading' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 120],
                'intro' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 400],
                'html' => ['type' => 'string', 'minLength' => 120],
                'outro' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 300],
            ],
        ];
    }

    protected function rules(GenerationContext $context): string
    {
        return "Regeln fuer die Ausgabe:\n"
            ."- intro ist ein Satz, der den Regionalbezug auf {$context->regionName} begruendet — "
            ."warum die Lage dort anders ist als bundesweit.\n"
            ."- html sind 100 bis 180 Woerter in <p>-Absaetzen, ohne Ueberschrift.\n"
            .'- outro ist ein Satz, der zur Betriebssuche im Portal ueberleitet, ohne Werbesprache '
            ."und ohne Superlativ.\n"
            .'- Jede Zahl stammt aus der Faktenliste oder den Portaldaten. Keine geschaetzten '
            .'Betriebszahlen, keine erfundenen Foerdersaetze.';
    }

    protected function fallbackPrompt(GenerationContext $context, array $vars): string
    {
        return <<<TEXT
            Schreiben Sie einen Abschnitt mit echtem Regionalbezug fuer {$context->regionScope}
            {$context->regionName}, Branche {$context->branchLabel}, Portal {$context->tenantName}.

            Portaldaten:
            {$vars['portal_data']}

            Belegte Fakten:
            {$vars['fact_snippets']}

            Gliederung des Artikels (Kontext):
            {$vars['outline']}

            Interne Linkziele:
            {$vars['internal_link_targets']}
            TEXT;
    }
}
