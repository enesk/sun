<?php

declare(strict_types=1);

namespace App\Console\Commands\Seo;

use App\Console\Commands\Seo\Concerns\RunsForPortals;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyDescriptionBackup;
use App\Models\Tenant;
use App\Services\Seo\AiFootprintDetector;
use App\Support\TenantCache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt Meta-Kommentare aus KI-Prompts aus den Firmenbeschreibungen (#2).
 *
 * Vorgabe ist der Trockenlauf, geschrieben wird nur mit --apply. In beiden
 * Faellen entsteht je Portal ein CSV-Bericht unter storage/app/seo-cleanup/.
 * Vor jeder Aenderung wird der Originaltext in company_description_backups
 * gesichert; seo:restore-ai-footprints stellt ihn wieder her.
 *
 * Bleiben nach der Bereinigung weniger als seo.ai_footprints.min_length
 * Zeichen oder faellt mehr als seo.ai_footprints.max_removed_ratio des Textes
 * weg, wird nicht geschrieben, sondern "manuell pruefen" gemeldet.
 */
class CleanAiFootprints extends Command
{
    use RunsForPortals;

    protected $signature = 'seo:clean-ai-footprints
        {--dry-run : Nur Bericht schreiben, nichts aendern (Vorgabe)}
        {--apply : Bereinigte Texte tatsaechlich speichern}
        {--tenants=* : Portal-ID, UUID oder Domain (ohne Angabe alle Portale)}';

    protected $description = 'Entfernt KI-Footprints (SEO-Keywords, Prompt-Kommentare) aus Firmenbeschreibungen';

    private const STATUS_CLEANED = 'bereinigt';

    private const STATUS_WOULD_CLEAN = 'wird bereinigt';

    private const STATUS_MANUAL = 'manuell pruefen';

    private const EXCERPT_CONTEXT = 120;

    private const EXCERPT_LENGTH = 400;

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('--apply und --dry-run schliessen sich aus.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $detector = AiFootprintDetector::fromConfig();
        $reportDir = $this->centralStoragePath((string) config('seo.ai_footprints.report_path', 'app/seo-cleanup'));

        if (! $apply) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Mit --apply speichern.');
        }

        return $this->runForPortals(fn (Tenant $tenant): int => $this->cleanPortal($tenant, $detector, $reportDir, $apply));
    }

    private function cleanPortal(Tenant $tenant, AiFootprintDetector $detector, string $reportDir, bool $apply): int
    {
        $connection = (new Company)->getConnectionName();
        $schema = Schema::connection($connection);

        $columns = array_values(array_filter(
            (array) config('seo.ai_footprints.columns', ['description']),
            fn (string $column): bool => $schema->hasColumn('companies', $column),
        ));

        if ($columns === []) {
            $this->warn('Keine der konfigurierten Spalten existiert in companies — uebersprungen.');

            return self::SUCCESS;
        }

        if ($apply && ! $schema->hasTable('company_description_backups')) {
            $this->error('Tabelle company_description_backups fehlt — zuerst tenants:migrate ausfuehren.');

            return self::FAILURE;
        }

        $minLength = (int) config('seo.ai_footprints.min_length', 80);
        $maxRemovedRatio = (float) config('seo.ai_footprints.max_removed_ratio', 0.5);
        $rows = [];
        $affected = [];
        $counts = [self::STATUS_CLEANED => 0, self::STATUS_WOULD_CLEAN => 0, self::STATUS_MANUAL => 0];
        $checked = 0;

        Company::query()
            ->select(array_merge(['id', 'name', 'city_id'], $columns))
            ->where(function (Builder $query) use ($columns): void {
                foreach ($columns as $column) {
                    $query->orWhere(fn (Builder $q) => $q->whereNotNull($column)->where($column, '!=', ''));
                }
            })
            ->chunkById(500, function (Collection $companies) use (
                $detector, $columns, $minLength, $maxRemovedRatio, $apply, &$rows, &$affected, &$counts, &$checked
            ): void {
                /** @var Company $company */
                foreach ($companies as $company) {
                    $checked++;

                    foreach ($columns as $column) {
                        $result = $detector->clean((string) $company->getAttribute($column));

                        if (! $result->changed()) {
                            continue;
                        }

                        $manual = $result->cleanedLength() < $minLength
                            || $result->removedCharacters() > $maxRemovedRatio * mb_strlen($result->original);
                        $status = match (true) {
                            $manual => self::STATUS_MANUAL,
                            $apply => self::STATUS_CLEANED,
                            default => self::STATUS_WOULD_CLEAN,
                        };
                        $counts[$status]++;

                        [$before, $after] = $this->excerpts($result->original, $result->cleaned);

                        $rows[] = [
                            $company->id,
                            $company->name,
                            $column,
                            implode(', ', $result->matchedPatterns),
                            $status,
                            $before,
                            $after,
                            $result->removedCharacters(),
                        ];

                        if ($manual || ! $apply) {
                            continue;
                        }

                        DB::connection($company->getConnectionName())->transaction(function () use ($company, $column, $result): void {
                            CompanyDescriptionBackup::query()->create([
                                'company_id' => $company->id,
                                'column_name' => $column,
                                'original_description' => $result->original,
                                'cleaned_description' => $result->cleaned,
                                'matched_pattern' => implode(', ', $result->matchedPatterns),
                            ]);

                            $company->setAttribute($column, $result->cleaned);
                            $company->save();
                        });

                        $affected[$company->id] = $company->city_id;
                    }
                }
            });

        $file = $this->writeReport($tenant, $reportDir, $apply, $rows);

        if ($affected !== []) {
            $this->forgetCaches($affected);
        }

        $this->table(['Geprueft', 'Betroffen', $apply ? 'Bereinigt' : 'Wird bereinigt', 'Manuell pruefen'], [[
            $checked,
            count($rows),
            $apply ? $counts[self::STATUS_CLEANED] : $counts[self::STATUS_WOULD_CLEAN],
            $counts[self::STATUS_MANUAL],
        ]]);
        $this->line("Bericht: {$file}");

        return self::SUCCESS;
    }

    /**
     * Auszug um die erste Abweichung herum, damit der Bericht zeigt, was wegfaellt.
     *
     * @return array{0: string, 1: string}
     */
    private function excerpts(string $original, string $cleaned): array
    {
        $length = min(strlen($original), strlen($cleaned));
        $prefix = 0;

        while ($prefix < $length && $original[$prefix] === $cleaned[$prefix]) {
            $prefix++;
        }

        $start = mb_strlen(mb_strcut($original, 0, $prefix)) - self::EXCERPT_CONTEXT;
        $start = max(0, $start);

        return [
            $this->flatten(mb_substr($original, $start, self::EXCERPT_LENGTH)),
            $this->flatten(mb_substr($cleaned, $start, self::EXCERPT_LENGTH)),
        ];
    }

    private function flatten(string $text): string
    {
        return trim((string) preg_replace('/\s*\R\s*/u', ' ⏎ ', $text));
    }

    /**
     * @param  list<array<int, string|int>>  $rows
     */
    private function writeReport(Tenant $tenant, string $reportDir, bool $apply, array $rows): string
    {
        File::ensureDirectoryExists($reportDir);

        $file = sprintf('%s/%s-%s%s.csv', $reportDir, $this->portalSlug($tenant), now()->format('Y-m-d'), $apply ? '-apply' : '');
        $handle = fopen($file, 'w');

        // BOM, damit Excel die Umlaute richtig liest.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['company_id', 'Name', 'Spalte', 'Muster', 'Status', 'Vorher-Auszug', 'Nachher-Auszug', 'Entfernte Zeichen'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        return $file;
    }

    /**
     * Beschreibungen selbst liegen nicht im Cache, wohl aber Firmenlisten, die
     * sie ausgeben. Uebrige Schluessel laufen nach spaetestens einer Stunde ab.
     *
     * @param  array<int, int|null>  $affected  company_id => city_id
     */
    private function forgetCaches(array $affected): void
    {
        TenantCache::forget('portal.featured_companies');
        TenantCache::forget('portal.random_companies');

        foreach ($affected as $companyId => $cityId) {
            TenantCache::forget("sun-v2.profile.nearby.{$cityId}.{$companyId}");
        }
    }
}
