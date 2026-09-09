<?php

namespace App\Services;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

class TenantBrandingService
{
    /**
     * Die in Impressum, Datenschutz und Redaktionsprinzipien verfuegbaren
     * Platzhalter samt Erklaerung fuer die Oberflaeche (#50).
     *
     * @var array<string, string>
     */
    public const LEGAL_PLACEHOLDERS = [
        '[PORTAL_NAME]' => 'Name des Portals aus den allgemeinen Einstellungen, ersatzweise der Betreibername.',
        '[BETREIBER_NAME]' => 'Name des Portalbetreibers.',
        '[VERANTWORTLICH_NAME]' => 'Redaktionell verantwortliche Person (§ 18 Abs. 2 MStV), ersatzweise der Betreibername.',
        '[BETREIBER_STRASSE]' => 'Strasse und Hausnummer aus der hinterlegten Betreiberadresse.',
        '[BETREIBER_PLZ]' => 'Postleitzahl aus der hinterlegten Betreiberadresse.',
        '[BETREIBER_ORT]' => 'Ort aus der hinterlegten Betreiberadresse.',
        '[BETREIBER_EMAIL]' => 'Kontakt-E-Mail-Adresse aus den allgemeinen Einstellungen.',
        '[BETREIBER_TELEFON]' => 'Kontakt-Telefonnummer, ersatzweise die Telefonnummer der Betreiberadresse.',
    ];

    /**
     * Im Editor erlaubtes HTML. Rechtstexte werden in den Templates mit
     * {!! !!} ausgegeben, deshalb muss der Text beim Speichern durch clean().
     */
    private const LEGAL_ALLOWED_HTML = 'p,br,strong,em,u,h1,h2,h3,h4,ul,ol,li,a[href|target|rel],table,thead,tbody,tr,th,td';

    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $value = $tenant->getAttribute($key);

        if ($value === null) {
            return $default ?? (TenantConfigConstants::DEFAULTS[$key] ?? null);
        }

