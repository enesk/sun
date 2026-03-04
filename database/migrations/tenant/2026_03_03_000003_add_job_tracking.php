<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // job_id zu tracking_events hinzufügen (nullable — existierende Company-Events haben kein Job)
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable()->after('company_id');

            // Index für Job-spezifische Abfragen
            $table->index(['job_id', 'event_type', 'created_at'], 'idx_tracking_job_type_date');
        });

        // Aggregierte Tagesdaten für Jobs (analog zu tracking_daily_stats für Companies)
        Schema::create('job_tracking_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date');
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('search_impressions')->default(0);
            $table->timestamps();

            $table->unique(['job_id', 'date'], 'uq_job_tracking_daily_job_date');
            $table->index(['company_id', 'date'], 'idx_job_tracking_daily_company_date');
            $table->index('date', 'idx_job_tracking_daily_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_tracking_daily_stats');

        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex('idx_tracking_job_type_date');
            $table->dropColumn('job_id');
        });
    }
};
