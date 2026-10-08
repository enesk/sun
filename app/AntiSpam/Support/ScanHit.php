<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\AntiSpam\Enums\CandidateKind;
use Carbon\CarbonImmutable;

/**
 * Ein Treffer eines Laufs (#10) — fuer die Ausgabe in der Konsole und den
 * Bericht, nicht fuer die Datenbank.
 *
 * Die E-Mail steht nur zur Sichtung in der Konsole; in den Bericht
 * ({@see ScanResult::toReport()}) wandert sie nicht.
 */
final class ScanHit
{
    public function __construct(
        public readonly CandidateKind $kind,
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly BotScore $score,
        public readonly ?CarbonImmutable $createdAt,
        public readonly bool $marked,
    ) {}
}
