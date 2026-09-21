<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Llm\LlmClient;
use App\Content\Llm\LlmContext;
use App\Content\Llm\PromptRenderer;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\PromptTemplate;
use App\Content\Support\UmlautSpelling;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Gemeinsames Verhalten der Generierungsstufen (#14).
 *
 * Arbeitsteilung zwischen Redaktion und Code — dieselbe wie in
 * DiscoverTopicsJob:
 *
 *  - Der redaktionelle Teil des Prompts kommt aus `prompt_templates` (#13) und
 *    ist im Content-Panel editierbar. Ist das Template nicht renderbar, weil
 *    jemand einen unbekannten Platzhalter eingefuegt hat, traegt der eingebaute
 *    Ersatztext den Aufruf allein. Ein Tippfehler in der Redaktion darf keinen
 *    Artikel kosten.
 *
 *  - Das Ausgabeschema gehoert dem Code, nicht dem Template. Es ist der
 *    Vertrag zwischen Stufe und HtmlAssembler; waere es editierbar, koennte
 *    eine Panel-Aenderung den Zusammenbau lautlos brechen. Genau deshalb ist
 *    `summary_sentence` in SectionStep Pflicht und bleibt es.
 *
 * Der System-Prompt traegt Styleguide und Verbotsliste. Er ist je Branche
 * identisch und wird vom LlmClient mit cache_control versehen — bei einem
 * Dutzend Aufrufen je Artikel ist das der Grossteil der Eingabekosten.
 */
abstract class GenerationStep
{
    public function __construct(
        protected readonly LlmClient $llm,
        protected readonly PromptRenderer $renderer,
    ) {}

    /**
     * Schluessel des Templates in `prompt_templates`.
     */
    /**
     * Die Prompts sind in ASCII geschrieben ("fuer", "Naehe"); ohne diese
     * Regel uebernimmt das Modell die Umschreibung in den Artikel (#41).
     * Sie steht im Code, damit sie fuer jede Vorlagenversion gilt.
     */
    public const SPELLING_RULE = 'Rechtschreibung: Schreiben Sie im gesamten Ergebnis die deutschen Umlaute '
        .'und das ß korrekt (ä, ö, ü, Ä, Ö, Ü, ß), also „für", „Nähe", „Störung", „Größe". Umschreibungen wie '
        .'„ae", „oe", „ue" oder „ss" statt ß stehen in diesen Anweisungen nur aus technischen Gründen und '
        .'dürfen im Text nie vorkommen. Ausgenommen sind Link-Adressen, die Sie unverändert übernehmen.';

    /**
     * Ueber die Claude-CLI beschrieb das Modell mitunter sein Vorgehen statt
     * zu liefern ("Kurzantwort erstellt: Sie beantwortet ..."), #42.
     */
    public const CONTENT_ONLY_RULE = 'Ausgabe: Die Felder enthalten ausschließlich den fertigen Text für die Leserinnen '
        .'und Leser. Keine Beschreibung Ihres Vorgehens, keine Vorbemerkung wie „Kurzantwort:" oder „Hier ist …", '
        .'keine Anführungszeichen um den ganzen Text.';

    abstract public function templateKey(): string;

    /**
     * Ausgabeschema des erzwungenen Tool-Use.
     *
     * @return array<string, mixed>
     */
    abstract protected function schema(GenerationContext $context): array;

    /**
     * Eingebauter Prompt, wenn das Template fehlt oder nicht renderbar ist.
     *
     * @param  array<string, mixed>  $vars
     */
    abstract protected function fallbackPrompt(GenerationContext $context, array $vars): string;

    /**
     * Maschinelle Ausgaberegeln, die der Job dem redaktionellen Prompt
     * anhaengt. Sie stehen bewusst nicht im Template: sie beschreiben das
     * Datenformat, nicht die Redaktion.
     */
    protected function rules(GenerationContext $context): string
    {
        return '';
    }

    /**
     * Ein Modellaufruf mit erzwungenem Schema.
     *
     * @param  array<string, mixed>  $extraVars
     * @return array<string, mixed>
     */
    protected function ask(GenerationContext $context, ArticleDraft $draft, array $extraVars = []): array
    {
        $vars = $context->promptVars($extraVars);
        $template = PromptTemplate::query()->resolve($this->templateKey(), $context->tenantId)->first();

        [$system, $user] = $this->prompts($template, $context, $vars);

        $rules = trim($this->rules($context));

        if ($rules !== '') {
            $user .= "\n\n".$rules;
        }

        $user .= "\n\n".self::SPELLING_RULE."\n\n".self::CONTENT_ONLY_RULE;

        $payload = $this->llm->emit(
            $system,
            $user,
            $this->schema($context),
            LlmContext::forDraft($draft, $this->templateKey()),
            $this->templateKey(),
        );

        return (array) UmlautSpelling::repairPayload($payload);
    }

    /**
     * System- und Nutzerprompt, mit Ersatztext bei nicht renderbarem Template.
     *
     * @param  array<string, mixed>  $vars
     * @return array{0: ?string, 1: string}
     */
    private function prompts(?PromptTemplate $template, GenerationContext $context, array $vars): array
    {
        $system = $this->fallbackSystemPrompt($context);

        if ($template === null) {
            return [$system, $this->fallbackPrompt($context, $vars)];
        }

        try {
            $rendered = $this->renderer->renderTemplate($template, $vars);

            return [$rendered['system'] ?? $system, $rendered['user']];
        } catch (InvalidArgumentException $exception) {
            Log::warning('Prompt-Template nicht renderbar, Ersatztext verwendet.', [
                'template' => $this->templateKey(),
                'tenant_id' => $context->tenantId,
                'exception' => $exception->getMessage(),
            ]);

            return [$system, $this->fallbackPrompt($context, $vars)];
        }
    }

    /**
     * Die Redaktionsstandards, wenn kein Template greift. Inhaltlich gleich
     * mit dem System-Prompt aus dem PromptTemplateSeeder (#13).
     */
    private function fallbackSystemPrompt(GenerationContext $context): string
    {
        return <<<TEXT
            Sie sind Redakteur:in fuer Ratgeberinhalte auf einem deutschen Branchenportal
            ({$context->tenantName}, Branche {$context->branchLabel}). Halten Sie sich strikt an
            diese Redaktionsstandards:

            - Deutsch, Sie-Form, sachlich und hilfsbereit.
            - Keine Superlative ("beste", "guenstigste", "Nr. 1"), keine Werbesprache.
            - Keine Floskeln und Fuellsaetze.
            - Jede Zahl, jede Statistik, jeder Preis und jede Frist braucht einen Beleg aus
              den uebergebenen Faktenschnipseln oder Portaldaten. Erfinden Sie keine Zahlen
              und keine Quellen. Ist etwas nicht belegt, schreiben Sie es nicht.
            - Kurze Absaetze (2-4 Saetze), aktive Saetze, konkrete Verben.
            - Beantworten Sie die Suchintention ({$context->intent}) zuerst.
            - Regionalbezuege ({$context->regionName}) gehoeren in den Fliesstext, nicht nur in
              Ueberschrift oder Meta-Angaben.

            Styleguide der Branche:

            {$context->styleguide}
            TEXT;
    }
}
