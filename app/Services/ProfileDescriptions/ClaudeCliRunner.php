<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Ein Batch-Aufruf der Claude-CLI ('claude -p').
 *
 * Eingabe per stdin, System-Prompt per Option, Ausgabe als JSON-Huelle
 * (--output-format json). Werkzeuge sind abgeschaltet (--tools ""), damit das
 * Modell nur antwortet; eine Option fuer die Zahl der Turns kennt die CLI
 * (2.1.x) nicht, ohne Werkzeuge bleibt es aber bei einer Antwort.
 */
final class ClaudeCliRunner
{
    /**
     * Meldungen der CLI bei erschoepftem Kontingent.
     */
    private const LIMIT_PATTERN = '/usage limit|rate[ _-]?limit|limit reached|limit will reset|resets? at|out of (extra )?usage|credit balance is too low|overloaded/i';

    /**
     * @return list<array{id: int, description: string}>
     *
     * @throws UsageLimitReached
     * @throws ClaudeCliFailed
     */
    public function run(string $systemPrompt, string $input, string $model): array
    {
        $binary = $this->binary();

        $process = new Process(
            [
                $binary,
                '-p',
                '--model', $model,
                '--output-format', 'json',
                '--system-prompt', $systemPrompt,
                '--tools', '',
                '--no-session-persistence',
                '--strict-mcp-config',
            ],
            sys_get_temp_dir(),
            [
                // Nested-Session-Check der CLI umgehen, falls aus Claude Code gestartet.
                'CLAUDECODE' => '',
                'CLAUDE_CODE_ENTRYPOINT' => '',
                // Laravel reicht die .env an Kindprozesse weiter. Ein API-Schluessel
                // (Content-Pipeline) wuerde die CLI vom Abo auf API-Guthaben umstellen.
                'ANTHROPIC_API_KEY' => false,
                'ANTHROPIC_AUTH_TOKEN' => false,
            ],
            $input,
            (float) config('profile_descriptions.timeout', 600),
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ClaudeCliFailed('Zeitlimit der Claude-CLI ueberschritten.');
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $envelope = json_decode($stdout, true);
        $result = is_array($envelope) ? (string) ($envelope['result'] ?? '') : '';
        $isError = ! is_array($envelope) || ($envelope['is_error'] ?? false) === true || ! $process->isSuccessful();

        if ($isError) {
            $message = trim($result !== '' ? $result : "{$stderr} {$stdout}");

            if (preg_match(self::LIMIT_PATTERN, $message) === 1) {
                throw new UsageLimitReached(mb_substr($message, 0, 300));
            }

            throw new ClaudeCliFailed('Claude-CLI Exit '.$process->getExitCode().': '.mb_substr($message, 0, 300));
        }

        return $this->parse($result);
    }

    /**
     * @throws ClaudeCliFailed
     */
    public function binary(): string
    {
        $binary = (string) config('profile_descriptions.cli_binary');

        if ($binary === '' || ! is_executable($binary)) {
            throw new ClaudeCliFailed('Claude-CLI nicht gefunden, CLAUDE_CLI_BINARY in der .env pruefen.');
        }

        return $binary;
    }

    /**
     * @return list<array{id: int, description: string}>
     */
    private function parse(string $result): array
    {
        $text = trim($result);
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);

        // Falls doch Text um das Array steht: vom ersten [ bis zum letzten ].
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start === false || $end === false || $end < $start) {
            throw ClaudeCliFailed::unusableAnswer('Antwort enthaelt kein JSON-Array: '.mb_substr($text, 0, 200));
        }

        $items = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($items)) {
            throw ClaudeCliFailed::unusableAnswer('Antwort ist kein gueltiges JSON: '.json_last_error_msg());
        }

        $entries = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_numeric($item['id'])) {
                continue;
            }

            $entries[] = [
                'id' => (int) $item['id'],
                'description' => is_string($item['description'] ?? null) ? $item['description'] : '',
            ];
        }

        return $entries;
    }
}
