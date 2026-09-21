<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenbuchung des Ratgebersystems (#5): Thema, Template und Zahl der
 * Web-Suchen je Aufruf. Die alte Pipeline laesst die Spalten leer.
 *
 * Das Budget zaehlt ueber operation LIKE 'guide.%' und created_at; der Index
 * (operation, created_at) traegt diese Summe, der Index auf guide_topic_id die
 * Kostenansicht je Thema (#16). guide_topic_id zeigt in die Tenant-DB und hat
 * deshalb keinen Fremdschluessel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('guide_topic_id')->nullable()->after('reference_id');
            $table->string('template_key', 64)->nullable()->after('guide_topic_id');
            $table->unsignedSmallInteger('search_count')->default(0)->after('cache_read_tokens');

            $table->index(['operation', 'created_at']);
            $table->index(['tenant_id', 'guide_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::table('llm_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['operation', 'created_at']);
            $table->dropIndex(['tenant_id', 'guide_topic_id']);
            $table->dropColumn(['guide_topic_id', 'template_key', 'search_count']);
        });
    }
};
