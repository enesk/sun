<?php

declare(strict_types=1);

namespace App\Content\Llm;

use App\Content\Llm\Exceptions\CliStructuredOutputException;
use App\Content\Llm\Exceptions\ProviderAccountException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Modellaufruf ueber die Claude-CLI ('claude -p') statt ueber die Messages API.
 *
 * Abgerechnet wird dann ueber das Claude-Abo des angemeldeten Kontos, nicht
 * ueber API-Guthaben. Die Schemaausgabe erzwingt die CLI selbst
 * (--json-schema), die Nutzdaten stehen in 'structured_output' der JSON-Huelle.
 *
 * Anmeldung: CLAUDE_CODE_OAUTH_TOKEN (aus 'claude setup-token') wird explizit
 * weitergereicht, weil bei gecachter Konfiguration die .env nicht in der
 * Prozessumgebung steht. Ein ANTHROPIC_API_KEY wird bewusst entfernt, sonst
 * rechnet die CLI ueber API-Guthaben ab.
 */
class ClaudeCliTransport
{
    /**
     * Abo-Kontingent erschoepft (5-Stunden- bzw. Wochenfenster).
     */
    private const LIMIT_PATTERN = '/usage limit|rate[ _-]?limit|limit reached|limit will reset|resets? at|out of (extra )?usage|credit balance is too low/i';

    /**
     * Anmeldung fehlt oder ist abgelaufen.
     */
    private const AUTH_PATTERN = '/not logged in|please run \/login|invalid api key|authentication_error|invalid oauth token|oauth token (has )?expired/i';

    /**
     * @param  array<string, mixed>  $schema
     * @return array{output: array<string, mixed>|null, text: string, usage: array<string, mixed>}
     *
     * @throws ProviderAccountException Kontingent erschoepft oder Anmeldung ungueltig
     * @throws CliStructuredOutputException keine schemakonforme Ausgabe nach den CLI-eigenen Wiederholungen
     * @throws RuntimeException sonstiger Fehler der CLI
     */
    public function send(?string $system, string $user, array $schema): array
    {
        $command = [
            $this->binary(),
            '-p',
            '--model', (string) $this->option('model', 'claude-sonnet-5'),
            '--output-format', 'json',
            '--json-schema', (string) json_encode($schema, JSON_UNESCAPED_UNICODE),
            '--tools', '',
            '--no-session-persistence',
            '--strict-mcp-config',
            '--setting-sources', '',
        ];

        if ($system !== null && trim($system) !== '') {
            array_push($command, '--system-prompt', $system);
        }

        $effort = $this->option('effort');

        if (is_string($effort) && $effort !== '') {
            array_push($command, '--effort', $effort);
        }

        $process = new Process($command, sys_get_temp_dir(), $this->environment(), $user, (float) $this->option('timeout', 300));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('Zeitlimit der Claude-CLI ueberschritten.');
        }

        $stdout = trim($process->getOutput());
        $envelope = json_decode($stdout, true);
        $result = is_array($envelope) && is_string($envelope['result'] ?? null) ? $envelope['result'] : '';

        if (! is_array($envelope) || ($envelope['is_error'] ?? false) === true || ! $process->isSuccessful()) {
            throw $this->failure($envelope, $result, $process);
        }

        return [
            'output' => is_array($envelope['structured_output'] ?? null) ? $envelope['structured_output'] : null,
            'text' => $result,
            'usage' => (array) ($envelope['usage'] ?? []),
        ];
    }

    /**
     * Klassifiziert nur die Fehlerfelder der Huelle, nie die rohe Ausgabe:
     * dort stehen Token- und Kostenzahlen, und eine "403" darin hielte den
     * Zugang sonst fuer abgelehnt.
     */
    private function failure(mixed $envelope, string $result, Process $process): \Throwable
    {
        $exitCode = $process->getExitCode();

        if (! is_array($envelope)) {
            $message = mb_substr(trim(trim($process->getErrorOutput()).' '.trim($process->getOutput())), 0, 300);

            return $this->classify($message, "Claude-CLI Exit {$exitCode}: {$message}");
        }

        $subtype = (string) ($envelope['subtype'] ?? '');
        $errors = array_filter(array_map(
            static fn ($error): string => is_string($error) ? $error : (string) json_encode($error, JSON_UNESCAPED_UNICODE),
            (array) ($envelope['errors'] ?? []),
        ));
        $apiStatus = $envelope['api_error_status'] ?? null;
        $message = mb_substr(trim($result !== '' ? $result : implode('; ', $errors)), 0, 300);
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

        return $this->classify($message, $summary);
    }

    private function classify(string $message, string $summary): \Throwable
    {
        if (preg_match(self::LIMIT_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_CREDIT, $message);
        }

        if (preg_match(self::AUTH_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_KEY, $message);
        }

        return new RuntimeException($summary);
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
            'ANTHROPIC_API_KEY' => false,
            'ANTHROPIC_AUTH_TOKEN' => false,
        ];

        $token = trim((string) $this->option('cli_oauth_token', ''));

        if ($token !== '') {
            $environment['CLAUDE_CODE_OAUTH_TOKEN'] = $token;
        }

        return $environment;
    }

    public function binary(): string
    {
        $binary = (string) $this->option('cli_binary', '');

        if ($binary === '' || ! is_executable($binary)) {
            throw new RuntimeException('Claude-CLI nicht gefunden, CLAUDE_CLI_BINARY in der .env pruefen.');
        }

        return $binary;
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.anthropic.{$key}", $default);
    }
}
