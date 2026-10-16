<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belegung eines Ankers (SUN-AD-003, Ticket #3; docs/ad-slots.md 4.2/4.3).
 *
 * `slot_key` ist ein String ohne Fremdschluessel: der Anker-Katalog ist Code
 * (config/ad-slots.php), keine Daten. Dass der Schluessel existiert und die
 * Geraetewahl zum Anker passt, prueft App\Ads\Models\AdPlacement beim
 * Speichern (und die Rules in app/Ads/Rules/ im Formular, Ticket #8).
 *
 * `page_type` ist redundant zu `slot_key` — das Model fuellt die Spalte aus
 * dem Katalog. Sie steht hier, weil der Editor (#7) nach Seitentyp listet und
 * der Resolver-Index sonst nicht greift.
 *
 * Pro Anker, Geraet und Portal darf es mehrere Belegungen geben; welche
 * gewinnt, entscheidet allein AdPlacement::scopeResolutionOrder() — es gibt
 * hier bewusst keinen Unique-Index, der das verboete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ad_unit_id')
                ->constrained('ad_units')
                ->cascadeOnDelete();

            // App\Ads\Enums\AdPageType, aus dem Katalog abgeleitet.
            $table->string('page_type', 32);
            // Schluessel aus config('ad-slots.slots'), kein FK.
            $table->string('slot_key', 64);
            // App\Ads\Enums\AdDevice; der Anker verengt (AdDevice::narrowTo()).
            $table->string('device', 16)->default('all');
            // App\Ads\Enums\AdPlacementStatus.
            $table->string('status', 16)->default('draft');

            // Groesser gewinnt (Ticket #3); bei Gleichstand die neuere Zeile.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->timestamp('starts_at')->nullable();   // null = sofort
            $table->timestamp('ends_at')->nullable();     // null = unbefristet

            // true = alle Portale, der Pivot bleibt dann leer.
            $table->boolean('applies_to_all_tenants')->default(false);

            $table->unsignedBigInteger('created_by')->nullable(); // users.id
            $table->timestamps();

            // Zugriffspfad des Resolvers (Ticket #4).
            $table->index(['slot_key', 'status', 'device']);
            // Faelligkeitspruefung und der Befehl, der auf `expired` schreibt.
            $table->index(['status', 'starts_at', 'ends_at']);
            // Liste je Seitentyp im Editor (#7).
            $table->index(['page_type', 'status']);

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
