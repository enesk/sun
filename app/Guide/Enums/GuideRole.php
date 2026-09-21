<?php

declare(strict_types=1);

namespace App\Guide\Enums;

/**
 * Rolle im Ratgeber-Dashboard (Panel `content`, #14).
 *
 * owner  darf alles: Tageslauf, Themen, Pruefung, Verlauf, Einstellungen, Kosten.
 * editor pflegt Themen und arbeitet die Pruefung ab; Einstellungen und
 *        Kostenzahlen sieht er nicht.
 *
 * Gespeichert in users.guide_role. Administratoren (users.is_admin) ohne
 * eigene Rolle gelten als owner, damit der Umbau niemanden aussperrt
 * (docs/guide-system.md, §7).
 */
enum GuideRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => __('Inhaber'),
            self::EDITOR => __('Redaktion'),
        };
    }

    public function canManageSettings(): bool
    {
        return $this === self::OWNER;
    }

    public function canSeeCosts(): bool
    {
        return $this === self::OWNER;
    }
}
