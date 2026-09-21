<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagesberichte des Ratgebersystems (#13).
 *
 * Der Bericht entsteht um 20:00 aus allen Tenant-Datenbanken und wird danach
 * von der Mail und vom Dashboard (#16) gelesen. Er liegt central und nicht im
 * Cache: der Dateicache haengt am mandantenspezifischen storage_path und waere
 * nach einem Lauf ueber alle Mandanten nicht verlaesslich wieder lesbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->json('report_json');
            $table->unsignedInteger('checked')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_daily_reports');
    }
};
