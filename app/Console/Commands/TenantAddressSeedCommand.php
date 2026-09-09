<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Constants\TenantOperatorDefaults;
use App\Models\Address;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Setzt die Betreiberanschrift bei Bestandstenants ohne Anschrift (#69).
 *
 * Abgrenzung zu tenants:address:backfill (#60): jenes Kommando ZERLEGT einen
 * vorhandenen Freitext aus settings.contact_address und schreibt ihn in den
 * Adressdatensatz. Es half genau einem Tenant, weil bei den uebrigen kein
 * Quelltext existierte. Dieses Kommando ergaenzt den anderen Fall: gar keine
 * Quelle vorhanden — dann gilt die Anschrift des Netzbetreibers aus
 * App\Constants\TenantOperatorDefaults, dieselbe, die CreateTenantCommand
 * jedem neu angelegten Tenant mitgibt.
 *
 * Das ist ausdruecklich kein Raten: alle Portale dieses Netzes gehoeren
 * demselben Betreiber, und dessen Anschrift steht als Vorgabe im Repository.
 * Geraten wuerde erst eine je Portal unterschiedliche Anschrift — die schreibt
 * dieses Kommando nie.
 *
 * Regeln:
 *
 * 1. Ein vorhandener, nicht leerer Feldwert wird nie ueberschrieben. Ein Tenant
 *    mit eigener, abweichender Anschrift bleibt unangetastet; fehlende
 *    Einzelfelder werden ergaenzt.
 * 2. Geschrieben wird ausschliesslich mit --write, Vorgabe ist der Trockenlauf.
 * 3. settings.contact_address wird als Ableitung mitgezogen, damit beide Seiten
 *    dieselbe Anschrift zeigen (Quelle der Wahrheit bleibt der Adressdatensatz).
 */
class TenantAddressSeedCommand extends Command
{
    protected $signature = 'tenants:address:seed
        {--write : Aenderungen tatsaechlich schreiben (ohne diesen Schalter nur Trockenlauf)}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID oder Name)}';

    protected $description = 'Setzt die Betreiberanschrift bei Tenants ohne eigene Anschrift';

    private const RESULT_WRITTEN = 'Anschrift gesetzt';

    private const RESULT_COMPLETED = 'Felder ergänzt';

    private const RESULT_PRESENT = 'bereits vollständig';

    /**
     * Formulierung fuer den Trockenlauf je Befund.
     *
     * @var array<string, string>
     */
    private const PENDING_LABELS = [
        self::RESULT_WRITTEN => 'Anschrift würde gesetzt',
        self::RESULT_COMPLETED => 'Felder würden ergänzt',
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
            /** @var Address|null $address */
            $address = $tenant->address()->first();

            $missing = $this->missingFields($address);

            if ($missing === []) {
                $counts[self::RESULT_PRESENT]++;
                $rows[] = [$tenant->name, $this->describe($address), '—', self::RESULT_PRESENT];

                continue;
            }

            // Ganz leer oder gar nicht vorhanden heisst "Anschrift gesetzt",
            // teilweise gefuellt heisst "Felder ergaenzt" — der Unterschied ist
            // fuer die Abnahme wichtig.
            $result = count($missing) === count(self::defaults())
                ? self::RESULT_WRITTEN
                : self::RESULT_COMPLETED;

            $before = $this->describe($address);

            if ($write) {
                $address = $this->apply($tenant, $address, $missing);
                $this->syncContactAddress($branding, $tenant, $address);
            }

            $counts[$result]++;
            $rows[] = [
                $tenant->name,
                $before,
                implode(', ', array_keys($missing)),
                $write ? $result : self::PENDING_LABELS[$result],
            ];
        }

        $this->table(['Tenant', 'Anschrift vorher', 'Ergänzte Felder', 'Ergebnis'], $rows);

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

        return self::SUCCESS;
    }

    /**
     * Vorgabewerte des Betreibers je Adressfeld.
     *
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return [
            'address_line_1' => TenantOperatorDefaults::ADDRESS_LINE_1,
            'zip' => TenantOperatorDefaults::ZIP,
            'city' => TenantOperatorDefaults::CITY,
            'country_code' => TenantOperatorDefaults::COUNTRY_CODE,
        ];
    }

    /**
     * Adressfelder, die bei diesem Tenant leer sind.
     *
     * @return array<string, string> Feldname => zu setzender Vorgabewert
     */
    private function missingFields(?Address $address): array
    {
        return array_filter(
            self::defaults(),
            fn (string $value, string $field): bool => $this->blank($address?->getAttribute($field)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    private function apply(Tenant $tenant, ?Address $address, array $values): Address
    {
        if ($address) {
            $address->update($values);

            return $address;
        }

        /** @var Address $created */
        $created = $tenant->address()->create($values);

        return $created;
    }

    /**
     * Zieht die abgeleitete Anschrift in settings.contact_address nach.
     */
    private function syncContactAddress(TenantBrandingService $branding, Tenant $tenant, Address $address): void
    {
        $derived = $branding->buildContactAddress(
            $address->address_line_1,
            $address->address_line_2,
            $address->zip,
            $address->city,
        );

        if ($derived === null) {
            return;
        }

        $branding->set($tenant, TenantConfigConstants::CONTACT_ADDRESS, $derived);
    }

    private function describe(?Address $address): string
    {
        if (! $address) {
            return 'kein Datensatz';
        }

        $parts = array_filter([
            trim((string) $address->address_line_1),
            trim(trim((string) $address->zip).' '.trim((string) $address->city)),
        ], static fn (string $part): bool => $part !== '');

        return $parts === [] ? 'leer' : implode(', ', $parts);
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
        return ! is_string($value) || trim($value) === '';
    }
}
