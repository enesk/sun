<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\Central\PromptTemplate;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Prueft den LLM-Zugang des Ratgebersystems mit einem echten research()-Aufruf
 * (#5): Web-Search, erzwungenes 'emit', Kostenbuchung. Kostet eine
 * Anfrage plus hoechstens --max-searches Suchen (je 0,01 USD).
 *
 *   php artisan guide:llm:ping
 *   php artisan guide:llm:ping --tenant=7 --allowed-domain=bafa.de --allowed-domain=kfw.de
 */
class GuideLlmPing extends Command
{
    private const TEMPLATE_KEY = 'guide.ping';

    private const DEFAULT_QUESTION = 'Wie hoch ist aktuell die staatliche Foerderung (BEG) fuer den Einbau einer Waermepumpe '
        .'in einem bestehenden Einfamilienhaus in Deutschland?';

    protected $signature = 'guide:llm:ping
        {--question= : Testfrage (Vorgabe: BEG-Foerderung Waermepumpe)}
        {--tenant= : Tenant-ID fuer Budget und Log}
        {--max-searches=3 : Hoechstzahl der Web-Suchen}
        {--allowed-domain=* : Nur diese Domains durchsuchen}
        {--blocked-domain=* : Diese Domains nie durchsuchen}';

    protected $description = 'Fuehrt einen research()-Aufruf des Ratgebersystems aus und zeigt Daten, Quellen und Kosten';

    public function handle(LlmClient $client): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId !== null && Tenant::query()->whereKey($tenantId)->doesntExist()) {
            $this->error("Tenant nicht gefunden: {$tenantId}");

            return self::FAILURE;
        }

        $question = (string) ($this->option('question') ?: self::DEFAULT_QUESTION);
        $startedAt = now();

        $this->line('Modell: <info>'.config('guide.model').'</info>, Werkzeug: <info>'.config('guide.web_search.tool_type').'</info>');
        $this->line("Frage: {$question}");
        $this->newLine();

        try {
            $result = $client->research(
                $this->template(),
                ['question' => $question, 'today' => now(config('guide.timezone'))->toDateString()],
                [],
                max(1, (int) $this->option('max-searches')),
                (array) $this->option('allowed-domain'),
                (array) $this->option('blocked-domain'),
                new LlmCallContext(tenantId: $tenantId === null ? null : (int) $tenantId),
            );
        } catch (\Throwable $exception) {
            $this->error($exception::class.': '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Daten');
        $this->line((string) json_encode($result->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();

        $this->info('Quellen ('.count($result->citations).')');
        $this->table(
            ['URL', 'Titel', 'Seitenalter', 'zitiert'],
            array_map(fn (array $citation) => [
                $citation['url'],
                mb_strimwidth($citation['title'], 0, 60, '…'),
                $citation['page_age'] ?? '–',
                $citation['cited'] ? 'ja' : '',
            ], $result->citations),
        );

        if ($result->searchErrors !== []) {
            $this->warn('Suchfehler: '.implode(', ', $result->searchErrors));
        }

        $logs = LlmUsageLog::query()
            ->where('template_key', self::TEMPLATE_KEY)
            ->where('created_at', '>=', $startedAt)
            ->count();

        $this->newLine();
        $this->line(sprintf(
            'Suchen: %d · Anfragen: %d · Tokens: %d ein / %d aus · Kosten: %.4f USD · Log-Eintraege: %d',
            $result->searchCount,
            $result->requests,
            $result->inputTokens,
            $result->outputTokens,
            $result->costUsd,
            $logs,
        ));

        if (count($result->citations) < 2) {
            $this->warn('Weniger als zwei Quellen — Web-Search pruefen (Werkzeugtyp, Domainlisten, max_uses).');
        }

        return self::SUCCESS;
    }

    /**
     * Nicht gespeichertes Template; der Ping braucht keinen Datensatz in
     * prompt_templates.
     */
    private function template(): PromptTemplate
    {
        return new PromptTemplate([
            'key' => self::TEMPLATE_KEY,
            'name' => 'LLM-Ping',
            'system_prompt' => 'Du bist Rechercheur fuer einen deutschsprachigen Ratgeber. Heute ist der {{today}}. '
                .'Recherchiere mit der Websuche in aktuellen, serioesen Quellen (Behoerden, Verbaende, Fachpresse) '
                .'und gib nur Aussagen wieder, die eine gefundene Quelle stuetzt.',
            'user_prompt' => '{{question}}',
            'output_schema_json' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer', 'facts'],
                'properties' => [
                    'answer' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 800],
                    'facts' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 8,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['statement', 'source_url'],
                            'properties' => [
                                'statement' => ['type' => 'string', 'minLength' => 5],
                                'source_url' => ['type' => 'string', 'minLength' => 8],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
