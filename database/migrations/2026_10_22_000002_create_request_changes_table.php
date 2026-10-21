<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Diff einer Aenderungsanfrage (SUN-RQ, Ticket #3).
 *
 * Eine Zeile je Feld. `old_value`/`new_value` sind JSON und nicht Text, weil
 * Oeffnungszeiten und Leistungen strukturiert sind — ein serialisierter
 * String waere im Panel (#11) nicht zeilenweise darstellbar.
 *
 * `accepted` ist dreiwertig: null = offen, true/false = Haekchen im Panel.
 * Erst `ProfileRequest::apply()` schreibt die angenommenen Zeilen in den
 * Betrieb (#7/#9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')
                ->constrained('profile_requests')
                ->cascadeOnDelete();

            // Spalte bzw. Relation von App\Models\Portal\Company; die
            // automatisch uebernehmbaren stehen in
            // config('profile-requests.auto_apply_fields').
            $table->string('field', 64);

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();

            // null = noch nicht entschieden.
            $table->boolean('accepted')->nullable();

            $table->timestamps();

            // Je Anfrage hoechstens eine Zeile pro Feld — ein zweiter
            // KI-Durchlauf aktualisiert, statt zu verdoppeln.
            $table->unique(['request_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_changes');
    }
};
