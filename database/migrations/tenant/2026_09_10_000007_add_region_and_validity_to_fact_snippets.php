<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faktenschnipsel als Datenquelle statt nur als Beleg (#11).
 *
 * Statistik-Connector und PortalDataProvider legen Kennzahlen ab, bevor es
 * einen Entwurf gibt: eine Zahl mit Einheit, Region, Quelle, Abrufdatum und
 * Haltbarkeit. Der Generator (#14) sucht sie ueber `fact_key` + Region,
 * das Qualitaetsgate (#15) verwirft alles, dessen `valid_until` abgelaufen ist.
 *
 * Die Spalte heisst `fact_key` und nicht `key`: `KEY` ist in MySQL ein
 * Schluesselwort und muesste in jeder Rohabfrage gequotet werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fact_snippets', function (Blueprint $table) {
            $table->string('fact_key', 128)->nullable()->after('fingerprint');
            $table->string('region_scope', 16)->default('national')->after('period'); // national|state|city
            $table->string('region_code', 32)->nullable()->after('region_scope');
            $table->timestamp('retrieved_at')->nullable()->after('source_url');
            $table->timestamp('valid_until')->nullable()->after('retrieved_at');

            $table->index(['fact_key', 'region_scope', 'region_code'], 'idx_fact_key_region');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('fact_snippets', function (Blueprint $table) {
            $table->dropIndex('idx_fact_key_region');
            $table->dropIndex(['valid_until']);
            $table->dropColumn(['fact_key', 'region_scope', 'region_code', 'retrieved_at', 'valid_until']);
        });
    }
};
