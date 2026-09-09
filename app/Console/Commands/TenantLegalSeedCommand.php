<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Constants\TenantLegalDefaults;
use App\Models\Address;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht den bereinigten Standard-Rechtstext (#48) bei Bestandstenants nach, die
 * bis heute weder Impressum noch Datenschutzerklaerung hinterlegt haben (#56).
 *
 * Geschrieben wird der Rohtext aus App\Constants\TenantLegalDefaults, also mit
 * stehenden Platzhaltern; aufgeloest werden sie erst beim Rendern in
 * TenantBrandingService::resolveLegalPlaceholders().
 *
 * Zwei Regeln bestimmen das Verhalten:
 *
 * 1. Ein Tenant wird nur befuellt, wenn Anschrift, Kontakt-E-Mail, Telefon und
 *    die redaktionell verantwortliche Person gepflegt sind. Sonst loesten die
 *    Platzhalter zu Leerstellen auf, und ein Impressum mit leerer Anschrift ist
 *    schlechter als gar keines: es sieht vollstaendig aus. Unvollstaendige
 *    Tenants werden namentlich gemeldet, nie halb befuellt.
 * 2. Vorhandener Text wird niemals ueberschrieben. Impressum und Datenschutz
 *    werden dabei getrennt betrachtet — wer nur eines von beiden gepflegt hat,
 *    bekommt das andere nachgezogen.
 *
 * Trockenlauf ist die Voreinstellung; geschrieben wird ausschliesslich mit
 * --write.
 */
class TenantLegalSeedCommand extends Command
{
    protected $signature = 'tenants:legal:seed
        {--write : Aenderungen tatsaechlich schreiben (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}';

    protected $description = 'Setzt den Standardtext fuer Impressum und Datenschutz bei Tenants ohne Text — nur wenn Betreiberangaben vollstaendig sind';

    /**
     * Die nachzuziehenden Felder mit Bezeichnung und Standardtext.
     *
     * @var array<string, array{label: string, text: string}>
     */
    private const FIELDS = [
        TenantConfigConstants::IMPRESSUM => [
            'label' => 'Impressum',
            'text' => TenantLegalDefaults::IMPRESSUM,
        ],
        TenantConfigConstants::DATENSCHUTZ => [
            'label' => 'Datenschutz',
            'text' => TenantLegalDefaults::DATENSCHUTZ,
        ],
    ];

    public function handle(TenantBrandingService $branding): int
    {
        $write = (bool) $this->option('write');

        if (! $write) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Zum Schreiben: --write');
            $this->newLine();
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine passenden Tenants gefunden.');

            return self::FAILURE;
        }

        $rows = [];
        $incomplete = [];
        $counts = [
            'befüllt' => 0,
            'vollständig — nichts zu tun' => 0,
            'übersprungen — Betreiberangaben unvollständig' => 0,
        ];

        foreach ($tenants as $tenant) {
            $name = (string) $tenant->name;
            $missingFields = $this->missingLegalTexts($branding, $tenant);

            if ($missingFields === []) {
                $counts['vollständig — nichts zu tun']++;
                $rows[] = [$name, 'vorhanden', 'vorhanden', 'nichts zu tun'];

                continue;
            }

            $missingData = $this->missingOperatorData($branding, $tenant);

            if ($missingData !== []) {
                $counts['übersprungen — Betreiberangaben unvollständig']++;
                $incomplete[$name] = $missingData;
                $rows[] = [
                    $name,
                    $this->state($branding, $tenant, TenantConfigConstants::IMPRESSUM),
                    $this->state($branding, $tenant, TenantConfigConstants::DATENSCHUTZ),
                    'übersprungen — fehlt: '.implode(', ', $missingData),
                ];

                continue;
            }

            $written = [];

            foreach ($missingFields as $key) {
                if ($write) {
                    $branding->set($tenant, $key, self::FIELDS[$key]['text']);
                }

                $written[] = self::FIELDS[$key]['label'];
            }

            $counts['befüllt']++;
            $rows[] = [
                $name,
                $this->state($branding, $tenant, TenantConfigConstants::IMPRESSUM),
                $this->state($branding, $tenant, TenantConfigConstants::DATENSCHUTZ),
                ($write ? 'befüllt: ' : 'würde befüllen: ').implode(', ', $written),
            ];
        }

        $this->table(['Tenant', 'Impressum', 'Datenschutz', 'Ergebnis'], $rows);

        $this->newLine();
        $this->info('Zusammenfassung');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if ($incomplete !== []) {
            $this->newLine();
            $this->warn('Übersprungen, weil Betreiberangaben fehlen (bitte zuerst pflegen):');
            foreach ($incomplete as $name => $missing) {
                $this->line("  - {$name}: ".implode(', ', $missing));
            }
            $this->newLine();
            $this->line('Anschriften lassen sich mit "tenants:address:backfill" (#60, aus vorhandenem Freitext)');
            $this->line('oder "tenants:address:seed" (#69, Betreiberanschrift) nachziehen.');
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function tenants(): \Illuminate\Support\Collection
    {
        /** @var array<int, string> $filter */
        $filter = array_filter((array) $this->option('tenant'), static fn ($v): bool => (string) $v !== '');

        /** @var \Illuminate\Support\Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->get();

        if ($filter === []) {
            return $tenants;
        }

        return $tenants->filter(static function (Tenant $tenant) use ($filter): bool {
            foreach ($filter as $needle) {
                if ((string) $tenant->id === (string) $needle
                    || (string) $tenant->uuid === (string) $needle
                    || mb_strtolower((string) $tenant->name) === mb_strtolower((string) $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Konfigurationsschluessel der Rechtstexte, die bei diesem Tenant fehlen.
     *
     * @return array<int, string>
     */
    private function missingLegalTexts(TenantBrandingService $branding, Tenant $tenant): array
    {
        $missing = [];

        foreach (array_keys(self::FIELDS) as $key) {
            if (! $this->hasText($branding, $tenant, $key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Betreiberangaben, ohne die die Platzhalter des Standardtextes leer
     * rendern wuerden.
     *
     * @return array<int, string> Klartextbezeichnungen der fehlenden Angaben
     */
    private function missingOperatorData(TenantBrandingService $branding, Tenant $tenant): array
    {
        /** @var Address|null $address */
        $address = $tenant->address()->first();

        $missing = [];

        if ($this->blank($address?->address_line_1)) {
            $missing[] = 'Straße';
        }

        if ($this->blank($address?->zip)) {
            $missing[] = 'PLZ';
        }

        if ($this->blank($address?->city)) {
            $missing[] = 'Ort';
        }

        if ($this->blank($branding->get($tenant, TenantConfigConstants::CONTACT_EMAIL))) {
            $missing[] = 'Kontakt-E-Mail';
        }

        // [BETREIBER_TELEFON] faellt auf die Telefonnummer der Adresse zurueck.
        if ($this->blank($branding->get($tenant, TenantConfigConstants::CONTACT_PHONE)) && $this->blank($address?->phone)) {
            $missing[] = 'Telefon';
        }

        if ($this->blank($branding->get($tenant, TenantConfigConstants::RESPONSIBLE_NAME))) {
            $missing[] = 'verantwortliche Person';
        }

        return $missing;
    }

    private function state(TenantBrandingService $branding, Tenant $tenant, string $key): string
    {
        return $this->hasText($branding, $tenant, $key) ? 'vorhanden' : 'leer';
    }

    private function hasText(TenantBrandingService $branding, Tenant $tenant, string $key): bool
    {
        $value = $branding->get($tenant, $key);

        return is_string($value) && trim(strip_tags($value)) !== '';
    }

    private function blank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }
}
