<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pruefabstand je Kategorie (#33, design/guide-dashboard.md §9.1). null =
 * Vorgabe guide.schedule.probe_interval_days. Ein eigener Wert am Thema
 * (guide_topics.refresh_interval_days) geht weiter vor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('refresh_interval_days')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('guide_categories', function (Blueprint $table) {
            $table->dropColumn('refresh_interval_days');
        });
    }
};
