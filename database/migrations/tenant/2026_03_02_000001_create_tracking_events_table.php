<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rohdaten: jeder einzelne Event (Page View, Contact Click, Search Impression)
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30)->comment('page_view, contact_click, search_impression');
            $table->string('contact_type', 20)->nullable()->comment('phone, email, website, map — nur bei contact_click');
            $table->string('search_query', 255)->nullable()->comment('Suchbegriff — nur bei search_impression');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Central DB User, kein FK');
            $table->string('referrer', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Performance-Indexes für Aggregation
            $table->index(['company_id', 'event_type', 'created_at'], 'idx_tracking_company_type_date');
            $table->index(['event_type', 'created_at'], 'idx_tracking_type_date');
            $table->index('created_at', 'idx_tracking_date');
        });

        // Aggregierte Tagesdaten: für schnelle Dashboard-Abfragen (#173 füllt diese Tabelle)
        Schema::create('tracking_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('contact_clicks_phone')->default(0);
            $table->unsignedInteger('contact_clicks_email')->default(0);
            $table->unsignedInteger('contact_clicks_website')->default(0);
            $table->unsignedInteger('contact_clicks_map')->default(0);
            $table->unsignedInteger('search_impressions')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'date'], 'uq_tracking_daily_company_date');
            $table->index(['date'], 'idx_tracking_daily_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_stats');
        Schema::dropIfExists('tracking_events');
    }
};
