<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anzeigebloecke der Altartikel (#34, App\Guide\Models\LegacyArticle).
 *
 * Beitraege, die noch die alte Content-Pipeline veroeffentlicht hat, zeigten
 * Kurzantwort, FAQ, Key-Facts, Quellen, Titelbild und Meta-Angaben aus deren
 * Entwurfstabelle. Diese Tabelle haelt genau diese Bloecke, eine Zeile je
 * Beitrag; befuellt wird sie einmalig von der folgenden Migration
 * 2026_09_23_000002, bevor sie die Pipeline-Tabellen droppt.
 *
 * `outline_json` enthaelt nur noch Regionalblock (regional_*) und HowTo,
 * `assets_json` nur den Titelbild-Bericht (hero), `sources_json` nur die
 * zitierten Quellen in Anzeigereihenfolge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_legacy_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id')->unique();
            // Herkunft zum Nachschlagen in der NDJSON-Sicherung, kein Fremdschluessel
            $table->unsignedBigInteger('origin_draft_id')->nullable();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('short_answer')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('key_facts_json')->nullable();
            $table->json('faq_json')->nullable();
            $table->json('sources_json')->nullable();
            $table->json('changelog_json')->nullable();
            $table->json('outline_json')->nullable();
            $table->json('assets_json')->nullable();
            $table->json('backlinks_json')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('hero_image_credit')->nullable();
            $table->string('hero_image_source', 32)->nullable();
            $table->string('region_scope', 16)->default('national');
            $table->string('region_code', 32)->nullable();
            $table->timestamp('published_at')->nullable();
            // Letzte redaktionelle Aenderung des Entwurfs: Grundlage von dateModified/lastmod
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('article_id')->references('id')->on('posts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_legacy_articles');
    }
};
