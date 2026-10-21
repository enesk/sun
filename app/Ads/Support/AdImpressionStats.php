<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Enums\AdDevice;
use App\Ads\Models\AdImpressionDaily;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Auswertung des Tagesaggregats fuer das Filament-Widget (Ticket #12,
 * docs/ad-slots.md 14).
 *
 * Liest ausschliesslich `ad_impression_daily` — nie den Stundenpuffer: der
 * ist ein Arbeitsvorrat und waere je nach Zeitpunkt des letzten Jobs halb
 * gefuellt. Die Zahlen des Widgets sind damit bis zur letzten
 * abgeschlossenen Stunde vollstaendig und danach stabil.
 *
 * 60 Sekunden gecacht, wie beim Bot-Schutz-Widget: das Dashboard laedt die
 * Kacheln bei jedem Filterwechsel neu, die Summen aendern sich aber nur
 * stuendlich.
 */
final class AdImpressionStats
{
    /** Zeitfenster, die das Widget anbietet (Tage). */
    public const WINDOWS = [7, 30];

    private const CACHE_SECONDS = 60;

    /**
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, delivered: int, viewability: int|null, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>}
     */
    public static function summary(?int $tenantId, int $days): array
    {
        $days = in_array($days, self::WINDOWS, true) ? $days : self::WINDOWS[0];

        // Heute mitgezaehlt: 7 Tage sind der laufende plus sechs davor.
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        return self::cached($tenantId, $from, $days, 'ads:impressions:summary:'.($tenantId ?? 'all').':'.$days);
    }

    /**
     * Wie {@see self::summary()}, nur mit frei gesetztem Fensteranfang statt
     * einer der beiden Breiten aus {@see self::WINDOWS} (Ticket #66).
     *
     * Gebraucht wird das, wenn ein Zeitraum vollstaendig hinter einem
     * bestimmten Ereignis liegen muss — etwa hinter dem Deploy des Nenners der
     * Viewability-Quote: ein 7-Tage-Fenster enthaelt dann noch Tage ohne
     * Nenner und liefert eine zu hohe Quote. Das Widget bietet diesen Weg
     * nicht an und bleibt bei WINDOWS; er steht nur am Kommando
     * `ads:impressionen-abnahme --ab=`.
     *
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, delivered: int, viewability: int|null, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>}
     */
    public static function summarySince(?int $tenantId, Carbon $from): array
    {
        $von = $from->copy()->startOfDay();
        $days = max(1, (int) $von->diffInDays(Carbon::now()->startOfDay()) + 1);

        return self::cached(
            $tenantId,
            $von,
            $days,
            'ads:impressions:summary:'.($tenantId ?? 'all').':ab:'.$von->toDateString(),
        );
    }

    /**
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, delivered: int, viewability: int|null, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>}
     */
    private static function cached(?int $tenantId, Carbon $from, int $days, string $schluessel): array
    {
        return Cache::remember(
            $schluessel,
            self::CACHE_SECONDS,
            fn (): array => self::build($tenantId, $from->toDateString(), $days),
        );
    }

