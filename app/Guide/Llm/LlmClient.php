<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\Exceptions\CliStructuredOutputException;
use App\Guide\Llm\Exceptions\LlmSchemaException;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\Central\PromptTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * LLM-Zugang des Ratgebersystems (#5, docs/guide-system.md §6).
 *
 * Zwei Treiber (config('guide.driver'), #42): 'cli' ruft die Claude-CLI mit
 * dem Claude-Abo auf (ClaudeCliTransport, siehe cliStructured()/cliResearch()),
 * 'api' die Anthropic Messages API. Die Aufrufer merken davon nichts; Budget,
 * Kontowaechter und Kosten-Log gelten fuer beide, das Log traegt den Treiber.
 *
 * Treiber 'api':
 *
 * structured(): schemakonforme Ausgabe ueber erzwungenen Tool-Use. Die Anfrage
 * definiert das Werkzeug 'emit' mit dem Schema als input_schema und setzt
 * tool_choice darauf; die Nutzdaten stehen in content[].input und werden vom
 * SchemaValidator noch einmal geprueft. Verstoesse gehen als tool_result mit
 * is_error in den naechsten Versuch.
 *
 * research(): derselbe Weg mit dem serverseitigen Web-Search-Werkzeug. Der
 * erste Turn laeuft mit tool_choice auto, das Modell sucht frei (max_uses).
 * Endet er mit pause_turn, wird die Anfrage mit der Teilantwort fortgesetzt.
 * Liefert das Modell danach nicht selbst ueber 'emit', wird 'emit' im selben
 * Verlauf erzwungen. Beide Werkzeuge stehen in jedem Turn in tools[], damit
 * der Cache-Praefix (tools, system) gleich bleibt.
 *
 * System-Prompt und Styleguide tragen cache_control (PromptRenderer).
 * Modell, max_tokens, Preise und Werkzeugtyp stehen in config/guide.php.
 * Jede HTTP-Anfrage ist eine Zeile in llm_usage_logs, auch bei Fehlern.
 */
class LlmClient
{
    public const PROVIDER = 'anthropic';

    public const DRIVER_API = 'api';

    public const DRIVER_CLI = ClaudeCliTransport::DRIVER;

    public function __construct(
        private readonly BudgetGuard $budget,
        private readonly PromptRenderer $renderer,
        private readonly SchemaValidator $validator,
        private readonly ClaudeCliTransport $cli,
    ) {}

    /**
     * Eingestellter Treiber; alles ausser 'api' gilt als 'cli'.
     */
    public static function driver(): string
    {
        return config('guide.driver') === self::DRIVER_API ? self::DRIVER_API : self::DRIVER_CLI;
    }

    /**
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $schema  leer = output_schema_json des Templates
     * @return array<string, mixed>
     *
     * @throws LlmSchemaException nach 1 + guide.anthropic.schema_retries Versuchen
     * @throws BudgetExceededException
     */
    public function structured(
        PromptTemplate $template,
        array $vars,
        array $schema,
        ?LlmCallContext $context = null,
    ): array {
        $schema = $this->schemaFor($template, $schema);
        $context ??= LlmCallContext::current();

        if (self::driver() === self::DRIVER_CLI) {
            return $this->cliStructured($template, $vars, $schema, $context);
        }

        $prompts = $this->renderer->render($template, $vars, $this->cacheEnabled());

        $request = $this->request(
            $prompts['system'],
            [['role' => 'user', 'content' => $prompts['user']]],
            [$this->emitTool($schema)],
        );

        return $this->emitLoop($request, $schema, $context, (string) $template->key, new UsageTotals);
    }

    /**
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $schema  leer = output_schema_json des Templates
     * @param  array<int, string>  $allowedDomains  nur diese Domains durchsuchen
     * @param  array<int, string>  $blockedDomains  diese Domains nie durchsuchen
     *
     * @throws LlmSchemaException
     * @throws BudgetExceededException
     */
    public function research(
        PromptTemplate $template,
        array $vars,
        array $schema,
        int $maxSearches,
        array $allowedDomains = [],
        array $blockedDomains = [],
        ?LlmCallContext $context = null,
    ): ResearchResult {
        $schema = $this->schemaFor($template, $schema);
        $context ??= LlmCallContext::current();

        if (self::driver() === self::DRIVER_CLI) {
            return $this->cliResearch($template, $vars, $schema, $maxSearches, $allowedDomains, $blockedDomains, $context);
        }

        $templateKey = (string) $template->key;
        $prompts = $this->renderer->render($template, $vars, $this->cacheEnabled());
        $totals = new UsageTotals;

        $request = $this->request(
            $prompts['system'],
            [['role' => 'user', 'content' => $prompts['user']]],
            [$this->webSearchTool($maxSearches, $allowedDomains, $blockedDomains), $this->emitTool($schema)],
        );
        $request['tool_choice'] = ['type' => 'auto'];

        $blocks = [];
        $response = $this->send($request, $context, $templateKey, $totals);
        $blocks = array_merge($blocks, $this->content($response));
        $continuations = 0;

        while (($response['stop_reason'] ?? null) === 'pause_turn') {
            if ($continuations >= (int) $this->option('max_continuations', 5)) {
                throw new RuntimeException(
                    "Recherche '{$templateKey}' nach {$continuations} Fortsetzungen (pause_turn) nicht abgeschlossen.",
                );
            }

            // Fortsetzen: Teilantwort unveraendert zurueckgeben, kein neuer User-Turn.
            $request['messages'][] = ['role' => 'assistant', 'content' => $this->content($response)];
            $response = $this->send($request, $context, $templateKey, $totals);
            $blocks = array_merge($blocks, $this->content($response));
            $continuations++;
        }

        if ($this->emitBlock($response) === null) {
            $content = $this->content($response);

            if ($content !== []) {
                $request['messages'][] = ['role' => 'assistant', 'content' => $content];
            }

            $request['messages'][] = ['role' => 'user', 'content' => 'Die Recherche ist abgeschlossen. '
                ."Gib das Ergebnis jetzt ausschliesslich ueber das Werkzeug '{$this->toolName()}' zurueck. "
                .'Stuetze dich nur auf die gefundenen Quellen.'];
            $response = null;
        }

        $data = $this->emitLoop($request, $schema, $context, $templateKey, $totals, $response);

        [$citations, $searchErrors] = $this->citations($blocks);

        if ($searchErrors !== []) {
            Log::warning('Web-Search meldet Fehler.', [
                'template' => $templateKey,
                'tenant_id' => $context->tenantId,
                'errors' => $searchErrors,
            ]);
        }

        return new ResearchResult(
            data: $data,
            citations: $citations,
            searchCount: $totals->searches,
            costUsd: $totals->costUsd,
            inputTokens: $totals->inputTokens,
            outputTokens: $totals->outputTokens,
            requests: $totals->requests,
            searchErrors: $searchErrors,
        );
    }

    /**
     * structured() ueber die Claude-CLI. Kein Nachrichtenverlauf: bei einem
     * Schemaverstoss stehen vorige Ausgabe und Verstoesse als Text im
     * naechsten Prompt. Meldet die CLI selbst keine schemakonforme Ausgabe
     * (CliStructuredOutputException), zaehlt das als Versuch.
     *
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function cliStructured(PromptTemplate $template, array $vars, array $schema, LlmCallContext $context): array
    {
        $templateKey = (string) $template->key;
        $prompts = $this->renderer->render($template, $vars, false);
        $system = $this->systemText($prompts['system']);

        return $this->cliEmitLoop($system, $prompts['user'], $schema, $context, $templateKey, new UsageTotals, false);
    }

    /**
     * research() ueber die Claude-CLI mit deren Websuche.
     *
     * Die Suchgrenze und die Domainlisten stehen im Prompt, weil die CLI
     * dafuer keine Optionen hat; Treffer ausserhalb der Domainlisten fallen
     * zusaetzlich aus den Zitaten. Zitate sind die Treffer der tatsaechlich
     * ausgefuehrten Suchen (stream-json) plus abgerufene Seiten; 'cited'
     * heisst: die URL steht in der Ausgabe. Damit bleibt die Pruefung
     * not_in_search_results im ResearchService wirksam.
     *
     * Schemaverstoesse werden ohne neue Suche korrigiert: der Folgeaufruf
     * bekommt die vorige Ausgabe und die gefundenen Treffer.
     *
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $allowedDomains
     * @param  array<int, string>  $blockedDomains
     */
    private function cliResearch(
        PromptTemplate $template,
        array $vars,
        array $schema,
        int $maxSearches,
        array $allowedDomains,
        array $blockedDomains,
        LlmCallContext $context,
    ): ResearchResult {
        if ($maxSearches < 1) {
            throw new InvalidArgumentException('research() braucht mindestens eine erlaubte Suche.');
        }

        $allowedDomains = $this->domains($allowedDomains);
        $blockedDomains = $this->domains($blockedDomains);

        if ($allowedDomains !== [] && $blockedDomains !== []) {
            throw new InvalidArgumentException('allowed_domains und blocked_domains schliessen sich aus.');
        }

        $templateKey = (string) $template->key;
        $prompts = $this->renderer->render($template, $vars, false);
        $system = $this->systemText($prompts['system']);
        $user = $prompts['user']."\n\n".$this->researchRules($maxSearches, $allowedDomains, $blockedDomains);
        $totals = new UsageTotals;
        $found = [];

        $data = $this->cliEmitLoop($system, $user, $schema, $context, $templateKey, $totals, true, $found);

        $citations = [];
        $encoded = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach ($found as $url => $title) {
            if (! $this->domainAllowed($url, $allowedDomains, $blockedDomains)) {
                continue;
            }

            $citations[] = [
                'url' => $url,
                'title' => $title,
                'page_age' => null,
                'cited' => str_contains($encoded, $url),
            ];
        }

        $searchErrors = [];

        if ($totals->searches === 0) {
            $searchErrors[] = 'cli_no_search';
        }

        if ($totals->searches > $maxSearches) {
            Log::warning('Claude-CLI hat mehr Websuchen gemacht als erlaubt.', [
                'template' => $templateKey,
                'tenant_id' => $context->tenantId,
                'searches' => $totals->searches,
                'max' => $maxSearches,
            ]);
        }

        return new ResearchResult(
            data: $data,
            citations: $citations,
            searchCount: $totals->searches,
            costUsd: $totals->costUsd,
            inputTokens: $totals->inputTokens,
            outputTokens: $totals->outputTokens,
            requests: $totals->requests,
            searchErrors: $searchErrors,
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $found  url => Titel, wird fortgeschrieben
     * @return array<string, mixed>
     */
    private function cliEmitLoop(
        string $system,
        string $user,
        array $schema,
        LlmCallContext $context,
        string $templateKey,
        UsageTotals $totals,
        bool $webTools,
        array &$found = [],
    ): array {
        $maxAttempts = 1 + max(0, (int) $this->option('schema_retries', 2));
        $prompt = $user;
        $errors = [];
        $searched = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Gesucht wird nur, bis eine Recherche mit Treffern vorliegt;
            // Korrekturen danach laufen ohne Werkzeuge.
            $withTools = $webTools && ! $searched;
            $response = $this->cliSend($system, $prompt, $schema, $withTools, $context, $templateKey, $totals);

            if ($response === null) {
                $errors = ['Die Ausgabe passte nicht zum verlangten Format.'];
                $prompt = $this->cliRetryPrompt($user, null, $errors, $found);

                continue;
            }

            foreach ($response->searchResults as $hit) {
                $found[$hit['url']] ??= $hit['title'];
            }

            foreach ($response->fetchedUrls as $url) {
                $found[$url] ??= '';
            }

            $searched = $searched || $response->searches > 0 || $found !== [];

            $errors = $response->output === null
                ? ['Antwort enthaelt keine strukturierte Ausgabe.']
                : $this->validator->validate($response->output, $schema);

            if ($errors === []) {
                return (array) $response->output;
            }

            Log::warning('Schemawidrige Modellantwort (Guide, CLI), neuer Versuch.', [
                'template' => $templateKey,
                'attempt' => $attempt,
                'errors' => $errors,
            ]);

            $prompt = $this->cliRetryPrompt($user, $response->output, $errors, $webTools ? $found : []);
        }

        throw LlmSchemaException::afterRetries($templateKey, $maxAttempts, $errors);
    }

    /**
     * Ein CLI-Aufruf mit Kontowaechter, Budgetpruefung und Kostenbuchung.
     * null = die CLI meldete keine schemakonforme Ausgabe.
     *
     * @param  array<string, mixed>  $schema
     */
    private function cliSend(
        string $system,
        string $prompt,
        array $schema,
        bool $webTools,
        LlmCallContext $context,
        string $templateKey,
        UsageTotals $totals,
    ): ?CliResponse {
        ProviderAccountGuard::assertUsable(self::PROVIDER);
        $this->budget->check($context);

        $startedAt = microtime(true);

        try {
            $response = $this->cli->run($system, $prompt, $schema, $webTools);
        } catch (CliStructuredOutputException $exception) {
            $this->log($context, $templateKey, [], $this->elapsed($startedAt), $exception->getMessage(), self::DRIVER_CLI);

            Log::warning('Claude-CLI ohne schemakonforme Ausgabe, neuer Versuch.', [
                'template' => $templateKey,
                'error' => $exception->getMessage(),
            ]);

            return null;
        } catch (\Throwable $exception) {
            $this->log($context, $templateKey, [], $this->elapsed($startedAt), $exception->getMessage(), self::DRIVER_CLI);

            throw $exception;
        }

        $cost = $this->log(
            $context,
            $templateKey,
            $response->usage,
            $this->elapsed($startedAt),
            null,
            self::DRIVER_CLI,
            $response->costUsd,
            $response->searches,
        );

        $totals->add($response->inputTokens(), $response->outputTokens(), $response->searches, $cost);

        ProviderAccountGuard::reportSuccess(self::PROVIDER);

        return $response;
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<int, string>  $errors
     * @param  array<string, string>  $found
     */
    private function cliRetryPrompt(string $user, ?array $previous, array $errors, array $found): string
    {
        $prompt = $user;

        if ($found !== []) {
            $lines = [];

            foreach ($found as $url => $title) {
                $lines[] = "- {$url}".($title !== '' ? " ({$title})" : '');
            }

            $prompt .= "\n\n---\nDie Recherche ist abgeschlossen, suche nicht erneut. Gefundene Quellen:\n"
                .implode("\n", $lines)
                ."\nStuetze dich nur auf diese Quellen.";
        }

        if ($previous !== null) {
            $prompt .= "\n\n---\nDeine vorige Ausgabe:\n"
                .json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $prompt
            ."\n\nDie Ausgabe verletzt das Schema:\n- ".implode("\n- ", $errors)
            ."\nBehebe genau diese Punkte. Aendere nichts Inhaltliches, was nicht beanstandet wurde.";
    }

    /**
     * @param  array<int, string>  $allowedDomains
     * @param  array<int, string>  $blockedDomains
     */
    private function researchRules(int $maxSearches, array $allowedDomains, array $blockedDomains): string
    {
        $rules = [
            "Recherche: Nutze die Websuche hoechstens {$maxSearches} Mal. Rufe Seiten nur ab, wenn ein Suchtreffer nicht genuegt.",
            'Jede Aussage braucht eine Quelle aus deinen Suchergebnissen; gib deren URL unveraendert an. Erfinde keine URL.',
        ];

        if ($allowedDomains !== []) {
            $rules[] = 'Verwende ausschliesslich Quellen von diesen Domains: '.implode(', ', $allowedDomains).'.';
        }

        if ($blockedDomains !== []) {
            $rules[] = 'Verwende nie Quellen von diesen Domains: '.implode(', ', $blockedDomains).'.';
        }

        return implode("\n", $rules);
    }

    /**
     * @param  array<int, string>  $allowedDomains
     * @param  array<int, string>  $blockedDomains
     */
    private function domainAllowed(string $url, array $allowedDomains, array $blockedDomains): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $matches = static fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain);

        if ($allowedDomains !== [] && array_filter($allowedDomains, $matches) === []) {
            return false;
        }

        return array_filter($blockedDomains, $matches) === [];
    }

    /**
     * System-Bloecke des PromptRenderers als ein Text fuer --system-prompt.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function systemText(array $blocks): string
    {
        return implode("\n\n", array_filter(array_map(
            static fn (array $block): string => trim((string) ($block['text'] ?? '')),
            $blocks,
        )));
    }

    /**
     * Kosten einer Antwort aus usage und config('guide.pricing').
     *
     * @param  array<string, mixed>  $usage
     */
    public function costFor(array $usage): float
    {
        $pricing = $this->modelPricing();
        $cacheWrite = (array) ($usage['cache_creation'] ?? []);
        $write1h = (int) ($cacheWrite['ephemeral_1h_input_tokens'] ?? 0);
        $write5m = (int) ($cacheWrite['ephemeral_5m_input_tokens'] ?? max(0, (int) ($usage['cache_creation_input_tokens'] ?? 0) - $write1h));

        $tokens = ((int) ($usage['input_tokens'] ?? 0)) * (float) ($pricing['input_per_mtok'] ?? 0)
            + ((int) ($usage['output_tokens'] ?? 0)) * (float) ($pricing['output_per_mtok'] ?? 0)
            + $write5m * (float) ($pricing['cache_write_5m_per_mtok'] ?? 0)
            + $write1h * (float) ($pricing['cache_write_1h_per_mtok'] ?? 0)
            + ((int) ($usage['cache_read_input_tokens'] ?? 0)) * (float) ($pricing['cache_read_per_mtok'] ?? 0);

        $tools = (array) ($usage['server_tool_use'] ?? []);

        $requests = ((int) ($tools['web_search_requests'] ?? 0)) * (float) config('guide.pricing.web_search_per_1000', 0)
            + ((int) ($tools['web_fetch_requests'] ?? 0)) * (float) config('guide.pricing.web_fetch_per_1000', 0);

        return round($tokens / 1_000_000 + $requests / 1_000, 6);
    }

    /**
     * Erzwingt 'emit' und prueft die Ausgabe; $response ist eine bereits
     * vorliegende Antwort, die schon einen emit-Block enthalten kann.
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>
     */
    private function emitLoop(
        array $request,
        array $schema,
        LlmCallContext $context,
        string $templateKey,
        UsageTotals $totals,
        ?array $response = null,
    ): array {
        $request['tool_choice'] = ['type' => 'tool', 'name' => $this->toolName()];
        $maxAttempts = 1 + max(0, (int) $this->option('schema_retries', 2));
        $errors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response ??= $this->send($request, $context, $templateKey, $totals);
            $block = $this->emitBlock($response);

            $errors = $block === null
                ? ["Antwort enthaelt keinen Aufruf des Werkzeugs '{$this->toolName()}' (stop_reason ".($response['stop_reason'] ?? '?').').']
                : $this->validator->validate(is_array($block['input'] ?? null) ? $block['input'] : [], $schema);

            if ($block !== null && $errors === []) {
                return (array) $block['input'];
            }

            Log::warning('Schemawidrige Modellantwort (Guide), neuer Versuch.', [
                'template' => $templateKey,
                'attempt' => $attempt,
                'errors' => $errors,
            ]);

            $request['messages'] = $this->retryMessages($request['messages'], $response, $block, $errors);
            $response = null;
        }

        throw LlmSchemaException::afterRetries($templateKey, $maxAttempts, $errors);
    }

    /**
     * Eine HTTP-Anfrage mit Budgetpruefung und Kostenbuchung.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function send(array $request, LlmCallContext $context, string $templateKey, UsageTotals $totals): array
    {
        // Toter Zugang (Guthaben, Schluessel): kein weiterer Aufruf, bis der
        // Recheck-Abstand vorbei ist.
        ProviderAccountGuard::assertUsable(self::PROVIDER);
        $this->budget->check($context);

        $startedAt = microtime(true);

        try {
            $response = $this->http()->post('/messages', $request);
        } catch (\Throwable $exception) {
            $this->log($context, $templateKey, [], $this->elapsed($startedAt), $exception->getMessage());

            throw $exception;
        }

        $durationMs = $this->elapsed($startedAt);

        if ($response->failed()) {
            $message = $this->errorMessage($response);
            $this->log($context, $templateKey, [], $durationMs, $message);

            $reason = ProviderAccountGuard::classify($response);

            if ($reason !== null) {
                throw ProviderAccountGuard::reportFailure(self::PROVIDER, $reason, $message);
            }

            $response->throw();
        }

        $body = (array) $response->json();
        $usage = (array) ($body['usage'] ?? []);

        $cost = $this->log($context, $templateKey, $usage, $durationMs);
        $totals->add(
            (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            $this->searchCount($usage),
            $cost,
        );

        ProviderAccountGuard::reportSuccess(self::PROVIDER);

        return $body;
    }

    /**
     * @param  array<int, array<string, mixed>>  $system
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function request(array $system, array $messages, array $tools): array
    {
        $request = [
            'model' => $this->model(),
            'max_tokens' => (int) $this->option('max_tokens', 16000),
            'tools' => $tools,
            'messages' => $messages,
        ];

        if ($system !== []) {
            $request['system'] = $system;
        }

        $effort = $this->option('effort');

        if (is_string($effort) && $effort !== '') {
            $request['output_config'] = ['effort' => $effort];
        }

        $thinking = $this->option('thinking');

        if (is_string($thinking) && $thinking !== '') {
            $request['thinking'] = ['type' => $thinking];
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function emitTool(array $schema): array
    {
        return [
            'name' => $this->toolName(),
            'description' => 'Gib das Ergebnis ausschliesslich ueber dieses Werkzeug zurueck. '
                .'Alle Pflichtfelder muessen gefuellt sein.',
            'input_schema' => $schema,
        ];
    }

    /**
     * @param  array<int, string>  $allowedDomains
     * @param  array<int, string>  $blockedDomains
     * @return array<string, mixed>
     */
    private function webSearchTool(int $maxSearches, array $allowedDomains, array $blockedDomains): array
    {
        if ($maxSearches < 1) {
            throw new InvalidArgumentException('research() braucht mindestens eine erlaubte Suche.');
        }

        $allowedDomains = $this->domains($allowedDomains);
        $blockedDomains = $this->domains($blockedDomains);

        if ($allowedDomains !== [] && $blockedDomains !== []) {
            throw new InvalidArgumentException('allowed_domains und blocked_domains schliessen sich aus.');
        }

        $tool = [
            'type' => (string) config('guide.web_search.tool_type'),
            'name' => (string) config('guide.web_search.tool_name', 'web_search'),
            'max_uses' => $maxSearches,
        ];

        if ($allowedDomains !== []) {
            $tool['allowed_domains'] = $allowedDomains;
        }

        if ($blockedDomains !== []) {
            $tool['blocked_domains'] = $blockedDomains;
        }

        $location = (array) config('guide.web_search.user_location', []);

        if ($location !== []) {
            $tool['user_location'] = $location;
        }

        return $tool;
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private function domains(array $domains): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($domain) => strtolower(trim((string) $domain)),
            $domains,
        ))));
    }

    /**
     * Quellen aus den web_search_tool_result-Bloecken, je URL einmal.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{0: array<int, array{url: string, title: string, page_age: ?string, cited: bool}>, 1: array<int, string>}
     */
    private function citations(array $blocks): array
    {
        $sources = [];
        $cited = [];
        $errors = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text') {
                foreach ((array) ($block['citations'] ?? []) as $citation) {
                    if (is_array($citation) && isset($citation['url'])) {
                        $cited[(string) $citation['url']] = true;
                    }
                }

                continue;
            }

            if ($type !== 'web_search_tool_result') {
                continue;
            }

            $content = $block['content'] ?? null;

            // Fehler kommen mit HTTP 200 als Objekt statt als Trefferliste.
            if (! is_array($content) || ! array_is_list($content)) {
                $errors[] = is_array($content) ? (string) ($content['error_code'] ?? 'unknown') : 'unknown';

                continue;
            }

            foreach ($content as $result) {
                if (! is_array($result) || ($result['type'] ?? null) !== 'web_search_result' || empty($result['url'])) {
                    continue;
                }

                $url = (string) $result['url'];

                $sources[$url] ??= [
                    'url' => $url,
                    'title' => (string) ($result['title'] ?? ''),
                    'page_age' => isset($result['page_age']) ? (string) $result['page_age'] : null,
                    'cited' => false,
                ];
            }
        }

        foreach (array_keys($cited) as $url) {
            if (isset($sources[$url])) {
                $sources[$url]['cited'] = true;
            }
        }

        return [array_values($sources), $errors];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function emitBlock(array $response): ?array
    {
        foreach ($this->content($response) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $this->toolName()) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Inhaltsbloecke einer Antwort, bereit zum Zuruecksenden. Ein leeres
     * input muss als JSON-Objekt rausgehen, nicht als Liste.
     *
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    private function content(array $response): array
    {
        $blocks = [];

        foreach ((array) ($response['content'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (array_key_exists('input', $block) && $block['input'] === []) {
                $block['input'] = new \stdClass;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Die Antwort bleibt im Verlauf, damit das Modell nur die Verstoesse
     * korrigiert.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>|null  $block
     * @param  array<int, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function retryMessages(array $messages, array $response, ?array $block, array $errors): array
    {
        $content = $this->content($response);

        if ($content !== []) {
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }

        $hint = "Die Ausgabe verletzt das Schema:\n- ".implode("\n- ", $errors)
            ."\nGib das Ergebnis erneut ueber das Werkzeug '{$this->toolName()}' zurueck und behebe genau diese Punkte. "
            .'Aendere nichts Inhaltliches, was nicht beanstandet wurde.';

        $messages[] = $block === null || $content === []
            ? ['role' => 'user', 'content' => $hint]
            : ['role' => 'user', 'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => (string) ($block['id'] ?? ''),
                'is_error' => true,
                'content' => $hint,
            ]]];

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return float gebuchte Kosten
     */
    private function log(
        LlmCallContext $context,
        string $templateKey,
        array $usage,
        int $durationMs,
        ?string $error = null,
        string $driver = self::DRIVER_API,
        ?float $costUsd = null,
        ?int $searches = null,
    ): float {
        // Beim Treiber 'cli' kommt der Betrag aus total_cost_usd der CLI:
        // rechnerisch zu Listenpreisen, abgerechnet wird ueber das Abo.
        $cost = $costUsd ?? ($usage === [] ? 0.0 : $this->costFor($usage));

        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'driver' => $driver,
            'model' => $this->model(),
            'operation' => $this->operation($templateKey),
            'reference_type' => $context->runId === null ? null : LlmCallContext::REFERENCE_RUN,
            'reference_id' => $context->runId,
            'guide_topic_id' => $context->topicId,
            'template_key' => Str::limit($templateKey, 64, ''),
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'cache_write_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
            'cache_read_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0),
            'search_count' => $searches ?? $this->searchCount($usage),
            'requests' => 1,
            'cost_usd' => $cost,
            'duration_ms' => $durationMs,
            'was_successful' => $error === null,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        $this->budget->record($cost);

        return $cost;
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function searchCount(array $usage): int
    {
        return (int) (((array) ($usage['server_tool_use'] ?? []))['web_search_requests'] ?? 0);
    }

    /**
     * Das Budget zaehlt nur operation 'guide.%'.
     */
    private function operation(string $templateKey): string
    {
        $operation = str_starts_with($templateKey, BudgetGuard::OPERATION_PREFIX)
            ? $templateKey
            : BudgetGuard::OPERATION_PREFIX.$templateKey;

        return Str::limit($operation, 64, '');
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function schemaFor(PromptTemplate $template, array $schema): array
    {
        $schema = $schema === [] ? (array) ($template->output_schema_json ?? []) : $schema;

        if ($schema === []) {
            throw new InvalidArgumentException(
                "Template '{$template->key}' hat kein Ausgabeschema; ohne Schema ist kein Tool-Use moeglich.",
            );
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelPricing(): array
    {
        $pricing = config("guide.pricing.{$this->model()}");

        // Ohne Preise waere cost_usd 0 und das Budget griffe nie.
        if (! is_array($pricing) || $pricing === []) {
            throw new InvalidArgumentException("config/guide.php enthaelt keine Preise fuer das Modell '{$this->model()}'.");
        }

        return $pricing;
    }

    private function http(): PendingRequest
    {
        $apiKey = (string) $this->option('api_key', '');

        if ($apiKey === '') {
            throw new InvalidArgumentException('ANTHROPIC_API_KEY ist nicht gesetzt.');
        }

        $this->modelPricing();

        $retry = (array) config('guide.resilience.retry', []);
        $baseDelay = (int) ($retry['base_delay_ms'] ?? 2000);
        $multiplier = max(1, (int) ($retry['multiplier'] ?? 3));
        $jitter = max(0, (int) ($retry['jitter_ms'] ?? 0));

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://api.anthropic.com/v1'), '/'))
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) $this->option('version', '2023-06-01'),
                'content-type' => 'application/json',
            ])
            ->timeout((int) $this->option('timeout', 300))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                fn (int $attempt) => $baseDelay * $multiplier ** ($attempt - 1) + random_int(0, $jitter),
                // Nur Netz-, Server- und Lastfehler wiederholen; ein 400 bleibt ein 400.
                fn (\Throwable $exception) => ! $exception instanceof RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    private function model(): string
    {
        return (string) config('guide.model', 'claude-sonnet-5');
    }

    private function toolName(): string
    {
        return (string) $this->option('tool_name', 'emit');
    }

    private function cacheEnabled(): bool
    {
        return (bool) $this->option('prompt_cache', true);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("guide.anthropic.{$key}", $default);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function errorMessage(Response $response): string
    {
        $body = (array) $response->json();
        $message = $body['error']['message'] ?? $response->body();

        return "HTTP {$response->status()}: ".(is_string($message) ? $message : json_encode($message));
    }
}
