<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Werbemittel (SUN-AD-003, Ticket #3; docs/ad-slots.md 4.2).
 *
 * Central-DB, nicht je Tenant: ein Werbemittel wird einmal gepflegt, die
 * Portale haengen ueber die Belegung (ad_placements + Pivot) daran.
 *
 * Die providerspezifischen Angaben stehen in `config_json`, nicht in eigenen
 * Spalten — ein dritter Provider (Ticket #4) bringt dann kein ALTER TABLE mit.
 * Das Schema je Provider prueft App\Ads\Support\AdUnitConfigSchema, und das
 * Model laesst eine Zeile ohne gueltige Konfiguration nicht speichern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // App\Ads\Enums\AdProviderType: adsense | html
            $table->string('provider', 32);

            // adsense: {"slot_id": "...", "format": "...", "layout_key": "..."}
            // html:    {"code": "...", "width": 300, "height": 250}
            $table->json('config_json');

            // Not-Aus ueber alle Belegungen hinweg.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_units');
    }
};
