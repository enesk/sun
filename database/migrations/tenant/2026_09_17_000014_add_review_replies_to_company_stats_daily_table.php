<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antworten auf Bewertungen als Statistik-Event (#12). Die Antwort selbst
 * steht bereits in reviews.owner_response / owner_response_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_stats_daily', function (Blueprint $table) {
            $table->unsignedInteger('review_replies')->default(0)->after('widget_views');
        });
    }

    public function down(): void
    {
        Schema::table('company_stats_daily', function (Blueprint $table) {
            $table->dropColumn('review_replies');
        });
    }
};
