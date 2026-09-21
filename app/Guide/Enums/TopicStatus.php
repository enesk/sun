<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Lebenszyklus eines Ratgeber-Themas (docs/guide-system.md, Abschnitt 3).
 *
 * draft -> outline_pending -> active <-> paused
 * Jeder Zustand ausser archived kann archiviert werden; archived -> draft
 * holt ein Thema zurueck, die gesperrte Gliederung bleibt dabei erhalten.
 */
enum TopicStatus: string
{
    case DRAFT = 'draft';
    case OUTLINE_PENDING = 'outline_pending';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Entwurf'),
            self::OUTLINE_PENDING => __('Gliederung offen'),
            self::ACTIVE => __('Aktiv'),
            self::PAUSED => __('Pausiert'),
            self::ARCHIVED => __('Archiviert'),
        };
    }

    /**
     * Filament-Farbe des Badges (config('content.colors'), design/guide-dashboard.md
     * §2.1). "Pausiert" traegt die Toene von status-scheduled, als status-paused
     * registriert, damit auch das Badge das Pausenzeichen statt des Punkts zeigt.
     * Archivieren ist keine Stoerung.
     */
    public function color(): string
    {
        return "status-{$this->pill()}";
    }

    /**
     * Modifikator der Pille .content-status--* (resources/css/content/theme.css).
     */
    public function pill(): string
    {
        return match ($this) {
            self::DRAFT => 'idea',
            self::OUTLINE_PENDING => 'review',
            self::ACTIVE => 'published',
            self::PAUSED => 'paused',
            self::ARCHIVED => 'archived',
        };
    }

    /**
     * Nur diese Themen nimmt DispatchDueTopicsJob auf: draft fuer den ersten
     * Lauf (Recherche + Gliederungsvorschlag), active fuer faellige Pruefungen.
     */
    public function isDispatchable(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::OUTLINE_PENDING, self::ACTIVE, self::ARCHIVED],
            self::OUTLINE_PENDING => [self::ACTIVE, self::DRAFT, self::ARCHIVED],
            self::ACTIVE => [self::PAUSED, self::ARCHIVED],
            self::PAUSED => [self::ACTIVE, self::ARCHIVED],
            self::ARCHIVED => [self::DRAFT],
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
