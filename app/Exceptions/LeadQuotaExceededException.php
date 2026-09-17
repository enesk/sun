<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Das Kontingent exklusiver Anfragen ist fuer den laufenden Monat verbraucht (#10).
 */
class LeadQuotaExceededException extends RuntimeException
{
    public static function forCompany(int $companyId, string $period): self
    {
        return new self("Lead-Kontingent von Betrieb {$companyId} fuer {$period} ist verbraucht.");
    }
}
