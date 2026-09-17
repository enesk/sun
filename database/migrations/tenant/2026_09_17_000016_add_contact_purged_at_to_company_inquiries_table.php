<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loeschvermerk fuer Anfragen aus dem Leadsystem (#32 Premium): Kontaktdaten
 * und Antworten entfernt leads:inquiries:purge-contacts nach 12 Monaten, der
 * Datensatz bleibt mit Status und Eingangsdatum stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_inquiries', function (Blueprint $table) {
            $table->timestamp('contact_purged_at')->nullable()->after('read_at');
            $table->index(['contact_purged_at', 'received_at'], 'ci_purge_index');
        });
    }

    public function down(): void
    {
        Schema::table('company_inquiries', function (Blueprint $table) {
            $table->dropIndex('ci_purge_index');
            $table->dropColumn('contact_purged_at');
        });
    }
};
