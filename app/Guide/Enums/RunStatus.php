<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Status eines Laufs durch die Job-Kette (docs/guide-system.md, Abschnitt 3).
 *
 * queued -> probing -> researching -> writing -> checking -> published
 *              |           |                       |-> review -> published
 *              '-> unchanged <-'                    '-> writing (Nachbesserung)
 *
 * create-Laeufe beginnen direkt mit researching. researching -> unchanged:
 * die Tiefenrecherche hat keinen geaenderten Fakt gefunden (#9). review -> writing ist der
 * Wiedereinstieg nach dem Sperren der Gliederung oder nach einer
 * Rueckweisung in der Pruef-Queue. published, unchanged und failed sind
 * terminal; ein Neustart ist immer ein neuer Lauf.
 */
enum RunStatus: string
{
    case QUEUED = 'queued';
    case PROBING = 'probing';
    case RESEARCHING = 'researching';
    case WRITING = 'writing';
    case CHECKING = 'checking';
    case REVIEW = 'review';
    case PUBLISHED = 'published';
    case UNCHANGED = 'unchanged';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => __('Wartet'),
            self::PROBING => __('Prüft Aktualität'),
            self::RESEARCHING => __('Recherchiert'),
            self::WRITING => __('Schreibt'),
            self::CHECKING => __('Qualitätsprüfung'),
            self::REVIEW => __('Wartet auf Prüfung'),
            self::PUBLISHED => __('Veröffentlicht'),
            self::UNCHANGED => __('Unverändert'),
            self::FAILED => __('Fehlgeschlagen'),
        };
    }

    /**
     * Filament-Farbe ohne Kenntnis des RunMode. Fuer die Anzeige eines Laufs
     * gilt RunDisplay::fromRun($status, $mode)->color() — nur dort haengt
     * "Neu erschienen" / "Aktualisiert" am Mode (design/guide-dashboard.md §2.2).
     */
    public function color(): string
    {
        return RunDisplay::fromRun($this, null)->color();
    }

    /**
     * Zustaende, in denen ein Job arbeitet; der Watchdog meldet Laeufe, die
     * laenger als guide.schedule.stuck_after_minutes darin stehen.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::PROBING, self::RESEARCHING, self::WRITING, self::CHECKING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PUBLISHED, self::UNCHANGED, self::FAILED], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::QUEUED => [self::PROBING, self::RESEARCHING, self::FAILED],
            self::PROBING => [self::RESEARCHING, self::UNCHANGED, self::FAILED],
            // unchanged: Tiefenrecherche ohne geaenderten Fakt (#9).
            self::RESEARCHING => [self::WRITING, self::UNCHANGED, self::FAILED],
            self::WRITING => [self::CHECKING, self::REVIEW, self::FAILED],
            self::CHECKING => [self::PUBLISHED, self::REVIEW, self::WRITING, self::FAILED],
            self::REVIEW => [self::WRITING, self::PUBLISHED, self::FAILED],
            self::PUBLISHED, self::UNCHANGED, self::FAILED => [],
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
