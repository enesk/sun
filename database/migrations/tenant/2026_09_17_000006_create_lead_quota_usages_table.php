<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monatlicher Lead-Zaehler je Betrieb (#3, Epic #1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_quota_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7)->comment('YYYY-MM');
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('quota_at_period_start')->nullable()->comment('null = unbegrenzt');
            $table->timestamps();

            $table->unique(['company_id', 'period'], 'lqu_company_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_quota_usages');
    }
};
