<?php

namespace App\Console\Commands\Premium;

use App\Models\Portal\CompanyLead;
use Illuminate\Console\Command;

/**
 * DSGVO (#9): loescht Kontaktdaten und Freitexte exklusiver Anfragen nach
 * Ablauf der Aufbewahrung (config leads.exclusive.retention_months). Die
 * Anfrage selbst bleibt mit Status und Auswahlantworten stehen, damit
 * Kontingent und Statistik nachvollziehbar bleiben.
 *
 * Laeuft im Tenant-Kontext: php artisan tenants:run leads:purge-contacts
 * (taeglich im Scheduler). Wiederholbar.
 */
class PurgeOldCompanyLeads extends Command
{
    protected $signature = 'leads:purge-contacts
        {--months= : Aufbewahrung in Monaten, Standard aus config leads.exclusive.retention_months}
        {--dry-run : Nur zaehlen, nichts aendern}';

    protected $description = 'Loescht Kontaktdaten exklusiver Anfragen nach Ablauf der Aufbewahrung (im Tenant-Kontext)';

    // Antworttypen ohne Freitext; alles andere kann personenbezogen sein
    private const KEPT_TYPES = ['single_choice', 'multi_choice', 'image_choice', 'consent', 'number', 'slider'];

    private const CHUNK = 500;

    public function handle(): int
    {
        if (! tenancy()->initialized) {
            $this->error('Nur im Tenant-Kontext: php artisan tenants:run leads:purge-contacts');

            return self::FAILURE;
        }

        $months = (int) ($this->option('months') ?? config('leads.exclusive.retention_months', 12));

        if ($months < 1) {
            $this->error('Aufbewahrung muss mindestens einen Monat betragen.');

            return self::FAILURE;
        }

        $query = CompanyLead::query()->dueForPurge($months);

        if ($this->option('dry-run')) {
            $this->info("Faellig: {$query->count()} Anfragen aelter als {$months} Monate.");

            return self::SUCCESS;
        }

        $purged = 0;

        $query->chunkById(self::CHUNK, function ($leads) use (&$purged): void {
            foreach ($leads as $lead) {
                /** @var CompanyLead $lead */
                $lead->forceFill([
                    'contact_name' => null,
                    'contact_email' => null,
                    'contact_phone' => null,
                    'answers' => array_values(array_filter(
                        (array) $lead->answers,
                        fn ($answer): bool => in_array($answer['type'] ?? null, self::KEPT_TYPES, true),
                    )),
                    'contact_purged_at' => now(),
                ])->save();
                $purged++;
            }
        });

        $this->info("Kontaktdaten geloescht: {$purged} Anfragen aelter als {$months} Monate.");

        return self::SUCCESS;
    }
}
