<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Enums\AdDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nimmt die Meldungen des Impression-Beacons an und zaehlt sie im
 * Stundenpuffer hoch (Ticket #12, docs/ad-slots.md 14).
 *
 * Die einzige Stelle, die aus einem Request auf die Zaehltabellen schreibt.
 * Sie schreibt ausschliesslich Zaehler — kein Zeitstempel je Ereignis, keine
 * IP, kein Hash, keine Sitzung. Darum braucht das Beacon keine Einwilligung:
 * uebertragen werden Katalogschluessel, Belegungs-Id und Geraeteklasse, und
 * gespeichert wird davon nur die Summe je Portal und Stunde.
 *
 * Was NICHT durchkommt:
 *
 *   * unbekannte Katalogschluessel (der Katalog ist Code, {@see AdSlotCatalog})
 *   * Geraete ausser `mobile`/`desktop` — `all` ist die Geraetewahl eines
 *     Ankers, kein Geraet, auf dem jemand sitzt
 *   * dieselbe Kombination mehrfach in einem Beacon: je Anker, Belegung und
 *     Geraet zaehlt ein Seitenaufruf genau eine Impression. Sonst koennte ein
 *     praeparierter Rumpf den Zaehler mit einem Aufruf hochtreiben; das
 *     Rate-Limit der Route begrenzt nur die Anzahl der Aufrufe.
 */
final class AdImpressionRecorder
{
    public const TABLE = 'ad_impression_buffer';

    /** Vorgabe, wenn kein Anzeigenplatz mitgeschickt wurde oder er entfallen ist. */
    public const UNKNOWN_PLACEMENT = 0;

    public static function enabled(): bool
    {
        return config('ad-slots.impression_tracking') === true;
    }

    /**
     * Hoechstzahl der Meldungen in einem Beacon. Alles darueber wird
     * abgeschnitten, nicht abgelehnt — ein abgeschnittenes Beacon ist
     * harmlos, ein abgelehntes verliert auch die gueltigen Meldungen.
     */
    public static function maxEntries(): int
    {
        return max(1, (int) config('ad-slots.impressions.max_entries', 20));
    }

    /**
     * @param  list<array<string, mixed>>  $entries  Rumpf des Beacons, ungeprueft
     * @return int Zahl der gezaehlten Impressionen
     */
    public function record(int $tenantId, array $entries): int
    {
        if (! self::enabled() || $tenantId <= 0) {
            return 0;
        }

        $hour = Carbon::now()->startOfHour();
        $rows = [];

        foreach (array_slice($entries, 0, self::maxEntries()) as $entry) {
            $row = $this->normalize($entry);

            if ($row === null) {
                continue;
            }

            // Schluessel des Zaehlers; mehrfach gemeldete Kombinationen
            // ueberschreiben sich hier, statt sich zu addieren.
            $rows[implode('|', $row)] = $row;
        }

        if ($rows === []) {
            return 0;
        }

        $this->bump($tenantId, $hour, array_values($rows));

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{slot_key: string, placement_id: int, device: string}|null
     */
    private function normalize(array $entry): ?array
    {
        $slotKey = is_string($entry['slot'] ?? null) ? $entry['slot'] : '';

        if ($slotKey === '' || ! AdSlotCatalog::exists($slotKey)) {
            return null;
        }

        $device = is_string($entry['device'] ?? null) ? $entry['device'] : '';

        if (! in_array($device, [AdDevice::MOBILE->value, AdDevice::DESKTOP->value], true)) {
            return null;
        }

        $placementId = $entry['placement'] ?? null;
        $placementId = is_numeric($placementId) ? (int) $placementId : self::UNKNOWN_PLACEMENT;

        return [
            'slot_key' => $slotKey,
            'placement_id' => max(self::UNKNOWN_PLACEMENT, $placementId),
            'device' => $device,
        ];
    }

    /**
     * Ein Statement fuer alle Meldungen eines Seitenaufrufs: anlegen oder
     * hochzaehlen. Eloquent und der Query-Builder koennen das nicht — `upsert()`
     * setzt Spalten auf einen Wert, es kann sie nicht um einen erhoehen —,
     * darum rohes SQL. `VALUES()` auf der rechten Seite ist in MySQL wie in
     * MariaDB die Form, die den eingefuegten Wert benennt.
     *
     * @param  list<array{slot_key: string, placement_id: int, device: string}>  $rows
     */
    private function bump(int $tenantId, Carbon $hour, array $rows): void
    {
        $now = Carbon::now();
        $bindings = [];

        foreach ($rows as $row) {
            $bindings[] = $tenantId;
            $bindings[] = $hour->toDateTimeString();
            $bindings[] = $row['slot_key'];
            $bindings[] = $row['placement_id'];
            $bindings[] = $row['device'];
            $bindings[] = $now->toDateTimeString();
            $bindings[] = $now->toDateTimeString();
        }

        $values = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?, 1, ?, ?)'));

        DB::connection(self::connection())->statement(
            'insert into '.self::TABLE.' (tenant_id, hour, slot_key, placement_id, device, impressions, created_at, updated_at)'
            .' values '.$values
            .' on duplicate key update impressions = impressions + values(impressions), updated_at = values(updated_at)',
            $bindings
        );
    }

    /**
     * Die Zaehltabellen liegen zentral — im Portal-Request ist die
     * Standardverbindung aber die Tenant-DB. Die Verbindung muss darum
     * ausdruecklich benannt werden (wie bei allen Models mit CentralConnection).
     */
    public static function connection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }
}
