<?php

namespace Tests\Feature\Guide;

use App\Guide\Llm\Exceptions\LlmSchemaException;
use App\Guide\Llm\Exceptions\ProviderAccountException;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Llm\ProviderAccountGuard;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\Central\PromptTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimedOut;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

/**
 * Treiber 'cli' des Ratgeber-LLM (#42): die Claude-CLI ist gefakt, geprueft
 * wird, was LlmClient aus ihrem stream-json-Verlauf macht.
 */
class ClaudeCliDriverTest extends TestCase
{
    use DatabaseTransactions;

    private const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['answer', 'facts'],
        'properties' => [
            'answer' => ['type' => 'string', 'minLength' => 5],
            'facts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['statement', 'source_url'],
                    'properties' => [
                        'statement' => ['type' => 'string'],
                        'source_url' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        config([
            'guide.driver' => 'cli',
            'guide.cli.binary' => '/bin/echo',
            'guide.cli.oauth_token' => 'test-token',
            'guide.cli.home' => '/home/test',
            'guide.cli.timeout' => 600,
            'guide.anthropic.schema_retries' => 2,
        ]);

        ProviderAccountGuard::reportSuccess(LlmClient::PROVIDER);
    }

    public function test_structured_returns_validated_output_and_logs_cli_costs(): void
    {
        Process::fake(['*' => Process::result($this->stream(['answer' => 'Antwort', 'facts' => []], cost: 0.0421))]);

        $data = $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext(tenantId: null));

        $this->assertSame('Antwort', $data['answer']);

        $log = LlmUsageLog::query()->latest('id')->first();
        $this->assertSame('cli', $log->driver);
        $this->assertSame('guide.test', $log->operation);
        $this->assertEqualsWithDelta(0.0421, (float) $log->cost_usd, 0.000001);
        $this->assertSame(120, (int) $log->output_tokens);
        $this->assertTrue((bool) $log->was_successful);

        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;

            return $process->environment['ANTHROPIC_API_KEY'] === false
                && $process->environment['CLAUDE_CODE_OAUTH_TOKEN'] === 'test-token'
                && $process->environment['HOME'] === '/home/test'
                && in_array('stream-json', $command, true)
                && $command[array_search('--tools', $command, true) + 1] === '';
        });
    }

    public function test_schema_violation_is_retried_with_errors_in_prompt(): void
    {
        Process::fake(['*' => Process::sequence([
            Process::result($this->stream(['answer' => 'x'])),
            Process::result($this->stream(['answer' => 'Richtig', 'facts' => []])),
        ])]);

        $data = $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);

        $this->assertSame('Richtig', $data['answer']);
        Process::assertRanTimes(fn () => true, 2);
        Process::assertRan(fn (PendingProcess $process): bool => str_contains((string) $process->input, 'Die Ausgabe verletzt das Schema'));
    }

    public function test_cli_structured_output_failure_counts_as_attempt(): void
    {
        Process::fake(['*' => Process::sequence([
            Process::result($this->errorStream(['subtype' => 'error_max_structured_output_retries', 'num_turns' => 6]), exitCode: 1),
            Process::result($this->stream(['answer' => 'Zweiter Versuch', 'facts' => []])),
        ])]);

        $data = $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);

        $this->assertSame('Zweiter Versuch', $data['answer']);
        $this->assertSame(2, LlmUsageLog::query()->where('driver', 'cli')->where('created_at', '>=', now()->subMinute())->count());
    }

    public function test_schema_errors_give_up_after_all_attempts(): void
    {
        Process::fake(['*' => Process::result($this->stream(['answer' => 'x']))]);

        $this->expectException(LlmSchemaException::class);

        try {
            $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
        } finally {
            Process::assertRanTimes(fn () => true, 3);
        }
    }

    public function test_research_uses_real_search_hits_as_citations(): void
    {
        $events = [
            ['type' => 'assistant', 'message' => ['content' => [
                ['type' => 'tool_use', 'name' => 'WebSearch', 'input' => ['query' => 'foerderung waermepumpe']],
            ]]],
            ['type' => 'user', 'tool_use_result' => ['query' => 'foerderung waermepumpe', 'results' => [[
                'tool_use_id' => 'srvtoolu_1',
                'content' => [
                    ['title' => 'KfW 458', 'url' => 'https://www.kfw.de/458'],
                    ['title' => 'Ratgeber', 'url' => 'https://ratgeber.example.org/wp'],
                    ['title' => 'Gesperrt', 'url' => 'https://spam.example.com/x'],
                ],
            ]]]],
        ];

        Process::fake(['*' => Process::result($this->stream([
            'answer' => 'Bis zu 70 Prozent.',
            'facts' => [['statement' => '30 % Grundfoerderung', 'source_url' => 'https://www.kfw.de/458']],
        ], events: $events))]);

        $result = $this->client()->research($this->template(), ['q' => 'Foerderung?'], [], 3, [], ['spam.example.com'], new LlmCallContext);

        $this->assertSame(1, $result->searchCount);
        $this->assertSame([], $result->searchErrors);
        $this->assertSame(['https://www.kfw.de/458', 'https://ratgeber.example.org/wp'], $result->urls());
        $this->assertTrue($result->citations[0]['cited']);
        $this->assertFalse($result->citations[1]['cited']);

        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;

            return in_array('WebSearch', $command, true)
                && in_array('WebFetch', $command, true)
                && ! in_array('Bash', $command, true)
                && str_contains((string) $process->input, 'hoechstens 3 Mal')
                && str_contains((string) $process->input, 'spam.example.com');
        });

        $this->assertSame(1, (int) LlmUsageLog::query()->latest('id')->value('search_count'));
    }

    public function test_research_without_search_is_flagged(): void
    {
        Process::fake(['*' => Process::result($this->stream(['answer' => 'Ohne Suche', 'facts' => []]))]);

        $result = $this->client()->research($this->template(), ['q' => 'Frage?'], [], 2, [], [], new LlmCallContext);

        $this->assertSame(0, $result->searchCount);
        $this->assertSame(['cli_no_search'], $result->searchErrors);
    }

    public function test_rejected_token_stops_with_provider_account_exception(): void
    {
        Process::fake(['*' => Process::result($this->errorStream([
            'subtype' => 'success',
            'api_error_status' => 401,
            'result' => 'Invalid bearer token',
            // Zahlen in usage duerfen nie als Fehler gelesen werden.
            'usage' => ['input_tokens' => 403, 'output_tokens' => 429],
        ]), exitCode: 1)]);

        try {
            $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
            $this->fail('ProviderAccountException erwartet.');
        } catch (ProviderAccountException $exception) {
            $this->assertSame(ProviderAccountException::REASON_KEY, $exception->reason);
        }

        // Kein weiterer Aufruf, solange der Zugang als tot gilt.
        $this->expectException(ProviderAccountException::class);
        $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
    }

    public function test_usage_limit_is_reported_as_exhausted_account(): void
    {
        Process::fake(['*' => Process::result($this->errorStream([
            'subtype' => 'success',
            'result' => 'Claude AI usage limit reached|1758520800',
        ]), exitCode: 1)]);

        try {
            $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
            $this->fail('ProviderAccountException erwartet.');
        } catch (ProviderAccountException $exception) {
            $this->assertSame(ProviderAccountException::REASON_CREDIT, $exception->reason);
        }
    }

    public function test_numbers_in_usage_do_not_count_as_auth_error(): void
    {
        Process::fake(['*' => Process::result($this->errorStream([
            'subtype' => 'error_during_execution',
            'usage' => ['input_tokens' => 403, 'output_tokens' => 401],
        ]), exitCode: 1)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('subtype error_during_execution');

        $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
    }

    public function test_timeout_becomes_runtime_exception_and_is_logged(): void
    {
        Process::fake(function (): never {
            throw new ProcessTimedOutException(
                new SymfonyTimedOut(new SymfonyProcess(['claude']), SymfonyTimedOut::TYPE_GENERAL),
                new FakeProcessResult,
            );
        });

        try {
            $this->client()->structured($this->template(), ['q' => 'Frage?'], [], new LlmCallContext);
            $this->fail('RuntimeException erwartet.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Zeitlimit der Claude-CLI', $exception->getMessage());
        }

        $log = LlmUsageLog::query()->latest('id')->first();
        $this->assertSame('cli', $log->driver);
        $this->assertFalse((bool) $log->was_successful);
    }

    private function client(): LlmClient
    {
        return app(LlmClient::class);
    }

    private function template(): PromptTemplate
    {
        return new PromptTemplate([
            'key' => 'guide.test',
            'name' => 'Test',
            'system_prompt' => 'Du bist Testredakteur.',
            'user_prompt' => '{{q}}',
            'output_schema_json' => self::SCHEMA,
        ]);
    }

    /**
     * stream-json-Verlauf mit abschliessendem result-Ereignis.
     *
     * @param  array<string, mixed>  $output
     * @param  array<int, array<string, mixed>>  $events
     */
    private function stream(array $output, float $cost = 0.01, array $events = []): string
    {
        $lines = [json_encode(['type' => 'system', 'subtype' => 'init'])];

        foreach ($events as $event) {
            $lines[] = json_encode($event);
        }

        $lines[] = json_encode([
            'type' => 'result',
            'subtype' => 'success',
            'is_error' => false,
            'num_turns' => 2,
            'total_cost_usd' => $cost,
            'result' => '',
            'structured_output' => $output,
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 120,
                'cache_read_input_tokens' => 50,
                'cache_creation_input_tokens' => 0,
                'server_tool_use' => ['web_search_requests' => 0],
            ],
        ]);

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function errorStream(array $fields): string
    {
        return json_encode(['type' => 'system', 'subtype' => 'init'])."\n"
            .json_encode(['type' => 'result', 'is_error' => true, 'num_turns' => 1, 'total_cost_usd' => 0] + $fields)."\n";
    }
}
