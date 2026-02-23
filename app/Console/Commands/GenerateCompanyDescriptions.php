<?php

namespace App\Console\Commands;

use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Stancl\Tenancy\Concerns\HasATenantArgument;
use Stancl\Tenancy\Concerns\TenantAwareCommand;

class GenerateCompanyDescriptions extends Command
{
    use TenantAwareCommand, HasATenantArgument {
        HasATenantArgument::getTenants as getTenantsFromTrait;
    }

    protected $signature = 'tenants:generate-descriptions
        {--limit=0 : Maximale Anzahl Firmen (0 = alle)}
        {--offset=0 : Offset zum Überspringen}
        {--dry-run : Nur anzeigen, nicht speichern}
        {--force : Auch Firmen mit bestehender Beschreibung überschreiben}';

    protected $description = 'Generiert Firmenbeschreibungen via Claude Code CLI für Firmen ohne Website und ohne Beschreibung';

    private int $generated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private float $startTime = 0;

    protected function getTenants(): array
    {
        $id = $this->argument('tenant');

        // Zuerst per Integer-ID suchen, dann per UUID
        $tenant = Tenant::where('id', $id)->first()
            ?? tenancy()->find($id);

        if (! $tenant) {
            $this->error("Tenant '{$id}' nicht gefunden.");

            return [];
        }

        return [$tenant];
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Selektiere Firmen ohne Website und ohne Beschreibung
        $query = Company::query()
            ->where(function ($q) {
                $q->whereNull('website')->orWhere('website', '');
            });

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            });
        }

        $query->with(['categories', 'city', 'reviews' => function ($q) {
            $q->where('moderation_status', 'approved')
                ->orderByDesc('rating')
                ->limit(5);
        }]);

        $query->orderBy('id');

        if ($offset > 0) {
            $query->skip($offset);
        }

        if ($limit > 0) {
            $query->take($limit);
        }

        $companies = $query->get();
        $total = $companies->count();

        $this->info("Gefunden: {$total} Firmen ohne Website" . ($force ? '' : ' und ohne Beschreibung'));

        if ($total === 0) {
            $this->info('Keine Firmen zu verarbeiten.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Beschreibungen werden nur angezeigt, nicht gespeichert.');
        }

        $this->startTime = microtime(true);
        $this->newLine();

        foreach ($companies as $index => $company) {
            $current = $index + 1;
            $elapsed = microtime(true) - $this->startTime;
            $avgPerItem = $current > 1 ? $elapsed / ($current - 1) : 0;
            $remaining = $avgPerItem > 0 ? ($total - $current) * $avgPerItem : 0;
            $eta = $remaining > 0 ? $this->formatDuration($remaining) : '--:--';
            $elapsedStr = $this->formatDuration($elapsed);
            $percent = round(($current / $total) * 100);

            // Fortschrittsbalken bauen (30 Zeichen breit)
            $barWidth = 30;
            $filled = (int) round($barWidth * $current / $total);
            $progressBar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);

            // Status-Zeile
            $this->output->write("\r\033[K");
            $this->output->write(
                "  <fg=cyan>{$current}</>/<fg=white>{$total}</> [{$progressBar}] <fg=yellow>{$percent}%</>"
                . "  <fg=gray>⏱ {$elapsedStr} | ETA: {$eta} |</>"
                . "  <fg=green>✓ {$this->generated}</> <fg=red>✗ {$this->errors}</>"
            );

            // Firmenname darunter
            $this->newLine();
            $truncatedName = mb_strlen($company->name) > 50
                ? mb_substr($company->name, 0, 47) . '...'
                : $company->name;
            $this->output->write("  <fg=gray>→ #{$company->id} {$truncatedName}</>");

            $prompt = $this->buildPrompt($company);
            $description = $this->callClaude($prompt);

            if ($description === null) {
                $this->errors++;
                $this->newLine();
                $this->error("  ✗ Fehler bei: {$company->name} (ID: {$company->id})");
                continue;
            }

            // Bereinigung: Manchmal kommt ein Prefix wie "Hier ist die Beschreibung:" zurück
            $description = $this->cleanResponse($description);

            if ($dryRun) {
                $this->newLine();
                $this->line("  <info>[{$company->id}]</info> {$company->name}");
                $this->line("  <comment>{$description}</comment>");
            } else {
                $company->update([
                    'description' => $description,
                    'description_source' => 'ai_generated',
                ]);
                $this->generated++;
            }

            // Cursor eine Zeile hoch für Overwrite beim nächsten Durchlauf
            if (! $dryRun) {
                $this->output->write("\033[1A\r\033[K");
            }

            // Kleiner Delay um Rate Limiting zu vermeiden
            usleep(500_000); // 0.5s
        }

        $this->newLine(2);
        $totalTime = $this->formatDuration(microtime(true) - $this->startTime);

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Fertig in {$totalTime}");
        $this->info("  ✓ Generiert:    {$this->generated}");
        $this->info("  ⊘ Übersprungen: {$this->skipped}");
        $this->info("  ✗ Fehler:       {$this->errors}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return self::SUCCESS;
    }

    private function buildPrompt(Company $company): string
    {
        $categories = $company->categories->pluck('name')->implode(', ') ?: 'Unbekannt';
        $city = $company->city?->name ?? 'Unbekannt';
        $address = trim("{$company->street} {$company->house_no}");
        $rating = $company->rating > 0 ? "{$company->rating}/5 Sternen bei {$company->rating_count} Bewertungen" : 'Keine Bewertungen';

        // Beste Reviews zusammenfassen
        $reviewContext = '';
        if ($company->reviews->isNotEmpty()) {
            $reviewTexts = $company->reviews
                ->filter(fn ($r) => ! empty($r->body))
                ->map(fn ($r) => "- {$r->rating}/5: \"{$r->body}\"")
                ->take(3)
                ->implode("\n");

            if ($reviewTexts) {
                $reviewContext = "\n\nKundenbewertungen:\n{$reviewTexts}";
            }
        }

        return <<<PROMPT
Du bist ein sachlicher Texter fuer ein deutsches Branchenportal. Schreibe eine informative Firmenbeschreibung (2-4 Saetze, 150-300 Zeichen) fuer:

Firma: {$company->name}
Kategorie: {$categories}
Stadt: {$city}
Adresse: {$address}
Bewertung: {$rating}{$reviewContext}

Regeln:
- Sachlich-professionell, keine Werbesprache
- Erwaehne die Bewertung mit Sternen und Anzahl
- Wenn Kundenbewertungen vorhanden, paraphrasiere eine positive Aussage
- Erwaehne Branche und Standort
- Keine erfundenen Details (keine Telefonnummern, keine Preise, keine Leistungen die nicht in den Daten stehen)
- Deutsch
- NUR die Beschreibung ausgeben, kein Prefix wie "Hier ist..." oder "Beschreibung:"
- Keine Anfuehrungszeichen um den gesamten Text
PROMPT;
    }

    private function callClaude(string $prompt): ?string
    {
        // Prompt als Temp-File, um Shell-Escaping-Probleme zu vermeiden
        $tmpFile = tempnam(sys_get_temp_dir(), 'claude_prompt_');
        file_put_contents($tmpFile, $prompt);

        try {
            // Nested-Session-Check umgehen: CLAUDECODE explizit leeren
            $result = Process::timeout(60)
                ->env([
                    'CLAUDECODE' => '',
                    'CLAUDE_CODE_ENTRYPOINT' => '',
                ])
                ->run("cat {$tmpFile} | /Users/enes/.local/bin/claude -p --model sonnet 2>&1");

            if (! $result->successful()) {
                $this->warn("  Claude CLI Exit-Code: {$result->exitCode()}");
                $this->warn("  Output: " . substr($result->output(), 0, 500));
                return null;
            }

            $output = trim($result->output());

            if (empty($output)) {
                return null;
            }

            return $output;
        } finally {
            @unlink($tmpFile);
        }
    }

    private function formatDuration(float $seconds): string
    {
        $h = (int) ($seconds / 3600);
        $m = (int) (($seconds % 3600) / 60);
        $s = (int) ($seconds % 60);

        if ($h > 0) {
            return sprintf('%dh %02dm %02ds', $h, $m, $s);
        }

        return sprintf('%dm %02ds', $m, $s);
    }

    private function cleanResponse(string $text): string
    {
        // Entferne typische LLM-Prefixe
        $prefixes = [
            'Hier ist die Beschreibung:',
            'Hier ist die Firmenbeschreibung:',
            'Beschreibung:',
            'Firmenbeschreibung:',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($text, $prefix)) {
                $text = trim(substr($text, strlen($prefix)));
            }
        }

        // Entferne umschließende Anführungszeichen (gerade und typographische)
        $text = preg_replace('/^["„"](.+)[""\x{201C}]$/su', '$1', $text) ?? $text;

        return trim($text);
    }
}
