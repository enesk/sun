<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagesaggregat der eigenen Impressionen (Ticket #12, docs/ad-slots.md 14).
 *
 * Zentral, wie das ganze Werbe-Datenmodell: die Auswertung vergleicht Portale
 * miteinander, und 23 Tenant-Tabellen koennte man dafuer nicht joinen.
 *
 * Es gibt KEINE Rohdatentabelle. Der Beacon schreibt in den Stundenpuffer
 * `ad_impression_buffer` — selbst schon ein Aggregat —, der stuendliche Job
 * {@see App\Ads\Jobs\AggregateAdImpressions} rollt ihn hierher und leert ihn.
 * Es gibt also nie eine Zeile je Seitenaufruf, und damit auch nichts, was auf
 * einen Besucher zurueckfuehrt.
 *
 * `slot_key` ist ein String ohne Fremdschluessel (der Katalog ist Code), und
 * `placement_id` ist bewusst OHNE Fremdschluessel und nicht nullable:
 *
 *   * Eine geloeschte Belegung darf ihre Geschichte nicht mitnehmen — sonst
 *     waere der Vergleich "vorher/nachher" nach jeder Umbuchung weg.
 *   * `0` heisst "Belegung nicht mehr bekannt". Ein NULL waere hier schaedlich:
 *     MySQL zaehlt NULLs im Unique-Index als verschieden, der Zaehler wuerde
 *     sich bei jedem Lauf vervielfachen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_impression_daily', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->date('day');
            // Schluessel aus config('ad-slots.slots'), kein FK.
            $table->string('slot_key', 64);
            // ad_placements.id, 0 = Belegung entfallen. Kein FK (siehe oben).
            $table->unsignedBigInteger('placement_id')->default(0);
            // App\Ads\Enums\AdDevice, hier nur mobile|desktop: der Zaehler
            // meldet das tatsaechliche Geraet, nicht die Geraetewahl des
            // Ankers — sonst waere der Vergleich Mobil/Desktop blind fuer
            // alle Belegungen mit `all`.
            $table->string('device', 16);

            $table->unsignedBigInteger('impressions')->default(0);

            $table->timestamps();

            // Ziel des Upserts im Aggregations-Job.
            $table->unique(['tenant_id', 'day', 'slot_key', 'placement_id', 'device'], 'ad_impr_daily_unique');
            // Auswertung im Widget: letzte 7/30 Tage, optional je Portal.
            // ACHTUNG: beide folgenden Indizes fallen in
            // 2026_10_19_000009_add_covering_indexes_to_ad_impression_daily_table
            // wieder weg — sie decken die Gruppenabfragen des Widgets nicht ab
            // (Ticket #44, docs/ad-slots-security-review.md 13.7). Hier bleiben
            // sie stehen, weil die Tabelle so schon ausgeliefert ist.
            $table->index(['day', 'tenant_id'], 'ad_impr_daily_day_tenant');
            // Slot-Vergleich ueber alle Portale.
            $table->index(['slot_key', 'day'], 'ad_impr_daily_slot_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_impression_daily');
    }
};
