<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikel-Writer (#10): Eine Fassung traegt alles, was der Publisher (#12)
 * in posts und guide_article_details schreibt. Bis zur Veroeffentlichung
 * (Pruef-Queue) gibt es sonst keinen Ort fuer Kurzantwort, Meta, Changelog
 * und SectionFactMap des Entwurfs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_article_versions', function (Blueprint $table) {
            $table->text('short_answer')->nullable()->after('body_html');
            $table->string('meta_title')->nullable()->after('title');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->json('changelog_json')->nullable()->after('key_facts_json');
            $table->json('section_fact_map_json')->nullable()->after('changelog_json');
        });
    }

    public function down(): void
    {
        Schema::table('guide_article_versions', function (Blueprint $table) {
            $table->dropColumn(['short_answer', 'meta_title', 'meta_description', 'changelog_json', 'section_fact_map_json']);
        });
    }
};
