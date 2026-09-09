<?php

declare(strict_types=1);

namespace App\Content\Enums;

/**
 * Lebenszyklus eines Artikelentwurfs in der Content-Pipeline.
 *
 * generating -> generated -> checking -> approved -> scheduled -> published
 *                                     \-> review (manuelle Prüfung) -> approved | failed
 *                                     \-> failed (nach erschöpften Retries)
 */
enum DraftStatus: string
{
    case GENERATING = 'generating';
    case GENERATED = 'generated';
    case CHECKING = 'checking';
    case APPROVED = 'approved';
    case REVIEW = 'review';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::GENERATING => __('Wird erstellt'),
            self::GENERATED => __('Erstellt'),
            self::CHECKING => __('In Prüfung'),
            self::APPROVED => __('Freigegeben'),
            self::REVIEW => __('Manuelle Prüfung'),
            self::SCHEDULED => __('Eingeplant'),
            self::PUBLISHED => __('Veröffentlicht'),
            self::FAILED => __('Fehlgeschlagen'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GENERATING, self::CHECKING => 'gray',
            self::GENERATED => 'info',
            self::APPROVED, self::SCHEDULED => 'warning',
            self::PUBLISHED => 'success',
            self::REVIEW => 'primary',
            self::FAILED => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PUBLISHED, self::FAILED], true);
    }

    /**
     * Zustände, die auf menschliches Eingreifen warten und deshalb im Dashboard
     * in der Prüf-Queue auftauchen.
     */
    public function needsHumanAction(): bool
    {
        return in_array($this, [self::REVIEW, self::FAILED], true);
    }

    /**
     * Zustände, in denen ein Job aktiv arbeitet. Ein Entwurf, der laenger als
     * config('content.pipeline.stuck_after_minutes') hier haengt, gilt als haengend.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::GENERATING, self::CHECKING], true);
    }

    /**
     * Erlaubte Folgezustände. Jeder Übergang ausserhalb dieser Liste ist ein Fehler.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::GENERATING => [self::GENERATED, self::FAILED],
            self::GENERATED => [self::CHECKING, self::FAILED],
            self::CHECKING => [self::APPROVED, self::REVIEW, self::GENERATING, self::FAILED],
            self::APPROVED => [self::SCHEDULED, self::REVIEW, self::FAILED],
            self::REVIEW => [self::APPROVED, self::GENERATING, self::FAILED],
            self::SCHEDULED => [self::PUBLISHED, self::REVIEW, self::FAILED],
            self::PUBLISHED => [self::GENERATING],
            self::FAILED => [self::GENERATING],
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
