<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Themen-Import (#6): Gliederungsvorgabe und normalisierte Frage je
 * Listen-Item sowie die Zuweisung Liste -> Tenant (docs/guide-system.md, §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_topic_list_items', function (Blueprint $table) {
            // Vorgegebene Ueberschriften in der Form von guide_topics.outline_json, Platzhalter unaufgeloest.
            $table->json('outline_json')->nullable()->after('category_name');
            // Duplikaterkennung innerhalb der Liste (App\Guide\Import\QuestionNormalizer).
            $table->string('question_normalized', 500)->nullable()->after('question');

            $table->index(['guide_topic_list_id', 'question_normalized'], 'guide_topic_list_items_list_normalized_index');
        });

        Schema::create('guide_topic_list_tenant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_topic_list_id')
                ->constrained('guide_topic_lists')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['guide_topic_list_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_topic_list_tenant');

        Schema::table('guide_topic_list_items', function (Blueprint $table) {
            $table->dropIndex('guide_topic_list_items_list_normalized_index');
            $table->dropColumn(['outline_json', 'question_normalized']);
        });
    }
};
