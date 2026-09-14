<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Portal\Company;
use App\Services\Seo\AiFootprintDetector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Generiert Firmenbeschreibungen ueber die Claude-CLI (#3).
 *
 * Jede Ausgabe laeuft vor der Rueckgabe durch denselben AiFootprintDetector wie
 * seo:clean-ai-footprints (keine zweite Musterliste) und wird danach auf
 * Plaintext mit Absaetzen normalisiert. Ist der Rest kuerzer als
 * seo.ai_footprints.min_length, wird einmal neu generiert, danach 'failed'.
 */
final class CompanyDescriptionGenerator
{
    private const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly CompanyDescriptionPrompt $prompt,
    ) {}

    public function generate(Company $company): CompanyDescriptionResult
    {
        $detector = AiFootprintDetector::fromConfig();
        $minLength = (int) config('seo.ai_footprints.min_length', 80);
        $patterns = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $raw = $this->call($company);

            if ($raw === null) {
                return new CompanyDescriptionResult(CompanyDescriptionResult::ERROR, null, $attempt, $this->prompt->version(), $patterns);
            }

            $result = $detector->clean($raw);
            $text = $this->toPlaintext($result->cleaned);

            if ($result->changed()) {
                $patterns = array_values(array_unique([...$patterns, ...$result->matchedPatterns]));

                Log::warning('Firmenbeschreibung: KI-Footprint in Generator-Ausgabe bereinigt', [
                    'company_id' => $company->id,
                    'tenant_id' => tenant()?->getTenantKey(),
                    'patterns' => $result->matchedPatterns,
                    'attempt' => $attempt,
                    'removed_characters' => $result->removedCharacters(),
                    'prompt_version' => $this->prompt->version(),
                ]);
            }

            if (mb_strlen($text) >= $minLength) {
                Log::info('Firmenbeschreibung generiert', [
                    'company_id' => $company->id,
                    'tenant_id' => tenant()?->getTenantKey(),
                    'attempt' => $attempt,
                    'prompt_version' => $this->prompt->version(),
                ]);

                return new CompanyDescriptionResult(CompanyDescriptionResult::GENERATED, $text, $attempt, $this->prompt->version(), $patterns);
            }
        }

        Log::warning('Firmenbeschreibung: Generierung nach Wiederholung verworfen, Rest zu kurz', [
            'company_id' => $company->id,
            'tenant_id' => tenant()?->getTenantKey(),
            'patterns' => $patterns,
            'min_length' => $minLength,
            'prompt_version' => $this->prompt->version(),
        ]);

        return new CompanyDescriptionResult(CompanyDescriptionResult::FAILED, null, self::MAX_ATTEMPTS, $this->prompt->version(), $patterns);
    }

    /**
     * Entfernt Markdown und fasst Zeilen zu Absaetzen zusammen: Leerzeilen
     * trennen Absaetze, Zeilen innerhalb eines Absatzes werden mit Leerzeichen verbunden.
     */
    public function toPlaintext(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        // Umschliessende Anfuehrungszeichen um den ganzen Text.
        $text = (string) (preg_replace('/^["„“”](.+)["“”]$/su', '$1', $text) ?? $text);

        $text = (string) preg_replace('/```[^\n]*\n?/u', '', $text);
        $text = (string) preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u', '$1', $text);

        $paragraphs = [];
        $current = [];

        foreach (explode("\n", $text) as $line) {
            $line = $this->stripLineMarkdown($line);

            if ($line === '') {
                if ($current !== []) {
                    $paragraphs[] = implode(' ', $current);
                    $current = [];
                }

                continue;
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $paragraphs[] = implode(' ', $current);
        }

        return implode("\n\n", $paragraphs);
    }

    private function stripLineMarkdown(string $line): string
    {
        $line = trim($line);

        // Trennlinien aus -, *, _ oder =.
        if (preg_match('/^[-*_=\s]{3,}$/u', $line) === 1) {
            return '';
        }

        $line = (string) preg_replace('/^(?:>\s*)+/u', '', $line);

        // Ueberschriften fallen ganz weg: "## Titel" oder eine fette Zeile ohne Satzende.
        if (preg_match('/^#{1,6}\s/u', $line) === 1 || preg_match('/^(\*\*|__)[^*_]+\1:?$/u', $line) === 1) {
            return '';
        }

        $line = (string) preg_replace('/^(?:[-*+•·▪‣–]|\d{1,2}[.)])\s+/u', '', $line);

        $line = str_replace(['*', '`'], '', $line);
        $line = (string) preg_replace('/(?<![\p{L}\p{N}])_+|_+(?![\p{L}\p{N}])/u', '', $line);
        $line = (string) preg_replace('/#(?=[\p{L}\p{N}])/u', '', $line);

        return trim((string) preg_replace('/[ \t]{2,}/u', ' ', $line));
    }

    private function call(Company $company): ?string
    {
        $config = (array) config('seo.description_generator');

        $result = Process::timeout((int) ($config['timeout'] ?? 60))
            ->env([
                // Nested-Session-Check der CLI umgehen.
                'CLAUDECODE' => '',
                'CLAUDE_CODE_ENTRYPOINT' => '',
            ])
            ->input($this->prompt->user($company))
            ->run([
                (string) $config['cli_binary'],
                '-p',
                '--model', (string) ($config['model'] ?? 'sonnet'),
                '--system-prompt', $this->prompt->system(),
            ]);

        if (! $result->successful()) {
            Log::warning('Firmenbeschreibung: Claude-CLI fehlgeschlagen', [
                'company_id' => $company->id,
                'tenant_id' => tenant()?->getTenantKey(),
                'exit_code' => $result->exitCode(),
                'output' => mb_substr($result->output().$result->errorOutput(), 0, 500),
                'prompt_version' => $this->prompt->version(),
            ]);

            return null;
        }

        $output = trim($result->output());

        return $output === '' ? null : $output;
    }
}
