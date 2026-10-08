<?php

declare(strict_types=1);

namespace App\AntiSpam\Enums;

/**
 * Was geprueft wird (#10): ein Konto aus der zentralen `users` oder ein
 * Firmeneintrag aus der `companies` eines Portals.
 *
 * Die Unterscheidung steht hier und nicht in einem Flag, weil jede Regel
 * ({@see \App\AntiSpam\Detectors\Detector::supports()}) selbst entscheidet,
 * fuer welche Art sie gilt — ein leeres Firmenprofil sagt ueber ein Konto
 * nichts, eine fehlende DOI-Bestaetigung ueber einen Eintrag nichts.
 */
enum CandidateKind: string
{
    case Account = 'account';
    case Listing = 'listing';

    public function label(): string
    {
        return match ($this) {
            self::Account => __('Konto'),
            self::Listing => __('Eintrag'),
        };
    }
}
