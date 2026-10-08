<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\Support\BotCandidate;
use App\Support\PersonalNameDetector;

/**
 * R1: Im Feld Firmenname steht eine Privatperson (#10).
 *
 * Die einzige Regel, die laut docs/turnstile.md §7 allein genuegt — und die
 * einzige mit 6 von 6 Treffern in den Produktionsdaten: alle
 * Selbsteintragungen auf fahrschulefinder.de der letzten 90 Tage sind
 * Fahrschueler, deren `companies.name` identisch zu `users.name` ist (§1.3 A).
 *
 * Zwei Muster, eines genuegt:
 *
 *   - Firmenname und Name des Inhabers sind derselbe Text
 *     ({@see PersonalNameDetector::isSamePerson()})
 *   - der Firmenname sieht ueberhaupt nach einem Personennamen aus
 *     ({@see PersonalNameDetector::looksPersonal()}) — zwei oder drei Woerter
 *     ohne Rechtsform, Firmenwort oder Branchenbegriff des Portals
 *
 * Beides nur ohne Places-ID: ein importierter Betrieb darf nach seinem Inhaber
 * heissen, und der Import ist keine Selbsteintragung.
 */
final class OwnerNameAsCompanyDetector implements Detector
{
    public function code(): string
    {
        return 'owner_name_as_company';
    }

    public function label(): string
    {
        return __('Firmenname ist ein Personenname');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return $candidate->isListing() && ! $candidate->hasPlacesId && filled($candidate->name);
    }

    public function matches(BotCandidate $candidate): bool
    {
        if (PersonalNameDetector::isSamePerson($candidate->name, $candidate->ownerName)) {
            return true;
        }

        return PersonalNameDetector::looksPersonal($candidate->name);
    }
}
