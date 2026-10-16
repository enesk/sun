<?php

declare(strict_types=1);

namespace App\Ads\Support;

/**
 * Die AdSense-Publisher-ID in ihren zwei Schreibweisen (Ticket #10).
 *
 * Gespeichert und ausgespielt wird die Form mit Praefix — `ca-pub-` plus 16
 * Ziffern —, weil genau sie als `data-ad-client` in den Anzeigenblock geht
 * (AdSenseProvider). In `ads.txt` steht dieselbe Nummer **ohne** `ca-`:
 *
 *     google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0
 *
 * Eingetragen werden darf beides; {@see normalize()} macht daraus die Form
 * mit Praefix. 16 Ziffern sind Googles Format — der Altbestand wurde mit
 * einem weicheren Muster uebernommen (`\d{10,20}`, LegacyAdTakeover), eine
 * abweichende Laenge faellt hier beim naechsten Speichern auf.
 */
final class AdSensePublisherId
{
    /** Eingabemuster: mit oder ohne `ca-`, immer 16 Ziffern. */
    public const PATTERN = '/^(ca-)?pub-\d{16}$/';

    /** Gesellschaftsschluessel der Google-Zeile in ads.txt. */
    public const ADS_TXT_RELATIONSHIP = 'DIRECT';

    /** Googles Zertifizierungs-ID; fuer alle AdSense-Konten dieselbe. */
    public const ADS_TXT_CERTIFICATION = 'f08c47fec0942fa0';

    public static function isValid(?string $value): bool
    {
        return preg_match(self::PATTERN, strtolower(trim((string) $value))) === 1;
    }

    /**
     * Speicherform `ca-pub-…`; null, wenn nichts Brauchbares drinsteht.
     */
    public static function normalize(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        return str_starts_with($value, 'ca-') ? $value : "ca-{$value}";
    }

    /**
     * Form fuer ads.txt — ohne `ca-`. Leer, wenn keine ID vorliegt.
     */
    public static function forAdsTxt(?string $value): ?string
    {
        $value = self::normalize($value);

        if ($value === null) {
            return null;
        }

        $value = str_starts_with($value, 'ca-') ? substr($value, 3) : $value;

        return $value === '' ? null : $value;
    }

    /**
     * Die vollstaendige Google-Zeile einer ads.txt; null ohne Publisher-ID.
     */
    public static function adsTxtLine(?string $value): ?string
    {
        $publisher = self::forAdsTxt($value);

        if ($publisher === null) {
            return null;
        }

        return sprintf(
            'google.com, %s, %s, %s',
            $publisher,
            self::ADS_TXT_RELATIONSHIP,
            self::ADS_TXT_CERTIFICATION,
        );
    }
}
