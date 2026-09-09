<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Schreibt prozentkodierte Platzhalter in den Rechtstexten der Bestandstenants
 * auf ihre Klammerfassung zurueck (#50).
 *
 * Ursache: HTMLPurifier kodiert eckige Klammern in Attributwerten, aus
 * href="mailto:[BETREIBER_EMAIL]" wird beim Speichern im Editor
 * href="mailto:%5BBETREIBER_EMAIL%5D". Der Speicherpfad ist in
 * TenantBrandingService::sanitizeLegalHtml() repariert; dieses Kommando raeumt
 * die bereits beschaedigten Bestandsdaten auf.
 *
 * Gearbeitet wird auf dem Rohtext der Tenant-Konfiguration, also vor
 * TenantBrandingService::resolveLegalPlaceholders(); die Platzhalter bleiben im
 * gespeicherten Text stehen.
 *
 * Reine Ersetzung bekannter Platzhalter — kein allgemeines urldecode(), damit
 * uebriger Inhalt unangetastet bleibt. Tenants ohne Text werden nur gemeldet.
 */
class TenantLegalRestorePlaceholdersCommand extends Command
{
    protected $signature = 'tenants:legal:restore-placeholders
        {--dry-run : Nur melden, nichts schreiben}';

    protected $description = 'Schreibt prozentkodierte Platzhalter (%5BNAME%5D) in den Rechtstexten zurueck auf [NAME]';

    /**
     * Die betroffenen Rohtext-Felder mit ihrer Bezeichnung fuer die Ausgabe.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        TenantConfigConstants::IMPRESSUM => 'Impressum',
        TenantConfigConstants::DATENSCHUTZ => 'Datenschutz',
        TenantConfigConstants::EDITORIAL_PRINCIPLES => 'Redaktionsprinzipien',
    ];

    public function handle(TenantBrandingService $branding): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Trockenlauf — es wird nichts geschrieben.');
            $this->newLine();
        }

        $empty = [];
        $touchedTenants = 0;
        $touchedFields = 0;

        foreach (Tenant::all() as $tenant) {
            $name = (string) $tenant->getAttribute('name');
            $updates = [];
            $lines = [];

            foreach (self::FIELDS as $key => $label) {
                $raw = $tenant->getAttribute($key);

                if (! is_string($raw) || trim($raw) === '') {
                    $empty[] = "{$name} — {$label}";

                    continue;
                }

                $fixed = $branding->restoreEncodedPlaceholders($raw);

                if ($fixed === $raw) {
                    continue;
                }

                $updates[$key] = $fixed;
                $lines[] = "  {$label}: ".$this->countOccurrences($raw).' kodierte Platzhalter';
            }

            if ($updates === []) {
                continue;
            }

            $touchedTenants++;
            $touchedFields += count($updates);

            $this->line("<info>{$name}</info>");
            foreach ($lines as $line) {
                $this->line($line);
            }

            if (! $dryRun) {
                $branding->setMany($tenant, $updates);
            }
        }

        $this->newLine();
        $this->info("{$touchedTenants} Tenants, {$touchedFields} Felder betroffen.");

        if ($empty !== []) {
            $this->newLine();
            $this->comment('Ohne Text (nicht befuellt, siehe #46/#56):');
            foreach ($empty as $entry) {
                $this->line("  {$entry}");
            }
        }

        if ($dryRun && $touchedTenants > 0) {
            $this->newLine();
            $this->comment('Zum Schreiben ohne --dry-run erneut ausfuehren.');
        }

        return self::SUCCESS;
    }

    /**
     * Zaehlt die kodierten Platzhalter in einem Rohtext.
     */
    private function countOccurrences(string $raw): int
    {
        $count = 0;

        foreach (array_keys(TenantBrandingService::LEGAL_PLACEHOLDERS) as $placeholder) {
            $name = trim($placeholder, '[]');
            $count += preg_match_all('/%5B'.preg_quote($name, '/').'%5D/i', $raw);
        }

        return $count;
    }
}
