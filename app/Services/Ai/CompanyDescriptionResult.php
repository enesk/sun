<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Ergebnis von CompanyDescriptionGenerator::generate().
 *
 * generated – $description ist gepruefter Plaintext und darf gespeichert werden.
 * failed    – auch der zweite Versuch war nach der Bereinigung zu kurz.
 * error     – der Modellaufruf selbst ist gescheitert (kein Markieren, naechster Lauf versucht es erneut).
 */
final readonly class CompanyDescriptionResult
{
    public const GENERATED = 'generated';

    public const FAILED = 'failed';

    public const ERROR = 'error';

    /**
     * @param  list<string>  $matchedPatterns
     */
    public function __construct(
        public string $status,
        public ?string $description,
        public int $attempts,
        public int $promptVersion,
        public array $matchedPatterns = [],
    ) {}

    public function generated(): bool
    {
        return $this->status === self::GENERATED;
    }
}
