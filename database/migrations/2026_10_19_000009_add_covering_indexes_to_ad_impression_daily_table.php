<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abdeckende Indizes fuer das Impressions-Widget (Ticket #44, Leistungspunkt
 * P1 aus dem Review der Zaehlkette #39, Messung in
 * docs/ad-slots-security-review.md 13.7).
 *
 * {@see App\Ads\Support\AdImpressionStats::build()} fuehrt je Aufbau zwei
 * Gruppenabfragen ueber `ad_impression_daily`: `where day >= ?`, gruppiert je
 * Anker bzw. je Portal und Geraet, summiert `impressions`. Keiner der beim
 * Anlegen der Tabelle gesetzten Indizes deckt das ab — `day` steht in
 * `ad_impr_daily_day_tenant` vorn, aber `device` und `impressions` fehlen,
 * also muesste MySQL jede Zeile zusaetzlich aus der Tabelle holen und waehlt
 * stattdessen gleich den Full Scan, sobald das Fenster einen nennenswerten
 * Anteil der Tabelle ausmacht.
 *
 * Gemessen auf der lokalen zentralen DB in voller Katalogbreite (23 Portale,
 * 32 Anker, 2 Geraete, 2 Belegungen je Tag), 558.041 Zeilen:
 *
 *   * je Anker, 30 Tage: 165 ms Full Scan -> 55 ms "Using index"
 *   * je Portal, 30 Tage: 129 ms Full Scan -> 52 ms "Using index"
 *
 * Bei der vorgesehenen Aufbewahrung von 400 Tagen (~1,18 Mio. Zeilen) waere
 * der Full Scan linear fortgeschrieben bei rund 350 ms + 280 ms je
 * Widget-Aufbau — darum vor dem ersten Jahreswechsel der Daten.
 *
 * Die Spaltenreihenfolge ist die der Abfrage: `day` als Bereichsfilter vorn,
 * dann die Gruppierungsspalten, `impressions` als letzte Spalte nur, damit
 * der Index die Summe selbst hergibt (Index-only, kein Tabellenzugriff).
 *
 * Zwei Indizes fallen dabei weg:
 *
 *   * `ad_impr_daily_day_tenant` (`day, tenant_id`) ist ein reines Praefix des
 *     neuen `ad_impr_daily_day_tenant_cover` — doppelt gepflegt, ohne eigenen
 *     Zugriffspfad.
 *   * `ad_impr_daily_slot_day` (`slot_key, day`) wird von keiner Abfrage des
 *     Moduls bedient: die einzige Leseabfrage filtert immer auf `day` und
 *     gruppiert nur nach `slot_key`, ein Filter auf `slot_key` ohne `day`
 *     kommt nicht vor. Der Upsert des Aggregations-Jobs trifft
 *     `ad_impr_daily_unique`, das Aufraeumen filtert auf `day`.
 *
 * Der Portalfilter des Widgets braucht nichts Neues: dort greift
 * `ad_impr_daily_unique` (`tenant_id, day, …`) als Praefix (5 ms).
 *
 * ACHTUNG: beide hier angelegten Indizes fallen in
 * 2026_10_19_000010_add_deliveries_to_ad_impression_tables wieder weg und
 * kommen als `…_cover2` mit `deliveries` als letzter Spalte zurueck — die
 * Widget-Abfrage holt seit Ticket #47 zusaetzlich `sum(deliveries)` und waere
 * ohne das nicht mehr Index-only. Hier bleiben sie stehen, weil diese
 * Migration schon ausgeliefert ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_impression_daily', function (Blueprint $table) {
            // Gruppierung je Anker (Slot-Tabelle des Widgets), Index-only.
            $table->index(['day', 'slot_key', 'device', 'impressions'], 'ad_impr_daily_day_slot_cover');
            // Gruppierung je Portal (Portal-Tabelle des Widgets), Index-only.
            // Ersetzt `ad_impr_daily_day_tenant` als dessen Erweiterung.
            $table->index(['day', 'tenant_id', 'device', 'impressions'], 'ad_impr_daily_day_tenant_cover');

            $table->dropIndex('ad_impr_daily_day_tenant');
            $table->dropIndex('ad_impr_daily_slot_day');
        });
    }

    public function down(): void
    {
        Schema::table('ad_impression_daily', function (Blueprint $table) {
            $table->index(['day', 'tenant_id'], 'ad_impr_daily_day_tenant');
            $table->index(['slot_key', 'day'], 'ad_impr_daily_slot_day');

            $table->dropIndex('ad_impr_daily_day_slot_cover');
            $table->dropIndex('ad_impr_daily_day_tenant_cover');
        });
    }
};
