<?php

declare(strict_types=1);

namespace App\Console\Commands\Seo;

use App\Console\Commands\Seo\Concerns\RunsForPortals;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyDescriptionBackup;
use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback zu seo:clean-ai-footprints (#2).
 *
 * Stellt je Firma und Spalte die juengste noch nicht zurueckgespielte
 * Sicherung wieder her. Wurde der Text seit der Bereinigung von Hand
 * geaendert, wird er ohne --force nicht ueberschrieben.
 */
class RestoreAiFootprints extends Command
{
    use RunsForPortals;

    protected $signature = 'seo:restore-ai-footprints
        {--apply : Tatsaechlich zurueckschreiben (Vorgabe ist Trockenlauf)}
        {--tenants=* : Portal-ID, UUID oder Domain (ohne Angabe alle Portale)}
        {--company=* : Nur diese company_id(s)}
        {--since= : Nur Sicherungen ab diesem Zeitpunkt, z. B. 2026-09-14 oder "2026-09-14 10:00"}
        {--force : Auch zurueckschreiben, wenn der Text seit der Bereinigung geaendert wurde}';

    protected $description = 'Stellt mit seo:clean-ai-footprints bereinigte Firmenbeschreibungen aus der Sicherung wieder her';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (! $apply) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Mit --apply zurueckschreiben.');
        }

        return $this->runForPortals(fn (Tenant $tenant): int => $this->restorePortal($apply));
    }

    private function restorePortal(bool $apply): int
    {
        if (! Schema::connection((new CompanyDescriptionBackup)->getConnectionName())->hasTable('company_description_backups')) {
            $this->warn('Keine Tabelle company_description_backups — nichts zurueckzuspielen.');

            return self::SUCCESS;
        }

        $companyIds = array_map('intval', array_filter((array) $this->option('company')));
        $since = $this->option('since') ? Carbon::parse((string) $this->option('since')) : null;
        $force = (bool) $this->option('force');
        $counts = ['wiederhergestellt' => 0, 'geaendert, uebersprungen' => 0, 'Firma fehlt' => 0];
        $seen = [];

        $pending = fn () => CompanyDescriptionBackup::query()
            ->whereNull('restored_at')
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since));

        $pending()
            ->when($companyIds !== [], fn ($query) => $query->whereIn('company_id', $companyIds))
            ->chunkByIdDesc(500, function ($backups) use ($pending, $apply, $force, &$counts, &$seen): void {
                /** @var CompanyDescriptionBackup $backup */
                foreach ($backups as $backup) {
                    $key = "{$backup->company_id}.{$backup->column_name}";

                    // Die juengste Sicherung je Firma und Spalte entscheidet, ob der
                    // Text seitdem geaendert wurde; zurueck geht es auf das Original
                    // der aeltesten, falls mehrere Laeufe hintereinander bereinigt haben.
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $company = Company::query()->find($backup->company_id);

                    if ($company === null) {
                        $counts['Firma fehlt']++;

                        continue;
                    }

                    $current = (string) $company->getAttribute($backup->column_name);

                    if (! $force && $current !== $backup->cleaned_description) {
                        $counts['geaendert, uebersprungen']++;
                        $this->line("  company_id {$company->id} ({$backup->column_name}): seit der Bereinigung geaendert — uebersprungen.");

                        continue;
                    }

                    $counts['wiederhergestellt']++;

                    if (! $apply) {
                        continue;
                    }

                    DB::connection($company->getConnectionName())->transaction(function () use ($pending, $company, $backup): void {
                        $chain = $pending()
                            ->where('company_id', $company->id)
                            ->where('column_name', $backup->column_name);

                        /** @var CompanyDescriptionBackup $oldest */
                        $oldest = (clone $chain)->orderBy('id')->first();

                        $company->setAttribute($backup->column_name, $oldest->original_description);
                        $company->save();

                        $chain->update(['restored_at' => now()]);
                    });

                    TenantCache::forget("sun-v2.profile.nearby.{$company->city_id}.{$company->id}");
                }
            }, 'id');

        if ($apply && $counts['wiederhergestellt'] > 0) {
            TenantCache::forget('portal.featured_companies');
            TenantCache::forget('portal.random_companies');
        }

        $this->table(array_keys($counts), [array_values($counts)]);

        return self::SUCCESS;
    }
}
