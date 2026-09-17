<?php

declare(strict_types=1);

namespace App\Console\Commands\Premium;

use App\Services\Premium\CompanyVerificationService;
use Illuminate\Console\Command;

/**
 * Loescht Verifizierungsnachweise 90 Tage nach der Entscheidung (#11).
 * Der Datensatz bleibt mit document_purged_at als Protokoll erhalten.
 *
 * Laeuft im Tenant-Kontext: php artisan tenants:run premium:purge-verification-documents
 */
class PurgeVerificationDocuments extends Command
{
    protected $signature = 'premium:purge-verification-documents
        {--days= : Aufbewahrung in Tagen, Standard: premium.verification.retention_days}
        {--dry-run : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht Verifizierungsnachweise nach Ablauf der Aufbewahrungsfrist (im Tenant-Kontext)';

    public function handle(CompanyVerificationService $service): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext: php artisan tenants:run premium:purge-verification-documents');

            return self::FAILURE;
        }

        $days = (int) ($this->option('days') ?? config('premium.verification.retention_days', 90));

        if ($days < 1) {
            $this->error('--days muss mindestens 1 sein.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $count = $service->purgeDocuments($days, $dryRun);

        $this->info(($dryRun ? 'Wuerde loeschen' : 'Geloescht').": {$count} Nachweise (Entscheidung aelter als {$days} Tage).");

        return self::SUCCESS;
    }
}
