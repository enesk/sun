<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwischenspeicher der SERP-Analyse (#10).
 *
 * Der SerpInsightService legt das vollstaendige SerpInsightDto eines
 * Zielkeywords im passenden Cluster ab. `serp_fetched_at` entscheidet ueber
 * die 14-Tage-Frist: solange der Zeitstempel juenger ist, wird kein zweiter
 * API-Aufruf gemacht. Scoring (#12) und Generator (#14) lesen dieselbe Spalte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->json('serp_json')->nullable()->after('centroid_json');
            $table->timestamp('serp_fetched_at')->nullable()->after('serp_json');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_clusters', function (Blueprint $table) {
            $table->dropColumn(['serp_json', 'serp_fetched_at']);
        });
    }
};
