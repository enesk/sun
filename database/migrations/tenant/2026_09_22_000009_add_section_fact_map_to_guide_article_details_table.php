<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche Fakt-Schluessel in welchem Abschnitt stehen (#9,
 * App\Guide\Research\SectionFactMap). Grundlage fuer changed_section_ids
 * der Aenderungserkennung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->json('section_fact_map_json')->nullable()->after('changelog_json');
        });
    }

    public function down(): void
    {
        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->dropColumn('section_fact_map_json');
        });
    }
};
