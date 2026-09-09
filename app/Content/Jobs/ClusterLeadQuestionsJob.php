<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\LlmClient;
use App\Content\Llm\LlmContext;
use App\Content\Llm\PromptRenderer;
use App\Content\Llm\PromptSchemaContract;
use App\Content\Models\Central\PromptTemplate;
use App\Content\Models\SourceItem;
use App\Content\Sources\SourceItemDto;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Freitexte der Nutzer zu Fragethemen buendeln (#11), woechentlich je Mandant.
 *
 * Was Besucher in die Suche tippen, in eine Bewertung schreiben oder als
 * Korrekturhinweis schicken, ist die ehrlichste Themenquelle des Portals —
 * und die einzige, die kein Wettbewerber hat. Sie ist zugleich die heikelste,
 * deshalb zwei Schutzschichten:
 *
 *   1. Vor dem Modellaufruf werden E-Mail-Adressen, Telefonnummern, URLs,
 *      IBANs, Hausnummern und Anreden mit Namen per Regex entfernt. Was der
 *      Regex nicht sicher erkennt, wird verworfen statt gesendet: Texte, in
 *      denen nach der Bereinigung noch Ziffernfolgen stehen, fallen raus.
 *   2. Das Modell bekommt die Anweisung, ausschliesslich Fragethemen
 *      zurueckzugeben, und liefert ueber das erzwungene Schema hoechstens
 *      max_questions Eintraege. Rohtexte werden nirgends gespeichert.
 *
 * Ergebnis sind source_items vom Typ 'lead_question' mit dem Fragethema als
 * Titel — die gehen in Scoring (#12) und Generator (#14) wie jedes andere
 * Rohsignal ein.
 */
class ClusterLeadQuestionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public const SOURCE_KEY = 'lead_questions';

    public const TEMPLATE_KEY = 'lead_question_cluster';

    /**
     * Auswerter-System-Prompt des Lead-Clusterings. Bewusst nicht der
     * Redakteurstext der Schreib-Stufen: hier entsteht kein Artikel, und
     * {{styleguide}}/{{fact_snippets}} gibt es an dieser Stelle nicht.
     * Der Seeder uebernimmt diese Konstante woertlich in Version 2 der
     * Vorlage — Prompt-Editor und Ersatztext bleiben so wortgleich.
     */
    public const SYSTEM_PROMPT = 'Du wertest anonymisierte Nutzereingaben eines deutschen Branchenportals aus. '
        .'Du fasst sie zu Fragethemen zusammen. Du gibst ausschliesslich Fragethemen zurueck, '
        .'niemals Namen, Orte aus einzelnen Eingaben, Zitate oder ganze Eingaben. '
        .'Formuliere jede Frage so, wie ein Ratsuchender sie stellen wuerde.';

    /**
     * Der Vertrag mit dem Code: diese Felder liest store() namentlich aus.
     * Traegt das Schema einer Vorlage sie nicht alle, laeuft der Aufruf mit
     * dem Schema aus dem Code weiter (design/content-dashboard.md §7b).
     *
     * @var array<int, string>
     */
    public const REQUIRED_QUESTION_FIELDS = [
        'question',
        'share',
    ];

    public function __construct(
        public int $tenantId,
    ) {
        $this->onQueue((string) config('content.sources.queue', 'content-sources'));
    }

    public function uniqueId(): string
    {
        return 'content-lead-questions:'.$this->tenantId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(LlmClient $llm, PromptRenderer $renderer): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $branch = BranchResolver::resolve($tenant) ?? 'default';

        $tenant->run(function () use ($llm, $renderer, $branch): void {
            $texts = $this->collectTexts();
            $minTexts = max(1, (int) config('content.sources.lead_questions.min_texts', 20));

            if (count($texts) < $minTexts) {
                Log::info('Lead-Clustering uebersprungen: zu wenige Freitexte.', [
                    'tenant_id' => $this->tenantId,
                    'texts' => count($texts),
                ]);

                return;
            }

            try {
                $questions = $this->cluster($llm, $renderer, $texts, $branch);
            } catch (BudgetExceededException $exception) {
                Log::warning('Lead-Clustering ausgesetzt: Budget erschoepft.', [
                    'tenant_id' => $this->tenantId,
                    'exception' => $exception->getMessage(),
                ]);

                return;
            }

            $this->store($questions, $branch, count($texts));
        });
    }

    /**
     * Freitexte aus den konfigurierten Tabellen, bereinigt und gekuerzt.
     *
     * @return array<int, string>
     */
    private function collectTexts(): array
    {
        $since = CarbonImmutable::now()->subDays(max(1, (int) config('content.sources.lead_questions.window_days', 90)));
        $maxTexts = max(1, (int) config('content.sources.lead_questions.max_texts', 400));

        $texts = [];

        foreach ((array) config('content.sources.lead_questions.tables', []) as $source) {
            $source = (array) $source;
            $table = (string) ($source['table'] ?? '');
            $column = (string) ($source['column'] ?? '');

            if ($table === '' || $column === '' || ! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->when(
                    Schema::hasColumn($table, (string) ($source['date_column'] ?? 'created_at')),
                    fn ($query) => $query->where((string) ($source['date_column'] ?? 'created_at'), '>=', $since),
                )
                ->limit($maxTexts)
                ->pluck($column);

            foreach ($rows as $row) {
                $clean = $this->anonymize((string) $row);

                if ($clean !== null) {
                    $texts[] = $clean;
                }

                if (count($texts) >= $maxTexts) {
                    return array_values(array_unique($texts));
                }
            }
        }

        return array_values(array_unique($texts));
    }

    /**
     * Entfernt personenbezogene Angaben. Gibt null zurueck, wenn der Text
     * danach unbrauchbar oder immer noch verdaechtig ist — im Zweifel wird
     * verworfen, nicht gesendet.
     */
    private function anonymize(string $text): ?string
    {
        $clean = strip_tags($text);

        $patterns = [
            // E-Mail
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u' => ' ',
            // Telefon: +49..., 030/123456, 0170 1234567
            '/(?:\+\d{1,3}[\s\/-]?)?\(?\d{3,5}\)?[\s\/-]?\d[\d\s\/-]{4,}\d/u' => ' ',
            // URL
            '/https?:\/\/\S+|www\.\S+/iu' => ' ',
            // IBAN
            '/\b[A-Z]{2}\d{2}[\sA-Z0-9]{10,32}\b/u' => ' ',
            // Anrede mit Namen ("Herr Mueller", "Frau Dr. Schmidt")
            '/\b(?:Herrn?|Frau|Fam\.|Familie)\s+(?:Dr\.\s+|Prof\.\s+)?[A-ZÄÖÜ][\wäöüß-]+/u' => ' ',
            // Grussformel mit Namen am Ende
            '/(?:MfG|LG|Mit freundlichen Gr(?:ü|u)(?:ss|ß)en|Viele Gr(?:ü|u)(?:ss|ß)e|Gru(?:ss|ß))\b.*$/isu' => ' ',
            // Strasse mit Hausnummer
            '/\b[A-ZÄÖÜ][\wäöüß-]*(?:stra[ßs]{1,2}e|str\.|weg|allee|platz|gasse)\s*\d+\s*[a-z]?\b/iu' => ' ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $clean = preg_replace($pattern, $replacement, $clean) ?? $clean;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if (mb_strlen($clean) < 8) {
            return null;
        }

        // Nach der Bereinigung sollte keine laengere Ziffernfolge mehr
        // stehen; wenn doch, ist es vermutlich eine nicht erkannte Nummer.
        if (preg_match('/\d{5,}/u', $clean) === 1) {
            return null;
        }

        return Str::limit($clean, 300, '');
    }

    /**
     * Ein Modellaufruf mit erzwungenem Schema.
     *
     * Der ganze Prompt steht in der Vorlage (#75): Branche, Hoechstzahl und
     * die anonymisierten Eingaben gehen als Variablen hinein, statt an einen
     * fest verdrahteten Text angehaengt zu werden. Nur so zeigt die Vorschau
     * im Prompt-Editor denselben Prompt, den der Lauf abschickt. Fehlt die
     * Vorlage oder ist sie nicht renderbar, traegt der eingebaute Ersatztext
     * den Aufruf allein.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<string, mixed>>
     */
    private function cluster(LlmClient $llm, PromptRenderer $renderer, array $texts, string $branch): array
    {
        $maxQuestions = max(1, (int) config('content.sources.lead_questions.max_questions', 10));
        $branchLabel = BranchResolver::label($branch) ?? 'Handwerk und Dienstleistung';
        $codeSchema = self::outputSchema($maxQuestions);
        $llmContext = new LlmContext(tenantId: $this->tenantId, operation: 'lead-questions');

        $vars = [
            'branch' => $branchLabel,
            'max_questions' => (string) $maxQuestions,
            'inputs' => '- '.implode("\n- ", $texts),
        ];

        $template = PromptTemplate::query()
            ->resolve(self::TEMPLATE_KEY, $this->tenantId)
            ->first();

        $payload = $template !== null
            ? $this->askWithTemplate($llm, $renderer, $template, $vars, $codeSchema, $llmContext)
            : null;

        $payload ??= $llm->emit(
            self::SYSTEM_PROMPT,
            $this->fallbackPrompt($vars),
            $codeSchema,
            $llmContext,
            self::TEMPLATE_KEY,
        );

        $questions = $payload['questions'] ?? [];

        return is_array($questions) ? array_slice($questions, 0, $maxQuestions) : [];
    }

    /**
     * Der Aufruf ueber die Vorlage. Gibt null zurueck, wenn die Vorlage sich
     * nicht rendern laesst — dann uebernimmt der Ersatztext.
     *
     * @param  array<string, string>  $vars
     * @param  array<string, mixed>  $codeSchema
     * @return array<string, mixed>|null
     */
    private function askWithTemplate(
        LlmClient $llm,
        PromptRenderer $renderer,
        PromptTemplate $template,
        array $vars,
        array $codeSchema,
        LlmContext $llmContext,
    ): ?array {
        try {
            $renderer->renderTemplate($template, $vars);
        } catch (\InvalidArgumentException $exception) {
            Log::warning('Lead-Template nicht renderbar, Ersatztext verwendet.', [
                'tenant_id' => $this->tenantId,
                'template_key' => $template->key,
                'template_version' => $template->version,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return $llm->structured(
            $template,
            $vars,
            $this->schemaFor($template, $codeSchema),
            $llmContext,
        );
    }

    /**
     * Das Schema fuer den Aufruf: das der Vorlage, wenn es alle Pflichtfelder
     * des Codes traegt, sonst das aus dem Code.
     *
     * Das Clustering haelt nie an, weil ein Schema nicht passt — dieselbe
     * Regel wie in der Themenfindung (design/content-dashboard.md §7b).
     *
     * @param  array<string, mixed>  $codeSchema
     * @return array<string, mixed>
     */
    private function schemaFor(PromptTemplate $template, array $codeSchema): array
    {
        /** @var array<string, mixed> $schema */
        $schema = is_array($template->output_schema_json) ? $template->output_schema_json : [];

        $contract = PromptSchemaContract::for(self::TEMPLATE_KEY);
        $missing = $contract?->missingFields($schema === [] ? null : $schema) ?? [];

        if ($schema !== [] && $missing === []) {
            return $schema;
        }

        Log::warning('Lead-Vorlage mit unpassendem Ausgabeschema, Schema aus dem Code verwendet.', [
            'tenant_id' => $this->tenantId,
            'template_key' => $template->key,
            'template_version' => $template->version,
            'missing_fields' => $missing,
        ]);

        return $codeSchema;
    }

    /**
     * Eingebauter Ersatztext, wenn keine Vorlage greift. Traegt dieselben
     * Bloecke wie Version 2 der Vorlage.
     *
     * @param  array<string, string>  $vars
     */
    private function fallbackPrompt(array $vars): string
    {
        return "Branche: {$vars['branch']}\n\n"
            ."Bilde aus den folgenden Eingaben hoechstens {$vars['max_questions']} Fragethemen. "
            ."Fasse inhaltlich Gleiches zusammen und ordne die Themen nach Haeufigkeit.\n\n"
            .'Eingaben:'."\n".$vars['inputs'];
    }

    /**
     * Der Ausgabevertrag des Lead-Clusterings. Oeffentlich und statisch, weil
     * der Seeder ihn woertlich in die Vorlage uebernimmt (#75) und der
     * Prompt-Editor die Herkunft anzeigt.
     *
     * @return array<string, mixed>
     */
    public static function outputSchema(int $maxQuestions = 10): array
    {
        return [
            'type' => 'object',
            'required' => ['questions'],
            'additionalProperties' => false,
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'maxItems' => $maxQuestions,
                    'items' => [
                        'type' => 'object',
                        'required' => self::REQUIRED_QUESTION_FIELDS,
                        'additionalProperties' => false,
                        'properties' => [
                            'question' => ['type' => 'string', 'description' => 'Die Frage in der Sprache der Nutzer.'],
                            // Nicht Pflicht: store() nimmt das Feld, wenn es
                            // da ist, und schreibt sonst keine Keywords.
                            'keyword' => ['type' => 'string', 'description' => 'Hauptsuchbegriff des Themas.'],
                            'share' => ['type' => 'number', 'description' => 'Anteil der Eingaben zu diesem Thema, 0 bis 1.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function store(array $questions, string $branch, int $textCount): void
    {
        $fetchedAt = CarbonImmutable::now();
        $stored = 0;

        foreach ($questions as $entry) {
            $question = trim((string) ($entry['question'] ?? ''));

            if ($question === '') {
                continue;
            }

            $keyword = trim((string) ($entry['keyword'] ?? ''));
            $share = max(0.0, min(1.0, (float) ($entry['share'] ?? 0.0)));

            $dto = new SourceItemDto(
                type: 'lead_question',
                title: $question,
                url: null,
                snippet: "Aus {$textCount} anonymisierten Nutzereingaben der letzten Wochen geclustert.",
                regionScope: 'national',
                regionCode: null,
                keywords: array_filter([$keyword]),
                signalStrength: $share,
                publishedAt: $fetchedAt,
                raw: ['branch' => $branch, 'share' => $share, 'text_count' => $textCount],
                externalId: 'lead:'.mb_substr(hash('sha256', mb_strtolower($question)), 0, 32),
                // Ein Fragethema je Kalenderwoche: die Liste wird woechentlich
                // neu gebildet, soll sich aber nicht taeglich vervielfachen.
                fingerprintSeed: 'lead|'.mb_strtolower($question).'|'.$fetchedAt->format('o-W'),
            );

            $record = SourceItem::query()->firstOrNew([
                'source_key' => self::SOURCE_KEY,
                'fingerprint' => $dto->fingerprint(),
            ]);

            $record->fill($dto->toAttributes(self::SOURCE_KEY, $fetchedAt))->save();
            $stored++;
        }

        Log::info('Lead-Fragen geclustert.', [
            'tenant_id' => $this->tenantId,
            'branch' => $branch,
            'texts' => $textCount,
            'questions' => $stored,
        ]);
    }
}
