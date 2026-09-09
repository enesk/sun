<?php

declare(strict_types=1);

namespace App\Content\Enums;

/**
 * Rollen der Redaktions-Accounts im Content-Panel (docs/content-pipeline.md, §6).
 *
 * Zwei Rollen genuegen: der `owner` verantwortet Geld und Konfiguration,
 * der `editor` arbeitet Artikel ab. Bewusst keine Spatie-Permissions —
 * die haengen am `web`-Guard und an App\Models\User, wovon der
 * Content-Guard gerade getrennt sein soll.
 */
enum ContentRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => __('Betreiber'),
            self::EDITOR => __('Redaktion'),
        };
    }

    /**
     * Budget, Provider-Zugaenge, Prompts, Portalzuordnung — alles, was Geld
     * kostet oder die Pipeline umkonfiguriert.
     */
    public function canManageSettings(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Kostenzahlen in der Leistungsansicht (#25).
     */
    public function canSeeCosts(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Produktion und Pruefung stehen beiden Rollen offen.
     */
    public function canWorkOnPipeline(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $role): array => $carry + [$role->value => $role->label()],
            [],
        );
    }
}