        return $value;
    }

    public function set(Tenant $tenant, string $key, mixed $value): void
    {
        $tenant->setAttribute($key, $value);
        $tenant->save();
    }

    public function setMany(Tenant $tenant, array $data): void
    {
        foreach ($data as $key => $value) {
            $tenant->setAttribute($key, $value);
        }

        $tenant->save();
    }

    public function getAll(Tenant $tenant): array
    {
        $result = [];

        $reflection = new \ReflectionClass(TenantConfigConstants::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $key) {
            if (is_string($key) && str_contains($key, '.')) {
                $result[$key] = $this->get($tenant, $key);
            }
        }

        return $result;
    }

    public function getFooterText(Tenant $tenant): string
    {
        $text = $this->get($tenant, TenantConfigConstants::FOOTER_TEXT);

        if ($text === null) {
            return '';
        }

        return str_replace(
            ['{year}', '{tenant_name}'],
            [date('Y'), $tenant->name],
            $text
        );
    }

    /**
     * Bereinigt einen Rechtstext aus dem Editor und haelt dabei die Platzhalter
     * unversehrt (#50).
     *
     * HTMLPurifier prozentkodiert eckige Klammern in Attributwerten, aus
     * href="mailto:[BETREIBER_EMAIL]" wird href="mailto:%5BBETREIBER_EMAIL%5D".
     * resolveLegalPlaceholders() trifft diese Fassung nicht mehr, der Link
     * fuehrt ins Leere. Deshalb wird die kodierte Schreibweise direkt nach dem
     * Bereinigen wieder zurueckgeschrieben — gespeichert wird ausschliesslich
     * die eckige Klammerfassung.
     */
    public function sanitizeLegalHtml(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        return $this->restoreEncodedPlaceholders(
            clean($content, ['HTML.Allowed' => self::LEGAL_ALLOWED_HTML])
        );
    }

    /**
     * Schreibt prozentkodierte Platzhalter (%5BNAME%5D) auf ihre Klammerfassung
     * zurueck. Bewusst nur fuer die bekannten Platzhalter und nicht als
     * allgemeines urldecode(), damit uebriger Inhalt unangetastet bleibt.
     */
    public function restoreEncodedPlaceholders(string $content): string
    {
        $search = [];
        $replace = [];

        foreach (array_keys(self::LEGAL_PLACEHOLDERS) as $placeholder) {
            $name = trim($placeholder, '[]');

            foreach (['%5B', '%5b'] as $open) {
                foreach (['%5D', '%5d'] as $close) {
                    $search[] = $open.$name.$close;
                    $replace[] = $placeholder;
                }
            }
        }

        return str_replace($search, $replace, $content);
    }

    public function resolveLegalPlaceholders(string $content, Tenant $tenant): string
    {
        $address = $tenant->address()->first();

        $replacements = [
            '[PORTAL_NAME]' => $this->get($tenant, TenantConfigConstants::SITE_TITLE) ?: $tenant->name,
            '[BETREIBER_NAME]' => $tenant->name,
            '[VERANTWORTLICH_NAME]' => $this->get($tenant, TenantConfigConstants::RESPONSIBLE_NAME) ?: $tenant->name,
            '[BETREIBER_STRASSE]' => $address?->address_line_1 ?? '',
            '[BETREIBER_PLZ]' => $address?->zip ?? '',
            '[BETREIBER_ORT]' => $address?->city ?? '',
            '[BETREIBER_EMAIL]' => $this->get($tenant, TenantConfigConstants::CONTACT_EMAIL) ?? '',
            '[BETREIBER_TELEFON]' => $this->get($tenant, TenantConfigConstants::CONTACT_PHONE) ?? $address?->phone ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Setzt die abgeleitete Anschrift fuer settings.contact_address zusammen
     * (#60). Format: "Strasse\nPLZ Ort", ein gesetzter Adresszusatz steht als
     * eigene Zeile dazwischen.
     *
     * Quelle der Wahrheit ist immer der Adressdatensatz — dieser Schluessel ist
     * nur noch eine Ableitung fuer bestehende Leser und wird selbst nie mehr
     * gelesen, um eine Anschrift zu ermitteln.
     */
    public function buildContactAddress(?string $line1, ?string $line2, ?string $zip, ?string $city): ?string
    {
        $line1 = trim((string) $line1);
        $line2 = trim((string) $line2);
        $zip = trim((string) $zip);
        $city = trim((string) $city);

        if ($line1 === '' || $zip === '' || $city === '') {
            return null;
        }

        $lines = [$line1];

        if ($line2 !== '') {
            $lines[] = $line2;
        }

        $lines[] = "{$zip} {$city}";

        return implode("\n", $lines);
    }

    /**
     * Zustand der Anschrift eines Tenants fuer Verwaltung und Admin (#60).
     *
     * placeholdersUsed prueft den ROHTEXT von Impressum und Datenschutz, also
     * vor resolveLegalPlaceholders() — nach der Aufloesung waeren die
     * Platzhalter gerade dann verschwunden, wenn sie leer gerendert haben.
     *
     * @return array{hasAddress: bool, placeholdersUsed: bool, needsAttention: bool, formatted: ?string}
     */
    public function legalAddressStatus(Tenant $tenant): array
    {
        /** @var \App\Models\Address|null $address */
        $address = $tenant->address()->first();

        $formatted = $this->buildContactAddress(
            $address?->address_line_1,
            $address?->address_line_2,
            $address?->zip,
            $address?->city,
        );

        $hasAddress = $formatted !== null;

        $raw = '';
        foreach ([TenantConfigConstants::IMPRESSUM, TenantConfigConstants::DATENSCHUTZ] as $key) {
            $value = $tenant->getAttribute($key);

            if (is_string($value)) {
                $raw .= $value;
            }
        }

        $placeholdersUsed = false;
        foreach (['[BETREIBER_STRASSE]', '[BETREIBER_PLZ]', '[BETREIBER_ORT]'] as $placeholder) {
            if (str_contains($raw, $placeholder)) {
                $placeholdersUsed = true;

                break;
            }
        }

        return [
            'hasAddress' => $hasAddress,
            'placeholdersUsed' => $placeholdersUsed,
            'needsAttention' => $placeholdersUsed && ! $hasAddress,
            'formatted' => $formatted,
        ];
    }

    public function isFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        return (bool) $this->get($tenant, $featureKey, false);
    }

    public function handleFileUpload(Tenant $tenant, string $key, $file): ?string
    {
        if (! in_array($key, TenantConfigConstants::FILE_FIELDS)) {
            return null;
        }

        // Delete old file if exists
        $oldPath = $this->get($tenant, $key);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        // Store new file in tenant-isolated directory
        $path = $file->store("tenants/{$tenant->uuid}/branding", 'public');

        $this->set($tenant, $key, $path);

        return $path;
    }

    public function deleteFile(Tenant $tenant, string $key): void
    {
        if (! in_array($key, TenantConfigConstants::FILE_FIELDS)) {
            return;
        }

        $path = $this->get($tenant, $key);
        if ($path) {
            Storage::disk('public')->delete($path);
            $this->set($tenant, $key, null);
        }
    }
}
