<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ziel einer Anfrage aus dem Profil-Dialog (#9), entschieden von
 * App\Services\Premium\LeadRoutingService.
 */
enum LeadRoute: string
{
    // Nur an den Betrieb, gespeichert als CompanyLead, nicht im Leadsystem
    case Exclusive = 'exclusive';

    // Bisheriger Weg ueber den widileads-Marktplatz inkl. Opt-in fuer weitere Betriebe
    case Marketplace = 'marketplace';

    public function isExclusive(): bool
    {
        return $this === self::Exclusive;
    }
}
