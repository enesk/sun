<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Constants\TenantOperatorDefaults;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Setzt die restlichen Betreiberangaben bei Bestandstenants nach (#78).
 *
 * Gegenstueck zu tenants:address:seed (#69), das dasselbe fuer die Anschrift
 * tut. Nach jenem Lauf war die Anschrift ueberall gepflegt, doch
 * tenants:legal:seed (#56) uebersprang weiterhin 18 Tenants, weil drei weitere
 * Angaben fehlten: Kontakt-E-Mail, Telefon und die redaktionell
 * verantwortliche Person.
 *
 * Alle drei sind netzweite Betreiberangaben, keine portalspezifischen Werte:
 *
 * - Die E-Mail stand bereits fest im CreateTenantCommand, galt also fuer jeden
 *   neu angelegten Tenant.
 * - Das Telefon steht seit #69 als TenantOperatorDefaults::PHONE im Repository.
 * - Die verantwortliche Person stand bis #48 im Impressumstext jedes Portals
 *   und wurde dort nur durch einen Platzhalter ersetzt.
 *
 * Sie stehen deshalb jetzt gemeinsam in App\Constants\TenantOperatorDefaults.
 * Geraten wuerde erst ein je Portal unterschiedlicher Wert — den schreibt
 * dieses Kommando nie.
 *
 * Regeln:
 *
 * 1. Ein vorhandener, nicht leerer Wert wird nie ueberschrieben. Ausnahme sind
 *    die in PLACEHOLDER_VALUES genannten offensichtlichen Testwerte
 *    (test@example.com bei Tenant "Sanitaer"); die zaehlen als nicht gepflegt.
 * 2. Geschrieben wird ausschliesslich mit --write, Vorgabe ist der Trockenlauf.
 */
class TenantContactSeedCommand extends Command
{
    protected $signature = 'tenants:contact:seed
        {--write : Aenderungen tatsaechlich schreiben (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}';

    protected $description = 'Setzt Kontakt-E-Mail, Telefon und verantwortliche Person bei Tenants ohne eigene Angaben';

    private const RESULT_WRITTEN = 'Angaben gesetzt';

    private const RESULT_COMPLETED = 'Felder ergänzt';

    private const RESULT_PRESENT = 'bereits vollständig';

    /**
     * Formulierung fuer den Trockenlauf je Befund.
     *
     * @var array<string, string>
     */
    private const PENDING_LABELS = [
        self::RESULT_WRITTEN => 'Angaben würden gesetzt',
        self::RESULT_COMPLETED => 'Felder würden ergänzt',
    ];

    /**
     * Klartextbezeichnung je Konfigurationsschluessel fuer die Ausgabe.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        TenantConfigConstants::CONTACT_EMAIL => 'Kontakt-E-Mail',
        TenantConfigConstants::CONTACT_PHONE => 'Telefon',
        TenantConfigConstants::RESPONSIBLE_NAME => 'verantwortliche Person',
    ];

    /**
     * Werte, die zwar gesetzt sind, aber keine echte Angabe darstellen. Sie
     * werden wie ein leeres Feld behandelt und ueberschrieben. Bewusst eine
     * kurze, namentliche Liste statt einer Heuristik.
     *
     * @var array<int, string>
     */
    private const PLACEHOLDER_VALUES = [
        'test@example.com',
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
        $counts = [
            self::RESULT_WRITTEN => 0,
            self::RESULT_COMPLETED => 0,
            self::RESULT_PRESENT => 0,
        ];

        foreach ($tenants as $tenant) {
            $missing = $this->missingFields($branding, $tenant);

            if ($missing === []) {
                $counts[self::RESULT_PRESENT]++;
                $rows[] = [$tenant->name, $this->describe($branding, $tenant), '—', self::RESULT_PRESENT];

                continue;
            }

            // Gar nichts gepflegt heisst "Angaben gesetzt", teilweise gefuellt
            // heisst "Felder ergaenzt" — der Unterschied ist fuer die Abnahme
            // wichtig.
            $result = count($missing) === count(self::defaults())
                ? self::RESULT_WRITTEN
                : self::RESULT_COMPLETED;

            $before = $this->describe($branding, $tenant);

            if ($write) {
                $branding->setMany($tenant, $missing);
            }

            $counts[$result]++;
            $rows[] = [
                $tenant->name,
                $before,
                implode(', ', array_map(static fn (string $key): string => self::LABELS[$key], array_keys($missing))),
                $write ? $result : self::PENDING_LABELS[$result],
            ];
        }

        $this->table(['Tenant', 'Angaben vorher', 'Ergänzte Felder', 'Ergebnis'], $rows);

        $this->newLine();
        $this->line('Zusammenfassung:');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        $pending = $counts[self::RESULT_WRITTEN] + $counts[self::RESULT_COMPLETED];

        if (! $write && $pending > 0) {
            $this->newLine();
            $this->comment("Mit --write werden {$pending} Tenants gepflegt.");
        }

        if ($write && $pending > 0) {
            $this->newLine();
            $this->line('Rechtstexte lassen sich jetzt mit "tenants:legal:seed --write" (#56) nachziehen.');
        }

        return self::SUCCESS;
    }

    /**
     * Vorgabewerte des Betreibers je Konfigurationsschluessel.
     *
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return [
            TenantConfigConstants::CONTACT_EMAIL => TenantOperatorDefaults::EMAIL,
            TenantConfigConstants::CONTACT_PHONE => TenantOperatorDefaults::PHONE,
            TenantConfigConstants::RESPONSIBLE_NAME => TenantOperatorDefaults::RESPONSIBLE_NAME,
        ];
    }

    /**
     * Felder, die bei diesem Tenant leer sind oder nur einen Testwert tragen.
     *
     * @return array<string, string> Schluessel => zu setzender Vorgabewert
     */
    private function missingFields(TenantBrandingService $branding, Tenant $tenant): array
    {
        return array_filter(
            self::defaults(),
            fn (string $value, string $key): bool => $this->blank($branding->get($tenant, $key)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function describe(TenantBrandingService $branding, Tenant $tenant): string
    {
        $parts = [];

        foreach (array_keys(self::defaults()) as $key) {
            $value = $branding->get($tenant, $key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            // Testwerte werden ueberschrieben, sollen aber sichtbar bleiben —
            // sonst stuende in der Spalte "keine", obwohl etwas dranstand.
            $parts[] = $this->blank($value)
                ? trim($value).' (Testwert)'
                : trim($value);
        }

        return $parts === [] ? 'keine' : implode(', ', $parts);
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        /** @var array<int, string> $filter */
        $filter = array_filter((array) $this->option('tenant'), static fn ($v): bool => (string) $v !== '');

        /** @var Collection<int, Tenant> $tenants */
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

    private function blank(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        return in_array(mb_strtolower(trim($value)), self::PLACEHOLDER_VALUES, true);
    }
}
