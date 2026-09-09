<?php

declare(strict_types=1);

namespace App\Content\Enums;

/**
 * Lebenszyklus eines Themenkandidaten in der Content-Pipeline.
 *
 * discovered -> scored -> selected | reserve | rejected
 *                         reserve  -> selected | rejected
 *
 * 'reserve' ist die Warteliste der Tagesauswahl (#12): Kandidaten, die es
 * knapp nicht in die Tagesplanung geschafft haben und beim Fehlschlag einer
 * Generierung ohne neuen Modellaufruf nachruecken.
 */
enum TopicStatus: string
{
    case DISCOVERED = 'discovered';
    case SCORED = 'scored';
    case SELECTED = 'selected';
    case RESERVE = 'reserve';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DISCOVERED => __('Gefunden'),
            self::SCORED => __('Bewertet'),
            self::SELECTED => __('Ausgewählt'),
            self::RESERVE => __('Reserve'),
            self::REJECTED => __('Verworfen'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DISCOVERED => 'gray',
            self::SCORED => 'info',
            self::SELECTED => 'success',
            self::RESERVE => 'gray',
            self::REJECTED => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::SELECTED, self::REJECTED], true);
    }

    /**
     * Erlaubte Folgezustände. Jeder Übergang ausserhalb dieser Liste ist ein Fehler.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DISCOVERED => [self::SCORED, self::REJECTED],
            self::SCORED => [self::SELECTED, self::RESERVE, self::REJECTED],
            self::RESERVE => [self::SELECTED, self::REJECTED],
            self::SELECTED, self::REJECTED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
