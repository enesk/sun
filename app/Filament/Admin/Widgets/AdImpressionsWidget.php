<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Ads\Support\AdImpressionRecorder;
use App\Ads\Support\AdImpressionStats;
use App\Ads\Support\AdsAdminAccess;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Eigene Impressionen je Anker und Portal (Ticket #12, docs/ad-slots.md 14).
 *
 * Zeigt die letzten 7 oder 30 Tage aus `ad_impression_daily`, je Anker
 * getrennt nach Mobil und Desktop, dazu die Summen je Portal. Damit ist ein
 * Anker datenbasiert zu bewerten — unabhaengig vom AdSense-Reporting und auch
 * fuer Direktbuchungen ueber den HtmlProvider, fuer die es dort gar keine
 * Zahlen gibt.
 *
 * Seit Ticket #47 steht neben den Sichtkontakten der Nenner (ausgelieferte
 * Fragmente) und damit die Viewability-**Quote** — die Groesse, an der die
 * Entscheidungsregel aus docs/ad-slots.md 13.3 haengt (unter 40 % abschalten,
 * ueber 70 % ausweiten). Vorher war sie nur im AdSense-Konto zu holen, und
 * das kennt nur den Anzeigenblock, nicht den Anker.
 *
 * Das Portal kommt aus dem Dashboard-Filter `tenant_id` (dieselbe Quelle wie
 * beim {@see PremiumRevenueWidget}); mit Portalfilter entfaellt die
 * Portaltabelle, weil sie dann nur eine Zeile haette. Das Zeitfenster hat das
 * Widget selbst, weil der Dashboard-Filter ein Jahr vorgibt und das fuer
 * Impressionen die falsche Groessenordnung ist.
 *
 * Sichtbar nur fuer Betreiber des Netzes ({@see AdsAdminAccess}) und nur,
 * solange ueberhaupt gezaehlt wird — bei abgeschaltetem Zaehlen waere eine
 * Tabelle voller Nullen eine falsche Auskunft.
 */
class AdImpressionsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.admin.widgets.ad-impressions';

    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = 'full';

    /** Zeitfenster in Tagen; die Knoepfe in der View setzen es. */
    public int $days = 7;

    public static function canView(): bool
    {
        return AdImpressionRecorder::enabled() && AdsAdminAccess::allowed();
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, AdImpressionStats::WINDOWS, true)
            ? $days
            : AdImpressionStats::WINDOWS[0];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenantId = filled($this->pageFilters['tenant_id'] ?? null)
            ? (int) $this->pageFilters['tenant_id']
            : null;

        return [
            'windows' => AdImpressionStats::WINDOWS,
            'tenantId' => $tenantId,
            'summary' => AdImpressionStats::summary($tenantId, $this->days),
        ];
    }
}
