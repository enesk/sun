<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\DisposableDomainList;
use App\AntiSpam\Support\BotCandidate;

/**
 * R6: Die Mail-Domain steht auf der Wegwerf-Sperrliste (#10).
 *
 * Dieselbe Liste wie die Pruefung am Formular ({@see DisposableDomainList}),
 * also Repo-Datei plus Portal-Ergaenzungen plus Ausnahmen. Der Lauf findet
 * damit genau die Adressen, die heute nicht mehr durch die Anmeldung kaemen.
 *
 * Gilt fuer Konten und fuer Eintraege: ein Eintrag traegt eine eigene
 * Kontaktadresse, die nicht die des Kontos sein muss.
 */
final class DisposableDomainDetector implements Detector
{
    public function __construct(
        private readonly DisposableDomainList $domains,
    ) {}

    public function code(): string
    {
        return 'disposable_domain';
    }

    public function label(): string
    {
        return __('Wegwerf-Mail-Domain');
    }

    public function supports(BotCandidate $candidate): bool
    {
        return filled($candidate->email);
    }

    public function matches(BotCandidate $candidate): bool
    {
        return $this->domains->blocks($candidate->email);
    }
}
