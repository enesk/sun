<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stundenpuffer des Impression-Zaehlens (Ticket #12, docs/ad-slots.md 14).
 *
 * Der Beacon schreibt nicht direkt ins Tagesaggregat, sondern hierher — und
 * auch hier nicht eine Zeile je Meldung, sondern einen Zaehler je
 * Portal/Stunde/Anker/Belegung/Geraet (`INSERT … ON DUPLICATE KEY UPDATE
 * impressions = impressions + …`). Zwei Gruende:
 *
 *  1. Rohdaten entstehen nie. Die kleinste Einheit ist eine Stunde ueber alle
 *     Besucher eines Portals — daraus ist kein Einzelbesuch ablesbar.
 *  2. Der Schreibweg bleibt ein einziges Statement je Seitenaufruf, auch bei
 *     mehreren Ankern, und das Tagesaggregat bekommt nur einmal je Stunde
 *     Schreiblast (sonst wuerden alle Portale den ganzen Tag auf dieselben
 *     wenigen Tageszeilen schreiben).
 *
 * Geleert wird vollstaendig von {@see App\Ads\Jobs\AggregateAdImpressions}:
 * verdichtet werden nur ABGESCHLOSSENE Stunden, die laufende bleibt liegen.
 * Die Tabelle ist damit dauerhaft klein.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_impression_buffer', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Beginn der Stunde (UTC wie alle Zeitstempel der App).
            $table->dateTime('hour');
            $table->string('slot_key', 64);
            $table->unsignedBigInteger('placement_id')->default(0);
            $table->string('device', 16);

            $table->unsignedBigInteger('impressions')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'hour', 'slot_key', 'placement_id', 'device'], 'ad_impr_buffer_unique');
            // Zugriffspfad des Jobs: alles vor dem Schnitt.
            $table->index('hour', 'ad_impr_buffer_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_impression_buffer');
    }
};
