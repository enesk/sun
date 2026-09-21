<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ratgeber-LLM ueber Claude-CLI oder Messages API (#42): jede Zeile im
 * Kosten-Log sagt, ueber welchen Treiber sie lief. Beim Treiber 'cli' ist
 * cost_usd rechnerisch (Abo), nicht abgerechnet. Alte Zeilen bleiben NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('llm_usage_logs', 'driver')) {
            return;
        }

        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->string('driver', 8)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('llm_usage_logs', 'driver')) {
            return;
        }

        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
    }
};
