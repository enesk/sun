<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal-Auswahl eines Vorschau-Links (SUN-PV-001, Ticket #2).
 *
 * Nur ausgewertet, wenn preview_links.applies_to_all_tenants = false.
 * tenants.id ist in diesem Projekt ein bigint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_link_tenant', function (Blueprint $table) {
            $table->foreignId('preview_link_id')->constrained('preview_links')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->primary(['preview_link_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_link_tenant');
    }
};
