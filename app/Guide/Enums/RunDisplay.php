<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Anzeigewert eines Laufs im Dashboard (design/guide-dashboard.md §2.2).
 *
 * Die Oberflaeche zeigt sieben Werte statt der neun RunStatus-Werte: die
 * vier Arbeitsschritte teilen sich "In Arbeit", "Veroeffentlicht" haengt vom
 * RunMode ab. Einzige Stelle fuer diese Abbildung — Tabellen, Filter und
 * Detailseiten rufen nur fromRun() auf. Der Wert ist zugleich der
 * URL-Parameter `lauf=`.
 */
enum RunDisplay: string
{
    case QUEUED = 'wartet';
    case IN_PROGRESS = 'in-arbeit';
    case REVIEW = 'pruefung';
    case CREATED = 'neu';
    case UPDATED = 'aktualisiert';
    case UNCHANGED = 'unveraendert';
    case FAILED = 'fehlgeschlagen';

    public static function fromRun(RunStatus $status, ?RunMode $mode): self
    {
        return match ($status) {
            RunStatus::QUEUED => self::QUEUED,
            RunStatus::PROBING, RunStatus::RESEARCHING, RunStatus::WRITING, RunStatus::CHECKING => self::IN_PROGRESS,
            RunStatus::REVIEW => self::REVIEW,
            RunStatus::PUBLISHED => $mode === RunMode::CREATE ? self::CREATED : self::UPDATED,
            RunStatus::UNCHANGED => self::UNCHANGED,
            RunStatus::FAILED => self::FAILED,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => __('Wartet'),
            self::IN_PROGRESS => __('In Arbeit'),
            self::REVIEW => __('Zur Prüfung'),
            self::CREATED => __('Neu erschienen'),
            self::UPDATED => __('Aktualisiert'),
            self::UNCHANGED => __('Geprüft, unverändert'),
            self::FAILED => __('Fehlgeschlagen'),
        };
    }

    /**
     * Modifikator der Pille .content-status--* (resources/css/content/theme.css).
     */
    public function pill(): string
    {
        return match ($this) {
            self::QUEUED => 'idea',
            self::IN_PROGRESS => 'generating',
            self::REVIEW => 'review',
            self::CREATED, self::UPDATED => 'published',
            self::UNCHANGED => 'unchanged',
            self::FAILED => 'failed',
        };
    }

    /**
     * Filament-Farbe des Badges (config('content.colors')); gleicher Ton wie
     * die Pille aus pill(). "Unveraendert" ist nie grau.
     */
    public function color(): string
    {
        return "status-{$this->pill()}";
    }

    /**
     * Rangfolge beim Sortieren, schlechtestes zuerst (§2.2).
     */
    public function rank(): int
    {
        return match ($this) {
            self::FAILED => 0,
            self::REVIEW => 1,
            self::IN_PROGRESS => 2,
            self::QUEUED => 3,
            self::CREATED => 4,
            self::UPDATED => 5,
            self::UNCHANGED => 6,
        };
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
