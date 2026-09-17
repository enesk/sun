<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monatsreport (#16): Abmeldung in den Einstellungen und der zuletzt
 * versendete Monat (YYYY-MM), damit Wiederholungen nicht doppelt senden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('monthly_report_opted_out_at')->nullable();
            $table->string('monthly_report_sent_period', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['monthly_report_opted_out_at', 'monthly_report_sent_period']);
        });
    }
};
