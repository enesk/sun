<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Top-Platzierung kann nicht vergeben werden (#6): kein Entitlement,
 * kein freier Slot oder der Betrieb hat den Slot bereits.
 */
class FeaturedPlacementUnavailableException extends RuntimeException
{
    public static function notEntitled(int $companyId): self
    {
        return new self("Betrieb {$companyId} hat keine Freischaltung fuer Top-Platzierungen.");
    }

    public static function noFreeSlot(int $cityId, int $categoryId): self
    {
        return new self("Kein freier Top-Platzierungs-Slot in Stadt {$cityId} / Branche {$categoryId}.");
    }

    public static function alreadyBooked(int $companyId, int $cityId, int $categoryId): self
    {
        return new self("Betrieb {$companyId} hat in Stadt {$cityId} / Branche {$categoryId} bereits eine Top-Platzierung.");
    }
}
