<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zentrale Rechtstexte fuer alle Portale (Ticket #17).
 *
 * Central-DB, genau eine Zeile je Textart. Vorher lag jeder Text als Kopie in
 * der Tenant-Konfiguration (`branding.impressum` und Geschwister) — 23 Kopien,
 * die auseinandergelaufen sind: gemessen am 22.10.2026 fuenf Generationen
 * desselben Textes. Die portalspezifischen Angaben kommen weiter ueber die
 * Platzhalter `[BETREIBER_*]` aus den Daten des jeweiligen Portals, der Text
 * selbst wird einmal gepflegt.
 *
 * Eigene Tabelle statt `configs`: dort ist `value` ein `text` (65 KB) und jeder
 * Wert wird in `config()` und in den Cache gelegt. Ein Datenschutztext von
 * 16 KB hat darin nichts zu suchen, und wachsen darf er auch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_texts', function (Blueprint $table) {
            $table->id();

            // App\Enums\LegalTextKey: impressum | datenschutz | editorial_principles
            $table->string('key', 64)->unique();

            $table->longText('content')->nullable();

            // Wer zuletzt gespeichert hat — Rechtstexte sind Pflichtangaben,
            // eine Aenderung muss nachlesbar sein. Kein Fremdschluessel: der
            // Eintrag bleibt lesbar, auch wenn das Konto spaeter geloescht wird.
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_texts');
    }
};
