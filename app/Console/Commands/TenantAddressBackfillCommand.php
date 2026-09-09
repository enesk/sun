<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht die Anschrift der Bestandstenants aus settings.contact_address in den
 * Adressdatensatz nach (#60).
 *
 * Seit #60 ist der Adressdatensatz (addresses, ueber Tenant::address()) die
 * Quelle der Wahrheit fuer die Adress-Platzhalter der Rechtstexte;
 * settings.contact_address ist nur noch eine Ableitung. Bei Bestandstenants ist
 * es genau umgekehrt: die Anschrift steht als freier Text im Schluessel, der
 * Adressdatensatz ist leer. Dieses Kommando dreht das um.
 *
 * Vorgabe ist der Trockenlauf — geschrieben wird nur mit --force. Ein
 * vorhandener, nicht leerer Feldwert wird nie ueberschrieben: handgepflegte
 * Angaben gewinnen immer gegen die Zerlegung eines Freitextes.
 *
 * Nicht eindeutige Quelltexte werden bewusst nur gemeldet. Eine Anschrift zu
 * raten ist bei Rechtstexten die schlechtere Wahl als eine Luecke, die jemand
 * sieht.
 */
class TenantAddressBackfillCommand extends Command
{
    protected $signature = 'tenants:address:backfill
        {--force : Erkannte Anschriften tatsaechlich schreiben (Vorgabe ist Trockenlauf)}';

    protected $description = 'Uebertraegt settings.contact_address der Bestandstenants in den Adressdatensatz';

    private const RESULT_APPLIED = 'übernommen';

    private const RESULT_PRESENT = 'bereits gefüllt';

    private const RESULT_AMBIGUOUS = 'nicht eindeutig';

    private const RESULT_NONE = 'keine Angabe';

    public function handle(TenantBrandingService $branding): int
    {
        $write = (bool) $this->option('force');

        if (! $write) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Mit --force schreiben.');
            $this->newLine();
        }

        $rows = [];
        $counts = [
            self::RESULT_APPLIED => 0,
            self::RESULT_PRESENT => 0,
            self::RESULT_AMBIGUOUS => 0,
            self::RESULT_NONE => 0,
        ];

        /** @var Tenant $tenant */
        foreach (Tenant::all() as $tenant) {
            $source = $tenant->getAttribute(TenantConfigConstants::CONTACT_ADDRESS);
            $source = is_string($source) ? trim($source) : '';

            /** @var \App\Models\Address|null $address */
            $address = $tenant->address()->first();
            $parsed = $this->parse($source);

            $result = $this->resolveResult($source, $parsed, $address);

            if ($result === self::RESULT_APPLIED && $write) {
                $values = [
                    'address_line_1' => $parsed['line1'],
                    'address_line_2' => $parsed['line2'],
                    'zip' => $parsed['zip'],
                    'city' => $parsed['city'],
                ];

                // Nur leere Felder befuellen — vorhandene Angaben gewinnen.
                $values = array_filter(
                    $values,
                    fn (?string $value, string $key): bool => $value !== null && $value !== ''
                        && $this->isEmpty($address?->getAttribute($key)),
                    ARRAY_FILTER_USE_BOTH
                );

                if ($address) {
                    $address->update($values);
                } else {
                    /** @var \App\Models\Address $address */
                    $address = $tenant->address()->create($values + ['country_code' => 'DE']);
                }

                // Abgeleiteten Schluessel gleich mitziehen, damit beide Seiten
                // dieselbe Anschrift zeigen.
                $derived = $branding->buildContactAddress(
                    $address->address_line_1,
                    $address->address_line_2,
                    $address->zip,
                    $address->city,
                );

                if ($derived !== null) {
                    $branding->set($tenant, TenantConfigConstants::CONTACT_ADDRESS, $derived);
                }
            }

            $counts[$result]++;

            $rows[] = [
                $tenant->name,
                $source === '' ? '—' : str_replace("\n", ' ⏎ ', $source),
                $parsed['line1'] ?? '—',
                $parsed['zip'] ?? '—',
                $parsed['city'] ?? '—',
                $result,
            ];
        }

        $this->table(
            ['Tenant', 'Quelltext', 'Straße', 'PLZ', 'Ort', 'Befund'],
            $rows,
        );

        $this->newLine();
        $this->line('Zusammenfassung:');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if (! $write && $counts[self::RESULT_APPLIED] > 0) {
            $this->newLine();
            $this->comment("Mit --force werden {$counts[self::RESULT_APPLIED]} Anschriften geschrieben.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{line1: ?string, line2: ?string, zip: ?string, city: ?string, ambiguous: bool}  $parsed
     */
    private function resolveResult(string $source, array $parsed, ?\App\Models\Address $address): string
    {
        $addressComplete = $address
            && ! $this->isEmpty($address->address_line_1)
            && ! $this->isEmpty($address->zip)
            && ! $this->isEmpty($address->city);

        if ($addressComplete) {
            return self::RESULT_PRESENT;
        }

        if ($source === '') {
            return self::RESULT_NONE;
        }

        if ($parsed['ambiguous'] || $parsed['zip'] === null || $parsed['line1'] === null) {
            return self::RESULT_AMBIGUOUS;
        }

        return self::RESULT_APPLIED;
    }

    /**
     * Zerlegt den Freitext in Strasse, Zusatz, PLZ und Ort.
     *
     * Getrennt wird an Zeilenumbruechen UND Kommata, weil beide Schreibweisen
     * im Bestand vorkommen. Der letzte Teil muss "PLZ Ort" sein, sonst gilt der
     * Text als nicht eindeutig.
     *
     * @return array{line1: ?string, line2: ?string, zip: ?string, city: ?string, ambiguous: bool}
     */
    private function parse(string $source): array
    {
        $empty = ['line1' => null, 'line2' => null, 'zip' => null, 'city' => null, 'ambiguous' => false];

        if (trim($source) === '') {
            return $empty;
        }

        $parts = preg_split('/[\r\n,]+/u', $source) ?: [];
        $parts = array_values(array_filter(
            array_map(fn (string $part): string => trim(preg_replace('/\s+/u', ' ', $part) ?? ''), $parts),
            fn (string $part): bool => $part !== '',
        ));

        if (count($parts) < 2) {
            return ['ambiguous' => true] + $empty;
        }

        $last = array_pop($parts);

        if (preg_match('/^(\d{5})\s+(.+)$/u', $last, $matches) !== 1) {
            return ['ambiguous' => true] + $empty;
        }

        // Mehr als zwei Strassenzeilen lassen sich nicht sicher zuordnen.
        if (count($parts) > 2) {
            return ['ambiguous' => true] + $empty;
        }

        return [
            'line1' => $parts[0],
            'line2' => $parts[1] ?? null,
            'zip' => $matches[1],
            'city' => trim($matches[2]),
            'ambiguous' => false,
        ];
    }

    private function isEmpty(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }
}
