<?php

declare(strict_types=1);

namespace App\Ads\Dto;

/**
 * Der vollstaendige Plan der Altbestands-Uebernahme (Ticket #15).
 *
 * Trennung von Plan und Ausfuehrung ist der ganze Zweck: derselbe Plan wird
 * im Trockenlauf gedruckt und im Ernstfall geschrieben, damit #11 den
 * Umstieg je Portal vorher nachvollziehen kann.
 *
 * `notes` sammelt alles, was **nicht** uebernommen wird — abgeschaltete
 * Alt-Slots, Positionen ohne Entsprechung im Katalog, Code ohne
 * AdSense-Anzeigenblock, nicht erreichbare Portal-Datenbanken. Die Liste ist
 * Teil des Ergebnisses, nicht eine Warnung am Rand: sie ist die Abnahmeliste.
 */
final readonly class LegacyTakeoverPlan
{
    /**
     * @param  list<LegacyAdUnitPlan>  $units
     * @param  list<LegacyAdSettingPlan>  $settings
     * @param  list<array{tenant: string, position: string, reason: string}>  $notes
     */
    public function __construct(
        public array $units,
        public array $settings,
        public array $notes,
        public int $legacySlotsSeen,
        public int $legacySlotsTaken,
    ) {}

    public function isEmpty(): bool
    {
        return $this->units === [] && $this->settings === [];
    }

    /** Portale, die mindestens eine Belegung bekommen. */
    public function tenantCount(): int
    {
        $ids = [];

        foreach ($this->units as $unit) {
            foreach ($unit->tenantIds as $id) {
                $ids[$id] = true;
            }
        }

        return count($ids);
    }
}