    /**
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, delivered: int, viewability: int|null, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int, delivered: int, viewability: int|null}>}
     */
    private static function build(?int $tenantId, string $from, int $days): array
    {
        $slots = self::grouped($tenantId, $from, 'slot_key');
        $portals = $tenantId === null ? self::grouped(null, $from, 'tenant_id') : [];
        $namen = $tenantId === null && $portals !== []
            ? Tenant::query()->whereKey(array_keys($portals))->pluck('name', 'id')->all()
            : [];

        $slotZeilen = [];

        foreach ($slots as $slotKey => $geraete) {
            $slotZeilen[] = [
                'slot_key' => (string) $slotKey,
                'label' => (string) (AdSlotCatalog::find((string) $slotKey)['label'] ?? $slotKey),
                'mobile' => $geraete[AdDevice::MOBILE->value],
                'desktop' => $geraete[AdDevice::DESKTOP->value],
                'total' => $geraete['total'],
                'delivered' => $geraete['delivered'],
                'viewability' => self::quote($geraete['total'], $geraete['delivered']),
            ];
        }

        usort($slotZeilen, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $portalZeilen = [];

        foreach ($portals as $id => $geraete) {
            $portalZeilen[] = [
                'tenant_id' => (int) $id,
                'name' => (string) ($namen[$id] ?? __('Portal :id', ['id' => $id])),
                'mobile' => $geraete[AdDevice::MOBILE->value],
                'desktop' => $geraete[AdDevice::DESKTOP->value],
                'total' => $geraete['total'],
                'delivered' => $geraete['delivered'],
                'viewability' => self::quote($geraete['total'], $geraete['delivered']),
            ];
        }

        usort($portalZeilen, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $total = array_sum(array_column($slotZeilen, 'total'));
        $delivered = array_sum(array_column($slotZeilen, 'delivered'));

        return [
            'days' => $days,
            'from' => $from,
            'total' => $total,
            'mobile' => array_sum(array_column($slotZeilen, 'mobile')),
            'desktop' => array_sum(array_column($slotZeilen, 'desktop')),
            'delivered' => $delivered,
            'viewability' => self::quote($total, $delivered),
            'slots' => $slotZeilen,
            'portals' => $portalZeilen,
        ];
    }

    /**
     * Viewability-Quote in Prozent: Sichtkontakte je ausgeliefertes Fragment
     * (Ticket #47, Entscheidungsregel docs/ad-slots.md 13.3).
     *
     * `null` heisst "kein Nenner" und wird in der Darstellung zu einem
     * Gedankenstrich — nicht zu 0 %. Das ist der Fall fuer jeden Zeitraum, der
     * noch Tage vor der Einfuehrung des Nenners enthaelt: dort steht
     * `deliveries = 0`, obwohl Sichtkontakte gezaehlt wurden. Eine 0 % waere
     * dort eine falsche Auskunft, und ausgerechnet die Abschaltregel haengt an
     * dieser Zahl.
     *
     * Ueber 100 % wird gekappt: Auslieferung und Sichtkontakt koennen in
     * verschiedene Tage fallen (Fragment um 23:59 eingesetzt, Sichtkontakt um
     * 00:00), und am Rand des Fensters liegt dann der Zaehler ohne seinen
     * Nenner.
     */
    private static function quote(int $viewable, int $delivered): ?int
    {
        if ($delivered <= 0) {
            return null;
        }

        return (int) min(100, round($viewable / $delivered * 100));
    }

    /**
     * Summen je $spalte und Geraet, beide Zaehler. Die Auslieferungen werden
     * nur in die Gesamtspalte summiert — eine Trennung Mobil/Desktop der
     * Quote braucht das Widget nicht, und die Spalten `impressions` und
     * `deliveries` stehen in dieser Reihenfolge am Ende der abdeckenden
     * Indizes ({@see docs/ad-slots-security-review.md} 13.7), damit die
     * Abfrage Index-only bleibt.
     *
     * Das Geraet steht im Aggregat immer als
     * `mobile` oder `desktop` ({@see AdImpressionRecorder}); eine andere
     * Auspraegung kaeme nur aus Altdaten und wird darum nicht einsortiert.
     *
     * @return array<array-key, array{mobile: int, desktop: int, total: int, delivered: int}>
     */
    private static function grouped(?int $tenantId, string $from, string $spalte): array
    {
        $zeilen = DB::connection(AdImpressionRecorder::connection())
            ->table((new AdImpressionDaily)->getTable())
            ->where('day', '>=', $from)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->selectRaw("{$spalte} as gruppe, device, sum(impressions) as impressions, sum(deliveries) as deliveries")
            ->groupBy('gruppe', 'device')
            ->get();

        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $gruppe = $zeile->gruppe;
            $ergebnis[$gruppe] ??= [AdDevice::MOBILE->value => 0, AdDevice::DESKTOP->value => 0, 'total' => 0, 'delivered' => 0];

            $menge = (int) $zeile->impressions;
            $ergebnis[$gruppe]['total'] += $menge;
            $ergebnis[$gruppe]['delivered'] += (int) $zeile->deliveries;

            if (array_key_exists((string) $zeile->device, $ergebnis[$gruppe])) {
                $ergebnis[$gruppe][(string) $zeile->device] += $menge;
            }
        }

        return $ergebnis;
    }
}
