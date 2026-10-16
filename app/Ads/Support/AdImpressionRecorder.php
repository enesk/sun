<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Enums\AdDevice;
use App\Ads\Models\AdPlacement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nimmt die Meldungen des Impression-Beacons an und zaehlt sie im
 * Stundenpuffer hoch (Ticket #12, docs/ad-slots.md 14).
 *
 * Die einzige Stelle, die aus einem Request auf die Zaehltabellen schreibt.
 * Sie schreibt ausschliesslich Zaehler — kein Zeitstempel je Ereignis, keine
 * IP, kein Hash, keine Sitzung. Darum braucht das Beacon keine Einwilligung:
 * uebertragen werden Katalogschluessel, Belegungs-Id, Geraeteklasse und die
 * Ereignisart, und gespeichert wird davon nur die Summe je Portal und Stunde.
 *
 * Zwei Zaehler je Zeile (Ticket #47):
 *
 *   * `deliveries` — das Fragment wurde ausgeliefert (eingesetzt, bevor die
 *     Sichtbarkeitsfrist laeuft). Der Nenner der Viewability-Quote.
 *   * `impressions` — Sichtkontakt nach der MRC-Vorgabe (50 % / 1 s). Der
 *     Zaehler der Quote.
 *
 * Welcher von beiden hochgeht, sagt `event` in der Meldung
 * ({@see EVENT_DELIVERED}, {@see EVENT_VIEWABLE}). Fehlt der Schluessel, gilt
 * `viewable` — eine Meldung aus einem Browser mit dem alten, noch
 * zwischengespeicherten `ads.js` ist genau das.
 *
 * Was NICHT durchkommt:
 *
 *   * unbekannte Katalogschluessel (der Katalog ist Code, {@see AdSlotCatalog})
 *   * Geraete ausser `mobile`/`desktop` — `all` ist die Geraetewahl eines
 *     Ankers, kein Geraet, auf dem jemand sitzt
 *   * dieselbe Kombination mehrfach in einem Beacon: je Anker, Belegung,
 *     Geraet und Ereignisart zaehlt ein Seitenaufruf genau einmal. Sonst
 *     koennte ein praeparierter Rumpf den Zaehler mit einem Aufruf
 *     hochtreiben; das Rate-Limit der Route begrenzt nur die Anzahl der
 *     Aufrufe.
 *   * eine `placement_id`, die es auf diesem Portal und diesem Anker nicht
 *     gibt: sie wird zu {@see UNKNOWN_PLACEMENT} (#42,
 *     docs/ad-slots-security-review.md 13.2). Damit ist die Zeilenzahl auf
 *     Anker x Geraet x (echte Belegungen + 1) gedeckelt, statt auf 2^64
 *     frei waehlbare Werte je Anker.
 */
final class AdImpressionRecorder
{
    public const TABLE = 'ad_impression_buffer';

    /** Vorgabe, wenn kein Anzeigenplatz mitgeschickt wurde oder er entfallen ist. */
    public const UNKNOWN_PLACEMENT = 0;

    /** Fragment eingesetzt — Nenner der Viewability-Quote (#47). */
    public const EVENT_DELIVERED = 'delivered';

    /** Sichtkontakt nach MRC-Vorgabe — Zaehler der Quote (#12). */
    public const EVENT_VIEWABLE = 'viewable';

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
        return max(1, (int) config('ad-slots.impressions.max_entries', 40));
    }

    /**
     * @param  list<array<string, mixed>>  $entries  Rumpf des Beacons, ungeprueft
     * @return int Zahl der gezaehlten Meldungen (Auslieferungen + Sichtkontakte)
     */
    public function record(int $tenantId, array $entries): int
    {
        if (! self::enabled() || $tenantId <= 0) {
            return 0;
        }

        $hour = Carbon::now()->startOfHour();
        $rows = [];
        $gezaehlt = 0;
        $valid = $this->validPlacements($tenantId);

        foreach (array_slice($entries, 0, self::maxEntries()) as $entry) {
            $row = $this->normalize($entry, $valid);

            if ($row === null) {
                continue;
            }

            $event = $row['event'];
            unset($row['event']);

            // Schluessel des Zaehlers. Auslieferung und Sichtkontakt
            // desselben Ankers landen in EINER Zeile, aber in verschiedenen
            // Spalten; dieselbe Kombination samt Ereignisart mehrfach
            // ueberschreibt sich, statt sich zu addieren.
            $schluessel = implode('|', $row);
            $rows[$schluessel] ??= $row + ['deliveries' => 0, 'impressions' => 0];

            $spalte = $event === self::EVENT_DELIVERED ? 'deliveries' : 'impressions';

            if ($rows[$schluessel][$spalte] === 1) {
                continue;
            }

            $rows[$schluessel][$spalte] = 1;
            $gezaehlt++;
        }

        if ($rows === []) {
            return 0;
        }

        $this->bump($tenantId, $hour, array_values($rows));

        return $gezaehlt;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, list<int>>  $valid  gueltige Belegungs-Ids je Anker
     * @return array{slot_key: string, placement_id: int, device: string, event: string}|null
     */
    private function normalize(array $entry, array $valid): ?array
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
        $placementId = is_numeric($placementId) ? max(self::UNKNOWN_PLACEMENT, (int) $placementId) : self::UNKNOWN_PLACEMENT;

        // Eine Belegung, die dieses Portal auf diesem Anker nicht hat, zaehlt
        // als "nicht mehr bekannt" — nicht als eigene Zeile. Verworfen wird
        // die Meldung nicht: der Anker war sichtbar, und zwischen Auslieferung
        // und Beacon kann eine Belegung pausiert oder geloescht worden sein.
        if ($placementId !== self::UNKNOWN_PLACEMENT && ! in_array($placementId, $valid[$slotKey] ?? [], true)) {
            $placementId = self::UNKNOWN_PLACEMENT;
        }

        return [
            'slot_key' => $slotKey,
            'placement_id' => $placementId,
            'device' => $device,
            'event' => $this->event($entry),
        ];
    }

    /**
     * Welcher der beiden Zaehler gemeint ist (#47).
     *
     * Nur `delivered` zaehlt als Auslieferung; alles andere — auch ein
     * fehlender oder voelliger Unsinns-Wert — gilt als Sichtkontakt. So
     * bleibt eine Meldung aus einem Browser mit dem alten, noch
     * zwischengespeicherten `ads.js` (dort gibt es den Schluessel nicht) das,
     * was sie war: ein Sichtkontakt. Umgekehrt waere der Rueckfall schaedlich,
     * weil er den Nenner der Quote aufblaehen wuerde.
     *
     * @param  array<string, mixed>  $entry
     */
    private function event(array $entry): string
    {
        $event = is_string($entry['event'] ?? null) ? $entry['event'] : '';

        return $event === self::EVENT_DELIVERED ? self::EVENT_DELIVERED : self::EVENT_VIEWABLE;
    }

    /**
     * Gueltige Belegungs-Ids je Anker fuer dieses Portal — eine Abfrage je
     * Cache-Fenster, nicht je Beacon ({@see AdCache::impressionPlacementsKey()}).
     *
     * Geprueft wird `active()` und `forTenant()`, bewusst **nicht**
     * `deliverable()`: ein gerade abgelaufener Zeitraum oder ein soeben
     * abgeschaltetes Werbemittel darf die Impression eines Ankers, der eben
     * noch sichtbar war, nicht zu einer Null-Belegung machen. Status und
     * Portalzuordnung reichen, um die Zeilenzahl zu deckeln.
     *
     * @return array<string, list<int>>
     */
    private function validPlacements(int $tenantId): array
    {
        /** @var array<string, list<int>> */
        return Cache::remember(
            AdCache::impressionPlacementsKey($tenantId),
            AdCache::impressionPlacementsTtl(),
            function () use ($tenantId): array {
                $map = [];

                $rows = AdPlacement::query()
                    ->select(['id', 'slot_key'])
                    ->active()
                    ->forTenant($tenantId)
                    ->toBase()
                    ->get();

                foreach ($rows as $row) {
                    $map[(string) $row->slot_key][] = (int) $row->id;
                }

                return $map;
            }
        );
    }

    /**
     * Ein Statement fuer alle Meldungen eines Seitenaufrufs: anlegen oder
     * hochzaehlen. Eloquent und der Query-Builder koennen das nicht — `upsert()`
     * setzt Spalten auf einen Wert, es kann sie nicht um einen erhoehen —,
     * darum rohes SQL. `VALUES()` auf der rechten Seite ist in MySQL wie in
     * MariaDB die Form, die den eingefuegten Wert benennt.
     *
     * Beide Zaehler stehen als Bindung im Statement (0 oder 1) und werden
     * beide addiert — eine Meldung, die nur den Sichtkontakt bringt, laesst
     * `deliveries` dadurch unveraendert (#47).
     *
     * @param  list<array{slot_key: string, placement_id: int, device: string, deliveries: int, impressions: int}>  $rows
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
            $bindings[] = $row['impressions'];
            $bindings[] = $row['deliveries'];
            $bindings[] = $now->toDateTimeString();
            $bindings[] = $now->toDateTimeString();
        }

        $values = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?, ?, ?, ?, ?)'));

        DB::connection(self::connection())->statement(
            'insert into '.self::TABLE.' (tenant_id, hour, slot_key, placement_id, device, impressions, deliveries, created_at, updated_at)'
            .' values '.$values
            .' on duplicate key update impressions = impressions + values(impressions),'
            .' deliveries = deliveries + values(deliveries), updated_at = values(updated_at)',
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
