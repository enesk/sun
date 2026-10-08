<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\BotCandidate;

/**
 * R3 und R4: Konto ohne Double-Opt-in, und das seit Tagen (#10).
 *
 * Trifft zu, wenn `email_verified_at` fehlt, das Konto aelter ist als
 * `antispam.suspected_bots.unverified_after_days` (Vorgabe 7 Tage) und
 * zusaetzlich eines von beiden gilt:
 *
 *   R3: nie eingeloggt (`last_seen_at` ist leer)
 *   R4: kein Firmeneintrag und kein Claim-Antrag — das Konto hat nichts getan
 *
 * Die Zusatzbedingung ist der Kern: 69 % der Anmeldungen haben keine
 * Bestaetigung, aber 97 % der Angemeldeten waren danach eingeloggt
 * (docs/turnstile.md §1.2). Ohne sie waere die Regel ein Treffer auf zwei
 * Dritteln des Bestands. Deshalb traegt sie auch nur das kleinste Gewicht und
 * braucht immer eine zweite Regel, um die Schwelle zu erreichen.
 *
 * Gilt nur fuer Konten: ein Firmeneintrag hat keine DOI.
 */
final class UnverifiedAfterDaysDetector implements Detector
{
    public function code(): string
    {
        return 'unverified_after_days';
    }

    public function label(): string
    {
        return __('keine E-Mail-Bestätigung, nie genutzt');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return $candidate->isAccount();
    }

    public function matches(BotCandidate $candidate): bool
    {
        if ($candidate->verifiedAt !== null) {
            return false;
        }

        $age = $candidate->ageInDays();

        if ($age === null || $age < $this->days()) {
            return false;
        }

        if ($candidate->lastSeenAt === null) {
            return true;
        }

        return ! $candidate->hasListing && ! $candidate->hasClaim;
    }

    private function days(): int
    {
        return max(1, AntiSpamConfig::int('suspected_bots.unverified_after_days', 7));
    }
}
