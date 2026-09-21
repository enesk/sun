<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Llm\Exceptions\CliStructuredOutputException;
use App\Guide\Llm\Exceptions\ProviderAccountException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Modellaufruf ueber die Claude-CLI ('claude -p') statt ueber die Messages
 * API (#42). Grundlage ist der Treiber der alten Pipeline
 * (App\Content\Llm\ClaudeCliTransport, bis 3e1bb60).
 *
 * - Abgerechnet wird ueber das Claude-Abo des Tokens CLAUDE_CODE_OAUTH_TOKEN.
 *   Token, Binary und HOME kommen aus config('guide.cli') und gehen explizit
 *   in die Prozessumgebung; bei config:cache steht die .env dort nicht. Ein
 *   ANTHROPIC_API_KEY wird entfernt, sonst rechnete die CLI ueber API-Guthaben ab.
 * - Die Schemaausgabe erzwingt die CLI selbst (--json-schema); die Nutzdaten
 *   stehen in structured_output des result-Ereignisses.
 * - Ausgabe als stream-json: nur der Verlauf zeigt, welche Websuchen wirklich
 *   liefen und was sie fanden. usage.server_tool_use der CLI zaehlt die
 *   Websuche nicht mit (bleibt 0).
 * - Werkzeuge: ohne Recherche keine, mit Recherche nur WebSearch und
 *   WebFetch. Kein Bash, kein Dateizugriff.
 */
class ClaudeCliTransport
{
    public const DRIVER = 'cli';

    /**
     * Abo-Kontingent erschoepft (5-Stunden- bzw. Wochenfenster) oder
     * Guthaben leer. Nur gegen result/errors pruefen, nie gegen die rohe
     * Ausgabe.
     */
    private const LIMIT_PATTERN = '/usage limit|rate[ _-]?limit|limit reached|limit will reset|resets? at|out of (extra )?usage|credit balance is too low/i';

    /**
     * Anmeldung fehlt oder ist abgelaufen.
     */
    private const AUTH_PATTERN = '/not logged in|please run \/login|invalid api key|authentication_error|invalid oauth token|oauth token (has )?expired|invalid bearer token/i';

    private const WEB_TOOLS = ['WebSearch', 'WebFetch'];

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws ProviderAccountException Kontingent erschoepft oder Anmeldung ungueltig
     * @throws CliStructuredOutputException keine schemakonforme Ausgabe nach den CLI-eigenen Wiederholungen
     * @throws RuntimeException Zeitlimit oder sonstiger Fehler der CLI
     */
    public function run(string $system, string $user, array $schema, bool $webTools = false): CliResponse
    {
        $timeout = max(30, (int) config('guide.cli.timeout', 600));

        try {
            $result = Process::path(sys_get_temp_dir())
                ->env($this->environment())
                ->input($user)
                ->timeout($timeout)
                ->run($this->command($system, $schema, $webTools));
        } catch (ProcessTimedOutException) {
            throw new RuntimeException("Zeitlimit der Claude-CLI ueberschritten ({$timeout} s).");
        }

        $events = $this->events($result->output());
        $envelope = $this->resultEvent($events);
        $text = is_array($envelope) && is_string($envelope['result'] ?? null) ? $envelope['result'] : '';

        if (! is_array($envelope) || ($envelope['is_error'] ?? false) === true || ! $result->successful()) {
            throw $this->failure($envelope, $text, $result->exitCode(), $result->errorOutput(), $result->output());
        }

        [$searches, $searchResults, $fetchedUrls] = $this->webActivity($events);

        return new CliResponse(
            output: is_array($envelope['structured_output'] ?? null) ? $envelope['structured_output'] : null,
            text: $text,
            usage: (array) ($envelope['usage'] ?? []),
            costUsd: round((float) ($envelope['total_cost_usd'] ?? 0), 6),
            turns: (int) ($envelope['num_turns'] ?? 0),
            searches: $searches,
            searchResults: $searchResults,
            fetchedUrls: $fetchedUrls,
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    public function command(string $system, array $schema, bool $webTools): array
    {
        $command = [
            $this->binary(),
            '-p',
            '--model', (string) config('guide.model', 'claude-sonnet-5'),
            '--output-format', 'stream-json',
            '--verbose',
            '--json-schema', (string) json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        // Variadische Optionen: jede Liste endet an der naechsten Option.
        if ($webTools) {
            array_push($command, '--tools', ...self::WEB_TOOLS);
            array_push($command, '--allowedTools', ...self::WEB_TOOLS);
        } else {
            array_push($command, '--tools', '');
        }

        array_push($command, '--no-session-persistence', '--strict-mcp-config', '--setting-sources', '');

        if (trim($system) !== '') {
            array_push($command, '--system-prompt', $system);
        }

        $effort = config('guide.anthropic.effort');

        if (is_string($effort) && $effort !== '') {
            array_push($command, '--effort', $effort);
        }

        return $command;
    }

    public function binary(): string
    {
        $binary = (string) config('guide.cli.binary', '');

        if ($binary === '' || ! is_executable($binary)) {
            throw new RuntimeException('Claude-CLI nicht gefunden oder nicht ausfuehrbar, CLAUDE_CLI_BINARY in der .env pruefen.');
        }

        return $binary;
    }

    /**
     * @return array<string, string|false>
     */
    private function environment(): array
    {
        $environment = [
            // Nested-Session-Check der CLI umgehen, falls aus Claude Code gestartet.
            'CLAUDECODE' => '',
            'CLAUDE_CODE_ENTRYPOINT' => '',
            // Ein API-Schluessel aus der .env stellte die CLI auf API-Guthaben um.
            'ANTHROPIC_API_KEY' => false,
            'ANTHROPIC_AUTH_TOKEN' => false,
        ];

        $token = trim((string) config('guide.cli.oauth_token', ''));

        if ($token !== '') {
            $environment['CLAUDE_CODE_OAUTH_TOKEN'] = $token;
        }

        $home = $this->home();

        if ($home !== null) {
            $environment['HOME'] = $home;
        }

        return $environment;
    }

    /**
     * HOME des CLI-Prozesses: Einstellung, sonst das Home des ausfuehrenden
     * Benutzers. Horizon-Worker unter Supervisor laufen oft ohne HOME, die
     * CLI braucht es fuer ihre Einstellungen.
     */
    private function home(): ?string
    {
        $configured = trim((string) config('guide.cli.home', ''));

        if ($configured !== '') {
            return $configured;
        }

        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            return $home;
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());

            if (is_array($user) && ($user['dir'] ?? '') !== '') {
                return (string) $user['dir'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function events(string $output): array
    {
        $events = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            $event = json_decode($line, true);

            if (is_array($event)) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>|null
     */
    private function resultEvent(array $events): ?array
    {
        for ($i = count($events) - 1; $i >= 0; $i--) {
            if (($events[$i]['type'] ?? null) === 'result') {
                return $events[$i];
            }
        }

        return null;
    }

    /**
     * Zahl der Websuchen, ihre Treffer und die abgerufenen Seiten.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array{0: int, 1: array<int, array{url: string, title: string}>, 2: array<int, string>}
     */
    private function webActivity(array $events): array
    {
        $searches = 0;
        $results = [];
        $fetched = [];

        foreach ($events as $event) {
            $content = $event['message']['content'] ?? null;

            if (($event['type'] ?? null) === 'assistant' && is_array($content)) {
                foreach ($content as $block) {
                    if (! is_array($block) || ($block['type'] ?? null) !== 'tool_use') {
                        continue;
                    }

                    if (($block['name'] ?? null) === 'WebSearch') {
                        $searches++;
                    }

                    if (($block['name'] ?? null) === 'WebFetch' && is_string($block['input']['url'] ?? null)) {
                        $fetched[$block['input']['url']] = true;
                    }
                }

                continue;
            }

            $toolResult = $event['tool_use_result'] ?? null;

            if (($event['type'] ?? null) !== 'user' || ! is_array($toolResult) || ! is_array($toolResult['results'] ?? null)) {
                continue;
            }

            foreach ($toolResult['results'] as $group) {
                foreach ((array) (is_array($group) ? ($group['content'] ?? []) : []) as $hit) {
                    if (is_array($hit) && is_string($hit['url'] ?? null) && $hit['url'] !== '') {
                        $results[$hit['url']] ??= ['url' => $hit['url'], 'title' => (string) ($hit['title'] ?? '')];
                    }
                }
            }
        }

        return [$searches, array_values($results), array_keys($fetched)];
    }

    /**
     * Klassifiziert nur die Fehlerfelder der Huelle, nie die rohe Ausgabe:
     * dort stehen Token- und Kostenzahlen, und eine "403" darin hielte den
     * Zugang sonst fuer abgelehnt.
     *
     * @param  array<string, mixed>|null  $envelope
     */
    private function failure(?array $envelope, string $text, ?int $exitCode, string $stderr, string $stdout): \Throwable
    {
        if ($envelope === null) {
            $message = mb_substr(trim(trim($stderr).' '.trim($stdout)), 0, 300);

            return $this->classify($message, "Claude-CLI Exit {$exitCode}: {$message}");
        }

        $subtype = (string) ($envelope['subtype'] ?? '');
        $errors = array_filter(array_map(
            static fn ($error): string => is_string($error) ? $error : (string) json_encode($error, JSON_UNESCAPED_UNICODE),
            (array) ($envelope['errors'] ?? []),
        ));
        $apiStatus = $envelope['api_error_status'] ?? null;
        $message = mb_substr(trim($text !== '' ? $text : implode('; ', $errors)), 0, 300);
        $summary = "Claude-CLI Exit {$exitCode}, subtype {$subtype}"
            .($apiStatus !== null ? ", API-Status {$apiStatus}" : '')
            .', Durchgaenge '.(int) ($envelope['num_turns'] ?? 0)
            .($message !== '' ? ": {$message}" : '');

        if ($subtype === 'error_max_structured_output_retries') {
            return new CliStructuredOutputException($summary);
        }

        if (in_array((int) $apiStatus, [401, 403], true)) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_KEY, $summary);
        }

        if ((int) $apiStatus === 429) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_CREDIT, $summary);
        }

        return $this->classify($message, $summary);
    }

    private function classify(string $message, string $summary): \Throwable
    {
        if (preg_match(self::LIMIT_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_CREDIT, $summary);
        }

        if (preg_match(self::AUTH_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_KEY, $summary);
        }

        return new RuntimeException($summary);
    }
}
