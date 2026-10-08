<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

/**
 * Ergebnis einer Bewertung (#10): Punktwert und die Regeln, die ihn ergeben.
 *
 * Der Wert ist auf 100 gedeckelt — er ist eine Rangfolge fuer die Sichtung,
 * keine Wahrscheinlichkeit. Die Gruende tragen ihr Gewicht mit, damit in
 * Filament nachvollziehbar bleibt, woraus der Wert entstanden ist, auch wenn
 * die Gewichte in config/antispam.php spaeter anders stehen.
 */
final class BotScore
{
    /**
     * @param  list<array{code: string, label: string, weight: int}>  $reasons
     */
    public function __construct(
        public readonly int $score,
        public readonly array $reasons,
    ) {}

    public static function none(): self
    {
        return new self(0, []);
    }

    public function isSuspect(int $threshold): bool
    {
        return $this->reasons !== [] && $this->score >= $threshold;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (array $reason): string => $reason['code'], $this->reasons);
    }

    /** Einzeilige Begruendung fuer Konsole und Tabelle. */
    public function summary(): string
    {
        if ($this->reasons === []) {
            return '—';
        }

        return implode(', ', array_map(
            static fn (array $reason): string => "{$reason['label']} (+{$reason['weight']})",
            $this->reasons,
        ));
    }
}
