<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markiert Fakten, deren einzige Quelle aelter ist als
 * config('guide.research.stale_after_months') (#8). Solche Quellen werden nur
 * akzeptiert, wenn es fuer den Fakt keine neuere gibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_facts', function (Blueprint $table) {
            $table->boolean('stale_source')->default(false)->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('guide_facts', function (Blueprint $table) {
            $table->dropColumn('stale_source');
        });
    }
};
