<?php

declare(strict_types=1);

namespace App\Content\Llm;

use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\CliStructuredOutputException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Llm\Exceptions\ProviderAccountException;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\Central\PromptTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Zentraler Zugang zur Anthropic Messages API (#6).
 *
 * Schemakonforme Antworten entstehen ueber erzwungenen Tool-Use: die Anfrage
 * definiert genau ein Werkzeug ('emit') mit dem gewuenschten JSON-Schema als
 * input_schema und setzt tool_choice darauf. Das Modell kann dann gar nichts
 * anderes zurueckgeben als einen Aufruf dieses Werkzeugs; die Nutzdaten stehen
 * in content[].input. Zusaetzlich prueft der SchemaValidator die Ausgabe noch
 * einmal selbst und schickt Verstoesse als Klartext in den naechsten Versuch.
 *
 * Der System-Prompt traegt cache_control: Styleguides sind je Branche
 * identisch und werden bei 48 Artikeln am Tag dutzendfach wiederverwendet.
 *
 * Modell, max_tokens, Sampling und Preise stehen ausschliesslich in
 * config('content.providers.anthropic') — nichts davon ist hier fest verdrahtet.
 *
 * Mit driver=cli laeuft derselbe Aufruf ueber die Claude-CLI und das Claude-Abo
 * (ClaudeCliTransport); die CLI erzwingt das Schema dann selbst.
 */
class LlmClient
{
    public const PROVIDER = 'anthropic';

    public const DRIVER_API = 'api';

    public const DRIVER_CLI = 'cli';

    public function __construct(
        private readonly BudgetGuard $budget,
        private readonly PromptRenderer $renderer,
        private readonly SchemaValidator $validator,
        private readonly ClaudeCliTransport $cli,
    ) {}

    public static function usesCli(): bool
    {
        return config('content.providers.anthropic.driver') === self::DRIVER_CLI;
    }

    /**
     * Rendert das Template, ruft das Modell und liefert die validierte Ausgabe.
     *
     * @param  array<string, mixed>  $vars
     * @param  array<string, mixed>  $schema  leer = output_schema_json des Templates
     * @return array<string, mixed>
     *
     * @throws LlmSchemaException nach allen Versuchen keine schemakonforme Antwort
     * @throws BudgetExceededException Budgetgrenze erreicht
     * @throws ProviderAccountException Guthaben aufgebraucht oder Schluessel abgelehnt
     */
    public function structured(
        PromptTemplate $template,
        array $vars,
        array $schema = [],
        ?LlmContext $context = null,
    ): array {
        $schema = $schema === [] ? (array) ($template->output_schema_json ?? []) : $schema;

        if ($schema === []) {
            throw new InvalidArgumentException(
                "Template '{$template->key}' hat kein Ausgabeschema; ohne Schema ist kein Tool-Use moeglich.",
            );
        }

        $context = ($context ?? LlmContext::current())->withOperation(
            $context?->operation ?? $template->key,
        );

        $prompts = $this->renderer->renderTemplate($template, $vars);

        return $this->emit($prompts['system'], $prompts['user'], $schema, $context, $template->key);
    }

    /**
     * Derselbe Weg ohne Template-Datensatz — fuer Diagnose (content:llm:ping)
     * und fuer Aufrufer mit bereits fertigem Prompt.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function emit(
        ?string $system,
        string $user,
        array $schema,
        ?LlmContext $context = null,
        string $templateKey = 'ad-hoc',
    ): array {
        $context ??= LlmContext::current($templateKey);

        // Toter Zugang (#104): ohne Guthaben entsteht kein Artikel, der
        // Aufruf waere nur ein weiterer 400.
        ProviderAccountGuard::assertUsable(self::PROVIDER);

        $this->budget->check(
            self::PROVIDER,
            $context->tenantId,
            $context->referenceType,
            $context->referenceId,
        );

        $maxAttempts = 1 + max(0, (int) $this->option('schema_retries', 2));

        if (self::usesCli()) {
            return $this->emitViaCli($system, $user, $schema, $context, $templateKey, $maxAttempts);
        }

        $messages = [['role' => 'user', 'content' => $user]];
        $errors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->send($system, $messages, $schema, $context, $templateKey, $attempt);

            $block = $this->toolUseBlock($response);

            if ($block === null) {
                $errors = ['Antwort enthaelt keinen Block vom Typ tool_use.'];
                $messages = $this->retryMessages($messages, $response, $errors);

                continue;
            }

            $payload = is_array($block['input'] ?? null) ? $block['input'] : [];
            $errors = $this->validator->validate($payload, $schema);

            if ($errors === []) {
                return $payload;
            }

            Log::warning('Schemawidrige Modellantwort, neuer Versuch.', [
                'template' => $templateKey,
                'attempt' => $attempt,
                'errors' => $errors,
            ]);

            $messages = $this->retryMessages($messages, $response, $errors);
        }

        throw LlmSchemaException::afterRetries($templateKey, $maxAttempts, $errors);
    }

    /**
     * CLI-Weg: kein Nachrichtenverlauf, deshalb stehen vorige Ausgabe und
     * Verstoesse im Wiederholungsfall als Klartext im naechsten Prompt.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function emitViaCli(
        ?string $system,
        string $user,
        array $schema,
        LlmContext $context,
        string $templateKey,
        int $maxAttempts,
    ): array {
        $prompt = $user;
        $errors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $response = $this->cli->send($system, $prompt, $schema);
            } catch (CliStructuredOutputException $exception) {
                // Die CLI hat selbst schon mehrfach wiederholt; ein frischer
                // Aufruf gelingt meist (#42). Zaehlt als schemawidriger Versuch.
                $this->log($context, $templateKey, [], $this->elapsed($startedAt), false, $exception->getMessage(), $attempt);
                $errors = ['Die Ausgabe passte nicht zum verlangten Format.'];

                Log::warning('CLI ohne schemakonforme Ausgabe, neuer Versuch.', [
                    'template' => $templateKey,
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            } catch (\Throwable $exception) {
                $this->log($context, $templateKey, [], $this->elapsed($startedAt), false, $exception->getMessage(), $attempt);

                throw $exception;
            }

            $this->log($context, $templateKey, $response['usage'], $this->elapsed($startedAt), true, null, $attempt);
            ProviderAccountGuard::reportSuccess(self::PROVIDER);

            $payload = $response['output'];
            $errors = $payload === null
                ? ['Antwort enthaelt keine strukturierte Ausgabe.']
                : $this->validator->validate($payload, $schema);

            if ($errors === []) {
                return $payload;
            }

            Log::warning('Schemawidrige Modellantwort (CLI), neuer Versuch.', [
                'template' => $templateKey,
                'attempt' => $attempt,
                'errors' => $errors,
            ]);

            $previous = $payload === null ? $response['text'] : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

            $prompt = $user
                ."\n\n---\nDeine vorige Ausgabe:\n{$previous}\n\n"
                ."Die Ausgabe verletzt das Schema:\n- ".implode("\n- ", $errors)
                ."\nBehebe genau diese Punkte. Aendere nichts Inhaltliches, was nicht beanstandet wurde.";
        }

        throw LlmSchemaException::afterRetries($templateKey, $maxAttempts, $errors);
    }

    /**
     * Ein einzelner Aufruf inklusive Kosten-Logging.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed> dekodierte API-Antwort
     */
    private function send(
        ?string $system,
        array $messages,
        array $schema,
        LlmContext $context,
        string $templateKey,
        int $attempt,
    ): array {
        $payload = $this->payload($system, $messages, $schema);
        $startedAt = microtime(true);

        try {
            $response = $this->http()->post('/messages', $payload);
        } catch (\Throwable $exception) {
            $this->log($context, $templateKey, [], $this->elapsed($startedAt), false, $exception->getMessage());

            throw $exception;
        }

        $durationMs = $this->elapsed($startedAt);

        if ($response->failed()) {
            $this->log($context, $templateKey, [], $durationMs, false, $this->errorMessage($response));

            $reason = ProviderAccountGuard::classify($response);

            if ($reason !== null) {
                throw ProviderAccountGuard::reportFailure(self::PROVIDER, $reason, $this->errorMessage($response));
            }

            $response->throw();
        }

        $body = (array) $response->json();
        $usage = (array) ($body['usage'] ?? []);

        $this->log($context, $templateKey, $usage, $durationMs, true, null, $attempt);
        ProviderAccountGuard::reportSuccess(self::PROVIDER);

        return $body;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function payload(?string $system, array $messages, array $schema): array
    {
        $payload = [
            'model' => (string) $this->option('model', 'claude-sonnet-5'),
            'max_tokens' => (int) $this->option('max_tokens', 16000),
            'messages' => $messages,
            'tools' => [[
                'name' => $this->toolName(),
                'description' => 'Gib das Ergebnis ausschliesslich ueber dieses Werkzeug zurueck. '
                    .'Alle Pflichtfelder muessen gefuellt sein.',
                'input_schema' => $schema,
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => $this->toolName()],
        ];

        if ($system !== null && trim($system) !== '') {
            $block = ['type' => 'text', 'text' => $system];

            if ((bool) $this->option('prompt_cache', true)) {
                $block['cache_control'] = ['type' => 'ephemeral'];
            }

            $payload['system'] = [$block];
        }

        $effort = $this->option('effort');

        if (is_string($effort) && $effort !== '') {
            $payload['output_config'] = ['effort' => $effort];
        }

        $thinking = $this->option('thinking');

        if (is_string($thinking) && $thinking !== '') {
            $payload['thinking'] = ['type' => $thinking];
        }

        $temperature = $this->option('temperature');

        if ($temperature !== null) {
            $payload['temperature'] = (float) $temperature;
        }

        return $payload;
    }

    private function http(): PendingRequest
    {
        $apiKey = (string) $this->option('api_key', '');

        if ($apiKey === '') {
            throw new InvalidArgumentException('ANTHROPIC_API_KEY ist nicht gesetzt.');
        }

        $retry = (array) config('content.resilience.retry', []);

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://api.anthropic.com/v1'), '/'))
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) $this->option('version', '2023-06-01'),
                'content-type' => 'application/json',
            ])
            ->timeout((int) $this->option('timeout', 300))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                (int) ($retry['base_delay_ms'] ?? 2000),
                // Nur Netz- und Serverfehler wiederholen; ein 400 bleibt ein 400.
                fn (\Throwable $exception) => ! $exception instanceof \Illuminate\Http\Client\RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    /**
     * Erster Tool-Use-Block der Antwort.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function toolUseBlock(array $response): ?array
    {
        foreach ((array) ($response['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $this->toolName()) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Die Antwort bleibt im Verlauf stehen, damit das Modell im naechsten
     * Versuch sieht, was es geliefert hat, und nur die Verstoesse korrigiert.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $response
     * @param  array<int, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function retryMessages(array $messages, array $response, array $errors): array
    {
        $content = $response['content'] ?? null;
        $block = $this->toolUseBlock($response);

        if (is_array($content) && $content !== []) {
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }

        $hint = "Die Ausgabe verletzt das Schema:\n- ".implode("\n- ", $errors)
            ."\nGib das Ergebnis erneut ueber das Werkzeug '{$this->toolName()}' zurueck und behebe genau diese Punkte. "
            .'Aendere nichts Inhaltliches, was nicht beanstandet wurde.';

        $messages[] = $block === null
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
     */
    private function log(
        LlmContext $context,
        string $templateKey,
        array $usage,
        int $durationMs,
        bool $successful,
        ?string $error = null,
        int $attempt = 1,
    ): void {
        $cost = $this->costFor($usage);

        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => (string) $this->option('model', 'claude-sonnet-5'),
            'operation' => Str::limit($context->operation ?? $templateKey, 64, ''),
            'reference_type' => $context->referenceType,
            'reference_id' => $context->referenceId,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'cache_write_tokens' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
            'cache_read_tokens' => (int) ($usage['cache_read_input_tokens'] ?? 0),
            'requests' => 1,
            'cost_usd' => $cost,
            'duration_ms' => $durationMs,
            'was_successful' => $successful,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        $this->budget->record(self::PROVIDER, $cost);

        Log::debug('Anthropic-Aufruf abgeschlossen.', [
            'template' => $templateKey,
            'attempt' => $attempt,
            'tenant_id' => $context->tenantId,
            'draft_id' => $context->draftId(),
            'cost_usd' => $cost,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Kosten eines Aufrufs aus den Preisen pro Million Tokens.
     *
     * @param  array<string, mixed>  $usage
     */
    public function costFor(array $usage): float
    {
        // Ueber die CLI traegt das Abo die Kosten; Tokens werden trotzdem geloggt.
        if (self::usesCli()) {
            return 0.0;
        }

        $pricing = (array) $this->option('pricing', []);

        $cost = ((int) ($usage['input_tokens'] ?? 0)) * (float) ($pricing['input_per_mtok'] ?? 0)
            + ((int) ($usage['output_tokens'] ?? 0)) * (float) ($pricing['output_per_mtok'] ?? 0)
            + ((int) ($usage['cache_creation_input_tokens'] ?? 0)) * (float) ($pricing['cache_write_per_mtok'] ?? 0)
            + ((int) ($usage['cache_read_input_tokens'] ?? 0)) * (float) ($pricing['cache_read_per_mtok'] ?? 0);

        return round($cost / 1_000_000, 6);
    }

    private function toolName(): string
    {
        return (string) $this->option('tool_name', 'emit');
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.anthropic.{$key}", $default);
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
