<?php

declare(strict_types=1);

namespace App\Content\Llm;

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
    private const AUTH_PATTERN = '/not logged in|please run \/login|invalid api key|authentication|oauth token|401|403/i';

    /**
     * @param  array<string, mixed>  $schema
     * @return array{output: array<string, mixed>|null, text: string, usage: array<string, mixed>}
     *
     * @throws ProviderAccountException Kontingent erschoepft oder Anmeldung ungueltig
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
            $message = mb_substr(trim($result !== '' ? $result : trim($process->getErrorOutput()).' '.$stdout), 0, 300);

            throw $this->failure($message, $process->getExitCode());
        }

        return [
            'output' => is_array($envelope['structured_output'] ?? null) ? $envelope['structured_output'] : null,
            'text' => $result,
            'usage' => (array) ($envelope['usage'] ?? []),
        ];
    }

    private function failure(string $message, ?int $exitCode): \Throwable
    {
        if (preg_match(self::LIMIT_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_CREDIT, $message);
        }

        if (preg_match(self::AUTH_PATTERN, $message) === 1) {
            return ProviderAccountGuard::reportFailure(LlmClient::PROVIDER, ProviderAccountException::REASON_KEY, $message);
        }

        return new RuntimeException("Claude-CLI Exit {$exitCode}: {$message}");
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
