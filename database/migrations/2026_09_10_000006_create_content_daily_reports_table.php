<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagesberichte der Content-Pipeline (#22).
 *
 * Der Bericht entsteht um 20:00 aus 20 Tenant-Datenbanken und wird
 * anschliessend gelesen: von der Mail und von der Uebersicht des Panels. Er
 * liegt deshalb central und nicht im Cache — der Dateicache haengt am
 * mandantenspezifischen storage_path und waere nach einem Lauf ueber alle
 * Mandanten nicht verlaesslich wieder aus dem Central-Kontext lesbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->json('report_json');
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('target')->default(0);
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_daily_reports');
    }
};
