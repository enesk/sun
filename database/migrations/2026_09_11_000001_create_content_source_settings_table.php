<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellen-Konfiguration je Connector (#45/#55), design/content-dashboard.md, §7a.
 *
 * Zentral neben provider_states, bewusst ohne Portalbezug: Ratenlimit, Kosten
 * und Provider-Zustand eines Connectors sind bereits zentral. Ein Rhythmus je
 * Portal wuerde denselben Anbieter bis zu 24-fach unterschiedlich takten.
 *
 * Die Tabelle beschreibt ausschliesslich Abweichungen von der Vorgabe. Ohne
 * Datensatz gilt, was der Connector deklariert und config/content.php sagt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_source_settings', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->boolean('is_enabled')->default(true);

            // Werte aus dem frueheren Enum SourceFrequency (entfernt mit #23); null = Deklaration
            // des Connectors gilt. Nur gleich oder seltener als die
            // Deklaration, nie haeufiger.
            $table->string('frequency_override', 20)->nullable();

            // Prozent, 0-200. 100 = unveraendert.
            $table->unsignedSmallInteger('weight')->default(100);

            $table->string('disabled_reason', 200)->nullable();
            $table->unsignedBigInteger('updated_by_content_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_source_settings');
    }
};
