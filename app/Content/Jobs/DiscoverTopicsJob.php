<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\TopicStatus;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Llm\LlmClient;
use App\Content\Llm\LlmContext;
use App\Content\Llm\PromptRenderer;
use App\Content\Llm\PromptSchemaContract;
use App\Content\Models\Central\PromptTemplate;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\NavigationalTopicDetector;
use App\Content\Services\RegionScopeResolver;
use App\Content\Services\YmylGuard;
use App\Content\Sources\TenantContext;
use App\Content\Support\BranchResolver;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Themenfindung je Mandant (#12), taeglich vor dem Scoring.
 *
 * Fasst die Rohsignale der letzten sieben Tage (#7-#11) mit einem einzigen
 * Modellaufruf zu hoechstens 30 Themenkandidaten zusammen. Das Modell
 * gruppiert und formuliert, es entscheidet nichts:
 *
 *   - Jeder Kandidat muss auf mindestens ein reales Rohsignal zeigen;
 *     erfundene source_item_ids werden verworfen (kein Thema ohne Anlass).
 *   - Ueber den Regionszuschnitt entscheidet nicht der Vorschlag des Modells,
 *     sondern der RegionScopeResolver anhand belegbarer regionaler Faktoren.
 *   - Die YMYL-Schranken des Mandanten greifen vor dem Speichern.
 *
 * Idempotent: ein zweiter Lauf am selben Tag aktualisiert die noch offenen
 * Kandidaten (discovered/scored) desselben Themas, laesst aber alles in Ruhe,
 * was bereits ausgewaehlt, in Reserve oder abgelehnt ist. Am Ende wird das
 * Scoring angestossen — das Abnahmekriterium verlangt Teilscores direkt nach
 * `content:topics:discover`.
 */
class DiscoverTopicsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public const TEMPLATE_KEY = 'topic_discover';

    /**
     * Planer-System-Prompt der Themenfindung. Bewusst nicht der
     * Redakteurstext der Schreib-Stufen: hier entsteht kein Artikel, und
     * {{styleguide}}/{{fact_snippets}} gibt es an dieser Stelle nicht.
     * Der Seeder uebernimmt diese Konstante woertlich in Version 2 der
     * Vorlage — Prompt-Editor und Ersatztext bleiben so wortgleich.
     */
    public const SYSTEM_PROMPT = 'Sie sind Themenplaner:in der Ratgeber-Redaktion eines deutschen Branchenportals. '
        .'Sie buendeln vorliegende Rohsignale zu Ratgeber-Themen. Sie erfinden keine Themen: '
        .'jedes Thema stuetzt sich auf mindestens eine der nummerierten Quellen. '
        .'Sie geben ausschliesslich deutsche Suchbegriffe zurueck, wie Ratsuchende sie eingeben.';

    /**
     * Der Vertrag mit dem Code: diese Felder liest store() namentlich aus.
     * Traegt das Schema einer Vorlage sie nicht alle, laeuft der Aufruf mit
     * dem Schema aus dem Code weiter (design/content-dashboard.md §7b).
     *
     * @var array<int, string>
     */
    public const REQUIRED_TOPIC_FIELDS = [
        'title',
        'primary_keyword',
        'secondary_keywords',
        'intent',
        'region_scope',
        'source_item_ids',
        'rationale',
    ];

    public function __construct(
        public int $tenantId,
        public bool $chainScoring = true,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.discovery', 'content-discovery'));
    }

    public function uniqueId(): string
    {
        return 'content-discover-topics:'.$this->tenantId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        LlmClient $llm,
        PromptRenderer $renderer,
        RegionScopeResolver $regions,
        YmylGuard $ymyl,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $branch = BranchResolver::resolve($tenant) ?? 'default';

        $tenant->run(function () use ($tenant, $llm, $renderer, $regions, $ymyl, $branch): void {
            $context = TenantContext::forTenant($tenant);
            $settings = $context->settings;
            $items = $this->sourceItems();

            if ($items->isEmpty()) {
                Log::info('Themenfindung uebersprungen: keine Rohsignale im Fenster.', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            try {
                $topics = $this->ask($llm, $renderer, $items, $context, $branch);
            } catch (BudgetExceededException $exception) {
                Log::warning('Themenfindung ausgesetzt: Budget erschoepft.', [
                    'tenant_id' => $this->tenantId,
                    'exception' => $exception->getMessage(),
                ]);

                return;
            } catch (LlmSchemaException $exception) {
                Log::error('Themenfindung ohne verwertbare Modellantwort.', [
                    'tenant_id' => $this->tenantId,
                    'exception' => $exception->getMessage(),
                ]);

                return;
            }

            $stored = $this->store($topics, $items, $settings, $regions, $ymyl);

            Log::info('Themenfindung abgeschlossen.', [
                'tenant_id' => $this->tenantId,
                'source_items' => $items->count(),
                'candidates' => $stored,
            ]);
        });

        if ($this->chainScoring) {
            ScoreTopicsJob::dispatch($this->tenantId);
        }
    }

    /**
     * Rohsignale des Fensters, staerkste zuerst. Mehr als
     * `max_source_items` gehen nicht in den Prompt — der Rest waere
     * Eingabekosten ohne besseren Themenvorschlag.
     *
     * @return Collection<int, SourceItem>
     */
    private function sourceItems(): Collection
    {
        $since = CarbonImmutable::now()->subDays(
            max(1, (int) config('content.topics.discover.lookback_days', 7)),
        );

        $limit = max(10, (int) config('content.topics.discover.max_source_items', 120));
        $navigational = app(NavigationalTopicDetector::class);

        // Betriebssuchen ("bauunternehmen freiburg") gehen gar nicht erst in
        // den Prompt (#42): das Modell uebernahm sie sonst fast woertlich als
        // Thema, und nach dem Scoring blieb kaum ein Kandidat uebrig. Deshalb
        // ein groesserer Vorrat, aus dem die Betriebssuchen herausfallen.
        return SourceItem::query()
            ->where('fetched_at', '>=', $since)
            ->orderByDesc('signal_strength')
            ->orderByDesc('fetched_at')
            ->limit($limit * 4)
            ->get()
            ->reject(fn (SourceItem $item): bool => $navigational->reasonFor((string) $item->title) !== null)
            ->take($limit)
            ->values();
    }

    /**
     * Ein Modellaufruf mit erzwungenem Schema.
     *
     * Der ganze Prompt steht in der Vorlage (#47): Signalliste und
     * Ausgaberegeln gehen als Variablen `signals` und `output_rules` hinein,
     * statt an den fertigen Text angehaengt zu werden. Nur so zeigt die
     * Vorschau im Prompt-Editor denselben Prompt, den der Lauf abschickt.
     * Fehlt die Vorlage oder ist sie nicht renderbar, traegt der eingebaute
     * Ersatztext den Aufruf allein.
     *
     * @param  Collection<int, SourceItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function ask(
        LlmClient $llm,
        PromptRenderer $renderer,
        Collection $items,
        TenantContext $context,
        string $branch,
    ): array {
        $maxCandidates = max(1, (int) config('content.topics.discover.max_candidates', 30));
        $branchLabel = BranchResolver::label($branch) ?? 'Handwerk und Dienstleistung';
        $codeSchema = self::outputSchema($maxCandidates);
        $llmContext = new LlmContext(tenantId: $this->tenantId, operation: self::TEMPLATE_KEY);

        $vars = [
            'tenant_name' => $context->name,
            'branch' => $branchLabel,
            'signals' => $this->signalBlock($items),
            'internal_link_targets' => $this->linkTargetBlock(),
            'max_candidates' => (string) $maxCandidates,
            'output_rules' => $this->rulesBlock($maxCandidates),
        ];

        $template = PromptTemplate::query()
            ->resolve(self::TEMPLATE_KEY, $this->tenantId)
            ->first();

        $result = $template !== null
            ? $this->askWithTemplate($llm, $renderer, $template, $vars, $codeSchema, $llmContext)
            : null;

        $result ??= $llm->emit(
            self::SYSTEM_PROMPT,
            $this->fallbackPrompt($vars),
            $codeSchema,
            $llmContext,
            self::TEMPLATE_KEY,
        );

        $topics = $result['topics'] ?? [];

        return is_array($topics) ? array_slice($topics, 0, $maxCandidates) : [];
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
            Log::warning('Themen-Template nicht renderbar, Ersatztext verwendet.', [
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
     * Die Themenfindung haelt nie an, weil ein Schema nicht passt — ein Tag
     * ohne Themen ist teurer als ein Tag mit dem Schema aus dem Code
     * (design/content-dashboard.md §7b).
     *
     * @param  array<string, mixed>  $codeSchema
     * @return array<string, mixed>
     */
    private function schemaFor(PromptTemplate $template, array $codeSchema): array
    {
        /** @var array<string, mixed> $schema */
        $schema = is_array($template->output_schema_json) ? $template->output_schema_json : [];

        // Eine Pruefstelle fuer Job und Formular (§7b.1 Abschnitt 1):
        // REQUIRED_TOPIC_FIELDS bleibt die Quelle, der Vertrag referenziert
        // sie nur.
        $contract = PromptSchemaContract::for('topic_discover');
        $missing = $contract?->missingFields($schema === [] ? null : $schema) ?? [];

        if ($schema !== [] && $missing === []) {
            return $schema;
        }

        Log::warning('Themen-Vorlage mit unpassendem Ausgabeschema, Schema aus dem Code verwendet.', [
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
        return "Portal: {$vars['tenant_name']} (Branche {$vars['branch']}).\n"
            .'Gesucht sind Ratgeber-Themen mit klarem Haupt-Keyword, erkennbarer Suchintention '
            ."und einer kurzen Begruendung, warum das Thema jetzt relevant ist.\n\n"
            ."Bereits vorhandene interne Ratgeber (nicht duplizieren):\n"
            .$vars['internal_link_targets']
            ."\n\n".$vars['signals']
            ."\n\n".$vars['output_rules'];
    }

    /**
     * Titel bereits veroeffentlichter Ratgeber des Portals — damit das Modell
     * keine Themen vorschlaegt, die es schon gibt. Ohne Ratgeber steht dort
     * 'keine' statt eines leeren Blocks.
     */
    private function linkTargetBlock(): string
    {
        $titles = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit(max(1, (int) config('content.topics.discover.max_link_targets', 40)))
            ->pluck('title')
            ->map(static fn ($title): string => trim((string) $title))
            ->filter()
            ->all();

        return $titles === []
            ? 'keine'
            : implode("\n", array_map(static fn (string $title): string => '- '.$title, $titles));
    }

    /**
     * Die nummerierte Signalliste. Die Nummer ist die source_items-ID — genau
     * die soll das Modell zurueckgeben, damit jedes Thema belegbar bleibt.
     *
     * @param  Collection<int, SourceItem>  $items
     */
    private function signalBlock(Collection $items): string
    {
        $lines = $items->map(function (SourceItem $item): string {
            $region = $item->region_scope === RegionScopeResolver::SCOPE_NATIONAL
                ? 'bundesweit'
                : "{$item->region_scope} {$item->region_code}";

            $date = $item->published_at?->format('d.m.Y') ?? $item->fetched_at?->format('d.m.Y') ?? '';

            return "[{$item->getKey()}] ({$item->source_key}, {$region}, {$date}) {$item->title}"
                .($item->summary !== null ? ' — '.Str::limit((string) $item->summary, 180) : '');
        })->all();

        return "Rohsignale der letzten Tage:\n".implode("\n", $lines);
    }

    private function rulesBlock(int $maxCandidates): string
    {
        return <<<TEXT
            Regeln fuer die Ausgabe:
            - Hoechstens {$maxCandidates} Themen, das staerkste zuerst.
            - source_item_ids enthaelt nur Nummern aus der Liste oben, mindestens eine je Thema.
            - primary_keyword ist der Suchbegriff, nicht die Ueberschrift; secondary_keywords sind Varianten und Unterfragen.
            - Fassen Sie mehrere Signale zum selben Anliegen zu EINEM Thema zusammen.
            - Jedes Thema ist eine Ratgeberfrage (Ablauf, Vorschriften, Foerderung, Auswahl, Wartung, Fehler vermeiden), keine Betriebssuche. Themen der Form "<Beruf> <Ort>", "<Beruf> in der Naehe" oder "<Beruf> Notdienst" sind verboten; die bedienen die Stadt- und Kategorieseiten des Portals.
            - region_scope ist ein Vorschlag: 'state' oder 'city' nur, wenn die Quellen selbst einen Ortsbezug tragen, sonst 'national'. region_code ist der ISO-Code des Bundeslands ('DE-BY') oder der Ortsname; bei 'national' lassen Sie das Feld weg.
            - rationale in einem Satz: warum das Thema jetzt.
            TEXT;
    }

    /**
     * Der Ausgabevertrag der Themenfindung. Oeffentlich und statisch, weil
     * der Seeder ihn woertlich in die Vorlage uebernimmt (#47) und der
     * Prompt-Editor die Herkunft anzeigt.
     *
     * @return array<string, mixed>
     */
    public static function outputSchema(int $maxCandidates = 30): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['topics'],
            'properties' => [
                'topics' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $maxCandidates,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => self::REQUIRED_TOPIC_FIELDS,
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 160],
                            'primary_keyword' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                            'secondary_keywords' => [
                                'type' => 'array',
                                'maxItems' => 10,
                                'items' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                            ],
                            'intent' => ['type' => 'string', 'enum' => ['informational', 'commercial', 'transactional']],
                            'region_scope' => ['type' => 'string', 'enum' => ['national', 'state', 'city']],
                            // Nicht Pflicht und ohne null-Variante: ein
                            // bundesweites Thema laesst das Feld weg. Ein
                            // Nulltyp im Werkzeugschema fuehrt bei einzelnen
                            // Modellversionen zu Schema-Retries ohne Nutzen.
                            'region_code' => ['type' => 'string', 'maxLength' => 64],
                            'source_item_ids' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 12,
                                'items' => ['type' => 'integer'],
                            ],
                            'rationale' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 400],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $topics
     * @param  Collection<int, SourceItem>  $items
     */
    private function store(
        array $topics,
        Collection $items,
        TenantContentSetting $settings,
        RegionScopeResolver $regions,
        YmylGuard $ymyl,
    ): int {
        $known = $items->keyBy('id');
        $requireSources = (bool) config('content.topics.discover.require_source_items', true);
        $stored = 0;

        foreach ($topics as $topic) {
            if (! is_array($topic)) {
                continue;
            }

            $keyword = trim((string) ($topic['primary_keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            $sourceIds = array_values(array_filter(
                array_map('intval', (array) ($topic['source_item_ids'] ?? [])),
                static fn (int $id): bool => $known->has($id),
            ));

            if ($sourceIds === [] && $requireSources) {
                Log::info('Themenkandidat ohne belegbares Rohsignal verworfen.', [
                    'tenant_id' => $this->tenantId,
                    'keyword' => $keyword,
                ]);

                continue;
            }

            $secondary = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                (array) ($topic['secondary_keywords'] ?? []),
            )));

            $text = $keyword.' '.implode(' ', $secondary).' '.(string) ($topic['title'] ?? '');
            $verdict = $ymyl->evaluate($text, (bool) $settings->is_ymyl);

            $candidate = $this->candidateFor($keyword);

            if ($candidate === null) {
                continue;
            }

            if ($verdict['outcome'] === YmylGuard::OUTCOME_BLOCKED) {
                $candidate->forceFill([
                    'title' => Str::limit((string) ($topic['title'] ?? $keyword), 255, ''),
                    'primary_keyword' => $keyword,
                    'status' => TopicStatus::REJECTED,
                    'rejection_reason' => $verdict['reason'],
                ])->save();

                continue;
            }

            $sourceItems = $known->only($sourceIds)->values();

            $region = $regions->decide(
                $text,
                $sourceItems,
                $settings,
                isset($topic['region_scope']) ? (string) $topic['region_scope'] : null,
                isset($topic['region_code']) && $topic['region_code'] !== null ? (string) $topic['region_code'] : null,
            );

            $informationalOnly = $verdict['outcome'] === YmylGuard::OUTCOME_INFORMATIONAL;

            $candidate->forceFill([
                'title' => Str::limit((string) ($topic['title'] ?? $keyword), 255, ''),
                'primary_keyword' => $keyword,
                'secondary_keywords_json' => $secondary,
                'source_item_ids_json' => $sourceIds,
                'intent' => $informationalOnly ? 'informational' : (string) ($topic['intent'] ?? 'informational'),
                'informational_only' => $informationalOnly,
                'region_scope' => $region['scope'],
                'region_code' => $region['code'],
                'region_reason' => Str::limit($region['reason'], 500, ''),
                'region_evidence_json' => $region['evidence'],
                'rationale' => trim(
                    (string) ($topic['rationale'] ?? '')
                    .($verdict['reason'] !== null ? ' '.$verdict['reason'] : ''),
                ),
                'status' => TopicStatus::DISCOVERED,
                'rejection_reason' => null,
            ])->save();

            $stored++;
        }

        return $stored;
    }

    /**
     * Der Kandidat zu einem Keyword: ein noch offener wird fortgeschrieben,
     * sonst entsteht ein neuer. Bereits ausgewaehlte, reservierte oder
     * abgelehnte Kandidaten bleiben unangetastet — dort haengen Entwuerfe und
     * redaktionelle Entscheidungen dran.
     */
    private function candidateFor(string $keyword): ?TopicCandidate
    {
        $open = TopicCandidate::query()
            ->where('primary_keyword', $keyword)
            ->whereIn('status', [TopicStatus::DISCOVERED->value, TopicStatus::SCORED->value])
            ->orderByDesc('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $blocked = TopicCandidate::query()
            ->where('primary_keyword', $keyword)
            ->whereIn('status', [TopicStatus::SELECTED->value, TopicStatus::RESERVE->value])
            ->exists();

        if ($blocked) {
            return null;
        }

        $recentlyRejected = TopicCandidate::query()
            ->where('primary_keyword', $keyword)
            ->where('status', TopicStatus::REJECTED->value)
            ->where('updated_at', '>=', CarbonImmutable::now()->subDays(
                max(1, (int) config('content.topics.selection.cooldown_days', 90)),
            ))
            ->exists();

        return $recentlyRejected ? null : new TopicCandidate;
    }
}
