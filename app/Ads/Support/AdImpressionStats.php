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
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int}>}
     */
    public static function summary(?int $tenantId, int $days): array
    {
        $days = in_array($days, self::WINDOWS, true) ? $days : self::WINDOWS[0];
        $schluessel = 'ads:impressions:summary:'.($tenantId ?? 'all').':'.$days;

        return Cache::remember($schluessel, self::CACHE_SECONDS, fn (): array => self::build($tenantId, $days));
    }

    /**
     * @return array{days: int, from: string, total: int, mobile: int, desktop: int, slots: list<array{slot_key: string, label: string, mobile: int, desktop: int, total: int}>, portals: list<array{tenant_id: int, name: string, mobile: int, desktop: int, total: int}>}
     */
    private static function build(?int $tenantId, int $days): array
    {
        // Heute mitgezaehlt: 7 Tage sind der laufende plus sechs davor.
        $from = Carbon::now()->subDays($days - 1)->toDateString();

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
            ];
        }

        usort($portalZeilen, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'days' => $days,
            'from' => $from,
            'total' => array_sum(array_column($slotZeilen, 'total')),
            'mobile' => array_sum(array_column($slotZeilen, 'mobile')),
            'desktop' => array_sum(array_column($slotZeilen, 'desktop')),
            'slots' => $slotZeilen,
            'portals' => $portalZeilen,
        ];
    }

    /**
     * Summen je $spalte und Geraet. Das Geraet steht im Aggregat immer als
     * `mobile` oder `desktop` ({@see AdImpressionRecorder}); eine andere
     * Auspraegung kaeme nur aus Altdaten und wird darum nicht einsortiert.
     *
     * @return array<array-key, array{mobile: int, desktop: int, total: int}>
     */
    private static function grouped(?int $tenantId, string $from, string $spalte): array
    {
        $zeilen = DB::connection(AdImpressionRecorder::connection())
            ->table((new AdImpressionDaily)->getTable())
            ->where('day', '>=', $from)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->selectRaw("{$spalte} as gruppe, device, sum(impressions) as impressions")
            ->groupBy('gruppe', 'device')
            ->get();

        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $gruppe = $zeile->gruppe;
            $ergebnis[$gruppe] ??= [AdDevice::MOBILE->value => 0, AdDevice::DESKTOP->value => 0, 'total' => 0];

            $menge = (int) $zeile->impressions;
            $ergebnis[$gruppe]['total'] += $menge;

            if (array_key_exists((string) $zeile->device, $ergebnis[$gruppe])) {
                $ergebnis[$gruppe][(string) $zeile->device] += $menge;
            }
        }

        return $ergebnis;
    }
}
