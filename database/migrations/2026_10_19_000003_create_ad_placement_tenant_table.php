<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Scope einer Belegung (SUN-AD-003, Ticket #3).
 *
 * Pivot statt JSON-Array im Placement: so bleibt das Filament-Multi-Select
 * (#8) ein gewoehnliches BelongsToMany und die Resolver-Abfrage ein JOIN
 * statt eines JSON_CONTAINS ueber die ganze Tabelle.
 *
 * Eine Zeile hier gilt nur, wenn `ad_placements.applies_to_all_tenants` false
 * ist — bei true ist der Pivot leer und wird nicht gelesen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placement_tenant', function (Blueprint $table) {
            $table->foreignId('ad_placement_id')
                ->constrained('ad_placements')
                ->cascadeOnDelete();

            // tenants.id ist in diesem Projekt ein bigint (siehe
            // 2024_06_16_091235_create_tenants_table), nicht der String aus
            // der stancl-Vorgabe — der Pivot folgt der echten Tabelle.
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->primary(['ad_placement_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placement_tenant');
    }
};
