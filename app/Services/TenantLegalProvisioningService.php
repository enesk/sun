<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\TenantConfigConstants;
use App\Constants\TenantLegalDefaults;
use App\Constants\TenantOperatorDefaults;
use App\Models\Address;
use App\Models\Tenant;

/**
 * Hinterlegt die Betreiberangaben und die Standard-Rechtstexte bei einem
 * neu angelegten Tenant (#50).
 *
 * Bis #50 taten das nur CreateTenantCommand und die Nachzugskommandos
 * (tenants:address:seed, tenants:contact:seed, tenants:legal:seed). Jeder
 * andere Anlageweg — Filament-Oberflaeche, CreateWorkspace, Checkout — liess
 * Anschrift, Kontakt und beide Rechtstexte leer, und das Portal ging ohne
 * Impressum und ohne Datenschutzerklaerung online. Weil der
 * Turnstile-Backfill (#11) nur an vorhandenen Text anhaengt, fehlte dort
 * zusaetzlich der Pflichtabschnitt zum Drittanbieter-Skript.
 *
 * Diese Klasse ist die einzige Stelle, die die Vorgaben bei einer Neuanlage
 * schreibt. Sie gilt fuer alle Wege: TenantCreationService (Oberflaechen und
 * Checkout), die Filament-Seite CreateTenant und CreateTenantCommand.
 *
 * Die Regeln sind dieselben wie in den Nachzugskommandos:
 *
 * 1. Ein vorhandener, nicht leerer Wert wird nie ueberschrieben.
 * 2. Rechtstexte werden als ROHTEXT mit stehenden Platzhaltern geschrieben;
 *    aufgeloest werden sie erst beim Rendern in
 *    TenantBrandingService::resolveLegalPlaceholders().
 * 3. Rechtstexte nur, wenn die Betreiberangaben vollstaendig sind — ein
 *    Impressum mit leerer Anschrift ist schlechter als keines, weil es
 *    vollstaendig aussieht.
 * 4. Geschrieben wird ueber TenantBrandingService::set() / setMany().
 */
final class TenantLegalProvisioningService
{
    public function __construct(
        private readonly TenantBrandingService $branding,
    ) {}

    /**
     * Vorgabewerte des Betreibers je Adressfeld. Deckungsgleich mit
     * TenantAddressSeedCommand, zusaetzlich die Telefonnummer — die gab
     * CreateTenantCommand neu angelegten Tenants schon immer mit.
     *
     * @return array<string, string>
     */
    public static function addressDefaults(): array
    {
        return [
            'address_line_1' => TenantOperatorDefaults::ADDRESS_LINE_1,
            'zip' => TenantOperatorDefaults::ZIP,
            'city' => TenantOperatorDefaults::CITY,
            'country_code' => TenantOperatorDefaults::COUNTRY_CODE,
            'phone' => TenantOperatorDefaults::PHONE,
        ];
    }

    /**
     * Vorgabewerte des Betreibers je Konfigurationsschluessel.
     *
     * @return array<string, string>
     */
    public static function contactDefaults(): array
    {
        return [
            TenantConfigConstants::CONTACT_EMAIL => TenantOperatorDefaults::EMAIL,
            TenantConfigConstants::CONTACT_PHONE => TenantOperatorDefaults::PHONE,
            TenantConfigConstants::RESPONSIBLE_NAME => TenantOperatorDefaults::RESPONSIBLE_NAME,
        ];
    }

    /**
     * Die Rechtstexte mit Bezeichnung und Standardinhalt.
     *
     * @return array<string, array{label: string, text: string}>
     */
    public static function legalDefaults(): array
    {
        return [
            TenantConfigConstants::IMPRESSUM => [
                'label' => 'Impressum',
                'text' => TenantLegalDefaults::IMPRESSUM,
            ],
            TenantConfigConstants::DATENSCHUTZ => [
                'label' => 'Datenschutz',
                'text' => TenantLegalDefaults::DATENSCHUTZ,
            ],
        ];
    }

