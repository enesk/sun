<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publisher (#12):
 *  - guide_article_versions.published_at: wann die Fassung live ging; leer
 *    bei Entwuerfen aus der Pruef-Queue und bei inhaltsgleichen Fassungen.
 *  - guide_article_details.published_version_id: die Fassung, die gerade in
 *    posts steht (Diff/Rollback #16, Schutz vor dem Prune-Command).
 *  - guide_topic_runs.publish_json: Protokoll der Veroeffentlichung
 *    (Aktion, Artikel, Fassung, IndexNow-Antwort).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_article_versions', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('change_summary');
        });

        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->foreignId('published_version_id')
                ->nullable()
                ->after('article_id')
                ->constrained('guide_article_versions')
                ->nullOnDelete();
        });

        Schema::table('guide_topic_runs', function (Blueprint $table) {
            $table->json('publish_json')->nullable()->after('quality_report_json');
        });
    }

    public function down(): void
    {
        Schema::table('guide_topic_runs', function (Blueprint $table) {
            $table->dropColumn('publish_json');
        });

        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
            $table->dropColumn('published_version_id');
        });

        Schema::table('guide_article_versions', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
