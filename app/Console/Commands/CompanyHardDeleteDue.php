<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Companies\Deletion\Models\DeletionLogEntry;
use App\Companies\Deletion\StepStatus;
use App\Models\Portal\Company;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Loescht die Profile endgueltig, deren Rueckholfrist abgelaufen ist (#8).
 *
 * Die Pipeline macht einen Soft Delete und setzt `hard_delete_after` auf
 * heute + 30 Tage (`profile-requests.deletion.hard_delete_after_days`).
 * Bis dahin ist eine Fehlbedienung rueckholbar; danach muss die Zeile weg,
 * weil `is_active=false` fuer Art. 17 nicht genuegt.
 *
 * Laeuft im Tenant-Kontext, taeglich im Scheduler:
 *   php artisan tenants:run companies:hard-delete-due
 *
 * Alles Personenbezogene ist zu diesem Zeitpunkt schon weg — Bilder,
 * Bewertungen, Statistiken hat die Pipeline sofort geloescht. Hier fallen
 * nur noch die Profilzeile und die daran haengenden Kaskaden. Die Zeilen in
 * `gone_urls` und `deletion_log` bleiben: ohne sie verlieren wir den 410 und
 * den Nachweis der Erledigung.
 */
class CompanyHardDeleteDue extends Command
{
    protected $signature = 'companies:hard-delete-due
        {--dry-run : Nur zaehlen, nichts loeschen}
        {--limit=500 : Hoechstzahl Profile je Lauf}';

    protected $description = 'Löscht soft-deletete Profile endgültig, deren Rückholfrist abgelaufen ist (im Tenant-Kontext)';

    public function handle(): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext: php artisan tenants:run companies:hard-delete-due');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('companies', 'hard_delete_after')) {
            $this->error('Spalte companies.hard_delete_after fehlt — tenants:migrate ausstehend.');

            return self::FAILURE;
        }

        $due = Company::onlyTrashed()
            ->whereNotNull('hard_delete_after')
            ->where('hard_delete_after', '<=', now())
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($due->isEmpty()) {
            $this->info('Keine Profile fällig.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Fällig: {$due->count()} Profil(e).");

            foreach ($due as $company) {
                $this->line(sprintf('  #%d %s (Frist %s)', $company->id, $company->name, $company->hard_delete_after?->format('d.m.Y')));
            }

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($due as $company) {
            try {
                $this->log($company, StepStatus::OK, 'Profil endgültig gelöscht.');
                $company->forceDelete();
                $deleted++;
            } catch (Throwable $exception) {
                $failed++;
                $this->log($company, StepStatus::FAILED, Str::limit($exception->getMessage(), 500, ''));
                $this->error("#{$company->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Endgültig gelöscht: {$deleted}, fehlgeschlagen: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Die Protokollzeile wird **vor** dem Loeschen geschrieben: danach ist
     * `$company->name` fuer den Nachweis nicht mehr zu holen.
     */
    private function log(Company $company, StepStatus $status, string $message): void
    {
        try {
            if (! Schema::hasTable('deletion_log')) {
                return;
            }

            $now = CarbonImmutable::now();

            DeletionLogEntry::query()->create([
                'run_id' => (string) Str::uuid(),
                'company_id' => $company->getKey(),
                'company_name' => $company->name,
                'step' => 'hard_delete_profile',
                'status' => $status->value,
                'message' => $message,
                'payload' => [
                    'deleted_reason' => $company->deleted_reason,
                    'deleted_at' => $company->deleted_at?->toIso8601String(),
                    'hard_delete_after' => $company->hard_delete_after?->toIso8601String(),
                ],
                'started_at' => $now,
                'finished_at' => $now,
                'created_at' => $now,
            ]);
        } catch (Throwable) {
            // Protokoll darf den Lauf nicht aufhalten; der Fehler steht im Log.
        }
    }
}
