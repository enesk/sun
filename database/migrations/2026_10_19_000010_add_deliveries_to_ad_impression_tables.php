<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nenner der Viewability-Quote (Ticket #47, docs/ad-slots.md 13.2/14).
 *
 * Der Zaehler aus #12 schreibt nur den Sichtkontakt (50 % / 1 s). Damit ist
 * die Entscheidungsregel aus docs/ad-slots.md 13.3 ("unter 40 % abschalten,
 * ueber 70 % ausweiten") nicht nachrechenbar: sie ist eine Quote
 * "sichtbar / ausgeliefert", und die ausgelieferten Fragmente hat niemand
 * gezaehlt.
 *
 * Gezaehlt wird darum eine zweite Spalte `deliveries` in BEIDEN Tabellen —
 * keine zweite Tabelle:
 *
 *   * Aggregationsstufe, Eindeutigkeitsschluessel und Pruning bleiben
 *     unveraendert; der Aggregations-Job summiert eine Spalte mehr.
 *   * Die Zusagen aus docs/ad-slots.md 14.2 bleiben wortgleich: kein
 *     Zeitstempel je Ereignis, keine IP, keine Sitzung, nur Summen je
 *     Portal/Anker/Belegung/Geraet/Stunde.
 *   * `deliveries >= impressions` ist die Erwartung je Zeile, aber keine
 *     Zusage: ein Fragment kann in der einen Stunde eingesetzt und erst in
 *     der naechsten sichtbar werden. Auf Tagesebene faellt das weg.
 *
 * Altzeilen bekommen `deliveries = 0`. Das ist richtig und nicht "unbekannt
 * als Null": vor diesem Lauf wurde nichts ausgeliefert GEZAEHLT. Die
 * Auswertung weist die Quote deshalb nur aus, wo `deliveries > 0` ist
 * ({@see App\Ads\Support\AdImpressionStats}).
 *
 * Die abdeckenden Indizes aus Ticket #44 muessen mit: sie enden auf
 * `impressions`, damit die Gruppenabfragen des Widgets Index-only laufen
 * (Messung in docs/ad-slots-security-review.md 13.7). Sobald die Abfrage
 * zusaetzlich `sum(deliveries)` holt, deckt der Index sie nicht mehr ab und
 * MySQL faellt auf den Full Scan zurueck — darum werden beide Indizes um
 * `deliveries` erweitert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_impression_buffer', function (Blueprint $table) {
            // Ausgelieferte Fragmente: eingesetzt, bevor die
            // Sichtbarkeitsfrist laeuft (resources/js/ads.js).
            $table->unsignedBigInteger('deliveries')->default(0)->after('impressions');
        });

        Schema::table('ad_impression_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('deliveries')->default(0)->after('impressions');
        });

        Schema::table('ad_impression_daily', function (Blueprint $table) {
            $table->index(['day', 'slot_key', 'device', 'impressions', 'deliveries'], 'ad_impr_daily_day_slot_cover2');
            $table->index(['day', 'tenant_id', 'device', 'impressions', 'deliveries'], 'ad_impr_daily_day_tenant_cover2');

            $table->dropIndex('ad_impr_daily_day_slot_cover');
            $table->dropIndex('ad_impr_daily_day_tenant_cover');
        });
    }

    public function down(): void
    {
        Schema::table('ad_impression_daily', function (Blueprint $table) {
            $table->index(['day', 'slot_key', 'device', 'impressions'], 'ad_impr_daily_day_slot_cover');
            $table->index(['day', 'tenant_id', 'device', 'impressions'], 'ad_impr_daily_day_tenant_cover');

            $table->dropIndex('ad_impr_daily_day_slot_cover2');
            $table->dropIndex('ad_impr_daily_day_tenant_cover2');

            $table->dropColumn('deliveries');
        });

        Schema::table('ad_impression_buffer', function (Blueprint $table) {
            $table->dropColumn('deliveries');
        });
    }
};
