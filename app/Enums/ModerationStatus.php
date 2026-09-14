<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moderationsstatus fuer Nutzerinhalte (#13).
 *
 * Werte bewusst identisch zur KasernenCheck-Moderationspipeline, damit eine
 * spaetere KI-Pruefung als Queue-Job ohne Datenumbau andocken kann.
 * Oeffentlich sichtbar ist ausschliesslich Approved.
 */
enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsReview = 'needs_review';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Status, die noch eine Entscheidung brauchen.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::NeedsReview->value];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }

    public static function labelFor(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? (string) $value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ausstehend',
            self::Approved => 'Freigegeben',
            self::Rejected => 'Abgelehnt',
            self::NeedsReview => 'Zu prüfen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::NeedsReview => 'info',
        };
    }
}
