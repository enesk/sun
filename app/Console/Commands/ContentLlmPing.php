<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Llm\EmbeddingClient;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\Exceptions\LlmSchemaException;
use App\Content\Llm\LlmClient;
use App\Content\Llm\LlmContext;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\Central\PromptTemplate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnoseaufruf gegen Anthropic und Voyage (#6, Definition of Done).
 *
 * Beweist in einem Durchlauf: Zugangsdaten stimmen, der erzwungene Tool-Use
 * liefert eine schemakonforme Antwort, der Budget-Guard laesst den Aufruf
 * durch und es entsteht ein Eintrag in llm_usage_logs.
 */
class ContentLlmPing extends Command
{
    protected $signature = 'content:llm:ping
        {--template= : Schluessel eines Templates aus prompt_templates statt des Testprompts}
        {--tenant= : Mandant (ID, UUID oder Domain) fuer Budget und Logging}
        {--embedding : Zusaetzlich einen Voyage-Aufruf ausfuehren}';

    protected $description = 'Prueft LLM-Zugang, Schemaausgabe, Budget-Guard und Kosten-Logging';

    /**
     * Bewusst klein, aber mit allen Zutaten, die im Betrieb vorkommen:
     * Pflichtfelder, enum, Liste mit Mindestlaenge.
     *
     * @var array<string, mixed>
     */
    private const PING_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['status', 'antwort', 'stichworte'],
        'properties' => [
            'status' => ['type' => 'string', 'enum' => ['ok']],
            'antwort' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200],
            'stichworte' => [
                'type' => 'array',
                'minItems' => 2,
                'maxItems' => 5,
                'items' => ['type' => 'string'],
            ],
        ],
    ];

    public function handle(LlmClient $client, EmbeddingClient $embeddings): int
    {
        $tenant = $this->tenant();

        if ($tenant === false) {
            return self::FAILURE;
        }

        $context = new LlmContext(
            tenantId: $tenant?->getKey() === null ? null : (int) $tenant->getKey(),
            operation: 'ping',
        );

        $this->line('Modell: '.config('content.providers.anthropic.model'));
        $this->line('Mandant: '.($tenant?->name ?? 'keiner (netzwerkweit)'));

        try {
            $result = $this->callModel($client, $context);
        } catch (BudgetExceededException $exception) {
            $this->error($exception->getMessage());
            $this->line("Provider steht jetzt auf 'paused' und wird um 00:00 freigegeben.");

            return self::FAILURE;
        } catch (LlmSchemaException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Schemakonforme Antwort erhalten:');
        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($this->option('embedding') && ! $this->pingEmbedding($embeddings, $context)) {
            return self::FAILURE;
        }

        $this->showLogs();

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function callModel(LlmClient $client, LlmContext $context): array
    {
        $key = $this->option('template');

        if ($key === null) {
            return $client->emit(
                'Du bist der Diagnose-Endpunkt der Ratgeber-Pipeline. Antworte knapp und auf Deutsch.',
                'Bestaetige, dass der Zugang funktioniert. Nenne dabei zwei bis fuenf Stichworte '
                    .'zum Thema Ratgeber-Redaktion.',
                self::PING_SCHEMA,
                $context,
                'ping',
            );
        }

        $template = PromptTemplate::query()
            ->resolve((string) $key, $context->tenantId)
            ->first();

        if (! $template instanceof PromptTemplate) {
            throw new \RuntimeException("Kein aktives Template mit dem Schluessel '{$key}'.");
        }

        $this->line("Template: {$template->name} (v{$template->version})");

        return $client->structured($template, [], [], $context);
    }

    private function pingEmbedding(EmbeddingClient $embeddings, LlmContext $context): bool
    {
        try {
            $vector = $embeddings->embed('Waermepumpe foerderung 2026', EmbeddingClient::INPUT_DOCUMENT, $context);
        } catch (Throwable $exception) {
            $this->error('Voyage: '.$exception->getMessage());

            return false;
        }

        $this->info('Voyage-Vektor erhalten: '.count($vector).' Dimensionen.');

        return true;
    }

    private function showLogs(): void
    {
        $logs = LlmUsageLog::query()
            ->where('operation', 'ping')
            ->latest('id')
            ->limit(5)
            ->get();

        $this->table(
            ['ID', 'Provider', 'Modell', 'In', 'Out', 'Cache r/w', 'USD', 'ms', 'ok'],
            $logs->map(fn (LlmUsageLog $log) => [
                $log->id,
                $log->provider,
                $log->model,
                $log->input_tokens,
                $log->output_tokens,
                "{$log->cache_read_tokens}/{$log->cache_write_tokens}",
                number_format($log->cost_usd, 6),
                $log->duration_ms,
                $log->was_successful ? 'ja' : 'nein',
            ])->all(),
        );
    }

    /**
     * @return Tenant|null|false false = angegebener Mandant existiert nicht
     */
    private function tenant(): Tenant|null|false
    {
        $value = $this->option('tenant');

        if ($value === null) {
            return null;
        }

        $tenant = is_numeric($value)
            ? Tenant::query()->find((int) $value)
            : Tenant::query()->where('uuid', $value)->orWhere('domain', $value)->first();

        if (! $tenant instanceof Tenant) {
            $this->error("Mandant '{$value}' nicht gefunden.");

            return false;
        }

        return $tenant;
    }
}
