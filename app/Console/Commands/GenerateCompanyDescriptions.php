<?php

namespace App\Console\Commands;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\Ai\CompanyDescriptionGenerator;
use App\Services\Ai\CompanyDescriptionResult;
use Illuminate\Console\Command;
use Stancl\Tenancy\Concerns\HasATenantArgument;
use Stancl\Tenancy\Concerns\TenantAwareCommand;

class GenerateCompanyDescriptions extends Command
{
    use HasATenantArgument, TenantAwareCommand {
        HasATenantArgument::getTenants as getTenantsFromTrait;
    }

    protected $signature = 'tenants:generate-descriptions
        {--limit=0 : Maximale Anzahl Firmen (0 = alle)}
        {--offset=0 : Offset zum Überspringen}
        {--dry-run : Nur anzeigen, nicht speichern}
        {--force : Auch Firmen mit bestehender Beschreibung überschreiben}';

    protected $description = 'Generiert Firmenbeschreibungen via Claude Code CLI (mit KI-Footprint-Guard) für Firmen ohne Website und ohne Beschreibung';

    private int $generated = 0;

    private int $skipped = 0;

    private int $errors = 0;

    private int $failed = 0;

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

    public function handle(CompanyDescriptionGenerator $generator): int
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

            // Zweimal verworfene Generierungen nur mit --force erneut versuchen.
            $query->where(function ($q) {
                $q->whereNull('description_source')
                    ->orWhere('description_source', '!=', config('seo.description_generator.failed_source'));
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

        $this->info("Gefunden: {$total} Firmen ohne Website".($force ? '' : ' und ohne Beschreibung'));

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
            $progressBar = str_repeat('█', $filled).str_repeat('░', $barWidth - $filled);

            // Status-Zeile
            $this->output->write("\r\033[K");
            $this->output->write(
                "  <fg=cyan>{$current}</>/<fg=white>{$total}</> [{$progressBar}] <fg=yellow>{$percent}%</>"
                ."  <fg=gray>⏱ {$elapsedStr} | ETA: {$eta} |</>"
                ."  <fg=green>✓ {$this->generated}</> <fg=red>✗ {$this->errors}</>"
            );

            // Firmenname darunter
            $this->newLine();
            $truncatedName = mb_strlen($company->name) > 50
                ? mb_substr($company->name, 0, 47).'...'
                : $company->name;
            $this->output->write("  <fg=gray>→ #{$company->id} {$truncatedName}</>");

            $result = $generator->generate($company);

            if ($result->status === CompanyDescriptionResult::ERROR) {
                $this->errors++;
                $this->newLine();
                $this->error("  ✗ Fehler bei: {$company->name} (ID: {$company->id})");

                continue;
            }

            if ($result->status === CompanyDescriptionResult::FAILED) {
                $this->failed++;
                $this->newLine();
                $this->warn("  ✗ Verworfen nach {$result->attempts} Versuchen (KI-Footprint/zu kurz): {$company->name} (ID: {$company->id})");

                if (! $dryRun) {
                    $company->update(['description_source' => config('seo.description_generator.failed_source')]);
                }

                continue;
            }

            $description = (string) $result->description;

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

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  Fertig in {$totalTime}");
        $this->info("  ✓ Generiert:    {$this->generated}");
        $this->info("  ⊘ Übersprungen: {$this->skipped}");
        $this->info("  ✗ Verworfen:    {$this->failed}");
        $this->info("  ✗ Fehler:       {$this->errors}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return self::SUCCESS;
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
}
