<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tageswerte je Betrieb (#15, Epic #1), geschrieben von stats:aggregate-daily.
 * ranking_position: Platz in der Stadtliste des Betriebs zum Aggregationszeitpunkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('phone_clicks')->default(0);
            $table->unsignedInteger('website_clicks')->default(0);
            $table->unsignedInteger('quote_requests')->default(0);
            $table->unsignedInteger('list_impressions')->default(0);
            $table->unsignedInteger('widget_views')->default(0);
            $table->unsignedInteger('ranking_position')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'date'], 'csd_company_date_unique');
            $table->index('date', 'csd_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_stats_daily');
    }
};