    /**
     * Setzt alles, was bei diesem Tenant fehlt.
     *
     * Wirft nie: eine fehlgeschlagene Provisionierung darf die Tenant-Anlage
     * nicht kippen. Was gesetzt wurde, steht im Rueckgabewert; was fehlschlug,
     * landet als Warnung im Log.
     *
     * @return array<int, string> Klartext der gesetzten Angaben, fuer die Ausgabe
     */
    public function provision(Tenant $tenant): array
    {
        try {
            $done = array_merge(
                $this->provisionAddress($tenant),
                $this->provisionContact($tenant),
            );

            // Erst nach Anschrift und Kontakt, damit die Vollstaendigkeits-
            // pruefung der Rechtstexte die eben gesetzten Werte sieht.
            return array_merge($done, $this->provisionLegalTexts($tenant));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TenantLegalProvisioningService: Provisionierung fehlgeschlagen', [
                'tenant_id' => $tenant->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function provisionAddress(Tenant $tenant): array
    {
        /** @var Address|null $address */
        $address = $tenant->address()->first();

        $missing = array_filter(
            self::addressDefaults(),
            fn (string $value, string $field): bool => $this->blank($address?->getAttribute($field)),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return [];
        }

        if ($address) {
            $address->update($missing);
        } else {
            /** @var Address $address */
            $address = $tenant->address()->create($missing);
        }

        $this->syncContactAddress($tenant, $address);

        return ['Anschrift'];
    }

    /**
     * @return array<int, string>
     */
    private function provisionContact(Tenant $tenant): array
    {
        $missing = array_filter(
            self::contactDefaults(),
            fn (string $value, string $key): bool => $this->blank($this->branding->get($tenant, $key)),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return [];
        }

        $this->branding->setMany($tenant, $missing);

        return ['Kontaktangaben'];
    }

    /**
     * @return array<int, string>
     */
    private function provisionLegalTexts(Tenant $tenant): array
    {
        $missing = array_filter(
            self::legalDefaults(),
            fn (array $field, string $key): bool => ! $this->hasText($tenant, $key),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return [];
        }

        if ($this->missingOperatorData($tenant) !== []) {
            return [];
        }

        $written = [];

        foreach ($missing as $key => $field) {
            $this->branding->set($tenant, $key, $field['text']);
            $written[] = $field['label'];
        }

        return $written;
    }

    /**
     * Betreiberangaben, ohne die die Platzhalter des Standardtextes leer
     * rendern wuerden. Dieselbe Pruefung wie in TenantLegalSeedCommand.
     *
     * @return array<int, string>
     */
    private function missingOperatorData(Tenant $tenant): array
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

        if ($this->blank($this->branding->get($tenant, TenantConfigConstants::CONTACT_EMAIL))) {
            $missing[] = 'Kontakt-E-Mail';
        }

        // [BETREIBER_TELEFON] faellt auf die Telefonnummer der Adresse zurueck.
        if ($this->blank($this->branding->get($tenant, TenantConfigConstants::CONTACT_PHONE)) && $this->blank($address?->phone)) {
            $missing[] = 'Telefon';
        }

        if ($this->blank($this->branding->get($tenant, TenantConfigConstants::RESPONSIBLE_NAME))) {
            $missing[] = 'verantwortliche Person';
        }

        return $missing;
    }

    /**
     * Zieht die abgeleitete Anschrift in settings.contact_address nach, aber
     * nur wenn dort noch nichts steht — ein gepflegter Freitext bleibt stehen.
     */
    private function syncContactAddress(Tenant $tenant, Address $address): void
    {
        if (! $this->blank($this->branding->get($tenant, TenantConfigConstants::CONTACT_ADDRESS))) {
            return;
        }

        $derived = $this->branding->buildContactAddress(
            $address->address_line_1,
            $address->address_line_2,
            $address->zip,
            $address->city,
        );

        if ($derived === null) {
            return;
        }

        $this->branding->set($tenant, TenantConfigConstants::CONTACT_ADDRESS, $derived);
    }

    private function hasText(Tenant $tenant, string $key): bool
    {
        $value = $this->branding->get($tenant, $key);

        return is_string($value) && trim(strip_tags($value)) !== '';
    }

    private function blank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }
}
