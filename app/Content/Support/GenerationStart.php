<?php

declare(strict_types=1);

namespace App\Content\Support;

/**
 * Ergebnis einer Sofort-Erzeugung (#42, §1).
 *
 * Ein blosser Text kann die beiden Erfolgsausgaenge nicht unterscheiden: der
 * Generatorjob liegt in der Queue, oder er fehlt und es bleibt beim
 * vorgemerkten Zustand. Beides ist ein Erfolg, aber nur das erste ist eine
 * gruene Meldung. Der abgelehnte Ausgang bleibt die Ausnahme.
 */
final class GenerationStart
{
    private function __construct(
        public readonly bool $queued,
        public readonly string $message,
    ) {}

    public static function queued(string $message): self
    {
        return new self(true, $message);
    }

    public static function deferred(string $message): self
    {
        return new self(false, $message);
    }
}
