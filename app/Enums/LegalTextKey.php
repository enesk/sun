<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Die Textarten, die zentral fuer alle Portale gepflegt werden (Ticket #17).
 *
 * Die Werte sind die Schluessel in `legal_texts.key` und gleichzeitig die
 * Feldnamen im Panel-Formular. Sie heissen bewusst wie die bisherigen
 * Tenant-Schluessel ({@see \App\Constants\TenantConfigConstants::IMPRESSUM}),
 * damit die Zuordnung bei der Uebernahme offensichtlich bleibt.
 */
enum LegalTextKey: string
{
    case IMPRESSUM = 'impressum';
    case DATENSCHUTZ = 'datenschutz';
    case EDITORIAL_PRINCIPLES = 'editorial_principles';

    public function label(): string
    {
        return match ($this) {
            self::IMPRESSUM => __('Impressum'),
            self::DATENSCHUTZ => __('Datenschutzerklärung'),
            self::EDITORIAL_PRINCIPLES => __('Redaktionelle Grundsätze'),
        };
    }

    /**
     * Der Tenant-Schluessel, unter dem derselbe Text bisher je Portal lag.
     * Gebraucht fuer die Uebernahme (#18) und fuer die schreibgeschuetzte
     * Wiedergabe in der Portal-Verwaltung.
     */
    public function tenantConfigKey(): string
    {
        return match ($this) {
            self::IMPRESSUM => \App\Constants\TenantConfigConstants::IMPRESSUM,
            self::DATENSCHUTZ => \App\Constants\TenantConfigConstants::DATENSCHUTZ,
            self::EDITORIAL_PRINCIPLES => \App\Constants\TenantConfigConstants::EDITORIAL_PRINCIPLES,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
