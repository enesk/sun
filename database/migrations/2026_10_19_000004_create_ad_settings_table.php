<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Werbe-Einstellungen je Portal (SUN-AD-003, Ticket #3).
 *
 * Genau eine Zeile je Tenant, central — wie tenant_turnstile_settings. Eine
 * eigene Tabelle statt Attributen auf `tenants.data`, damit #10 die Werte
 * aller Portale in einer Abfrage listen und der Resolver sie ohne Umweg
 * ueber das Tenant-Model joinen kann.
 *
 * Fehlt die Zeile, gilt "aus" — ads_enabled ist der Freigabeschalter je
 * Portal und steht beim Anlegen bewusst auf false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->unique()
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Nichts erscheint, solange das hier nicht an ist (#11-Rollout).
            $table->boolean('ads_enabled')->default(false);

            // "ca-pub-..."; leer = Vorgabe aus config('ad-slots.adsense.publisher_id').
            $table->string('adsense_publisher_id')->nullable();

            // Zusatzzeilen fuer ads.txt dieser Domain (#10).
            $table->text('ads_txt_extra')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable(); // users.id
            $table->timestamps();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_settings');
    }
};
