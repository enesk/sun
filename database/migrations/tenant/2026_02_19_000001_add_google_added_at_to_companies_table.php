<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('google_added_at')->nullable()->after('logo_path');

            // Compound Index für den Bot-Query: WHERE google_added_at IS NULL AND website IS NULL
            // MySQL kann partial indexes nicht, aber dieser Compound Index
            // beschleunigt den Filter massiv (statt Full Table Scan)
            $table->index(['google_added_at', 'website'], 'idx_companies_bot_queue');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_bot_queue');
            $table->dropColumn('google_added_at');
        });
    }
};
