<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\CompanyInquiry;
use Illuminate\Console\Command;

/**
 * DSGVO (#32 Premium): loescht Kontaktdaten und Antworten der Anfragen aus dem
 * Leadsystem nach Ablauf der Aufbewahrung (config leads.exclusive.retention_months),
 * nach dem Muster von leads:purge-contacts. Die Antworten tragen keinen
 * Feldtyp, koennen also Freitext enthalten und werden deshalb ganz geleert.
 * Status, Score und Eingangsdatum bleiben stehen; geloeschte (soft deleted)
 * Anfragen werden mit bereinigt.
 *
 * Laeuft im Tenant-Kontext: php artisan tenants:run leads:inquiries:purge-contacts
 * (taeglich im Scheduler). Wiederholbar.
 */
class PurgeOldCompanyInquiries extends Command
{
    protected $signature = 'leads:inquiries:purge-contacts
        {--months= : Aufbewahrung in Monaten, Standard aus config leads.exclusive.retention_months}
        {--dry-run : Nur zaehlen, nichts aendern}';

    protected $description = 'Loescht Kontaktdaten der Webhook-Anfragen nach Ablauf der Aufbewahrung (im Tenant-Kontext)';

    private const CHUNK = 500;

    public function handle(): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext: php artisan tenants:run leads:inquiries:purge-contacts');

            return self::FAILURE;
        }

        $months = (int) ($this->option('months') ?? config('leads.exclusive.retention_months', 12));

        if ($months < 1) {
            $this->error('Aufbewahrung muss mindestens einen Monat betragen.');

            return self::FAILURE;
        }

        $query = CompanyInquiry::withTrashed()->dueForPurge($months);

        if ($this->option('dry-run')) {
            $this->info("Faellig: {$query->count()} Anfragen aelter als {$months} Monate.");

            return self::SUCCESS;
        }

        $purged = 0;

        $query->chunkById(self::CHUNK, function ($inquiries) use (&$purged): void {
            foreach ($inquiries as $inquiry) {
                /** @var CompanyInquiry $inquiry */
                $inquiry->forceFill([
                    'contact_name' => null,
                    'contact_email' => null,
                    'contact_phone' => null,
                    'answers' => [],
                    'contact_purged_at' => now(),
                ])->save();
                $purged++;
            }
        });

        $this->info("Kontaktdaten geloescht: {$purged} Anfragen aelter als {$months} Monate.");

        return self::SUCCESS;
    }
}
