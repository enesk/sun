<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Tabellen der Content-Pipeline (siehe docs/content-pipeline.md, §4).
 *
 * Additiv: keine bestehende Tabelle wird veraendert. Die veroeffentlichten
 * Ratgeber landen in der bereits vorhandenen Artikel-Tabelle `posts`;
 * `article_drafts.article_id` ist die Klammer dorthin.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rohsignale der Quell-Connectoren (#7-#11), normalisiert.
        Schema::create('source_items', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64);            // z. B. google_trends, gsc, dataforseo_serp
            $table->string('source_type', 32);           // rss, api, serp, internal
            $table->string('external_id')->nullable();   // ID beim Anbieter, falls vorhanden
            $table->string('fingerprint', 64);           // sha256 ueber Titel + URL, gegen Doppelimporte
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('language', 8)->default('de');
            $table->string('region_scope', 16)->default('national'); // national|state|city
            $table->string('region_code', 32)->nullable();
            $table->json('payload_json')->nullable();    // Rohantwort des Anbieters
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source_key', 'fingerprint']);
            $table->index(['source_key', 'fetched_at']);
            $table->index('published_at');
        });

        // Keyword-Cluster (#12): buendelt Themen, verhindert Kannibalisierung.
        Schema::create('keyword_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('primary_keyword');
            $table->json('keywords_json')->nullable();
            $table->json('centroid_json')->nullable();   // Embedding-Zentroid, PHP-seitige Similarity
            $table->string('region_scope', 16)->default('national');
            $table->string('region_code', 32)->nullable();
            $table->unsignedInteger('article_count')->default(0);
            $table->timestamp('last_article_at')->nullable();
            $table->timestamps();

            $table->index(['region_scope', 'region_code']);
        });

        // Themenkandidaten mit Scoring (#12).
        Schema::create('topic_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('primary_keyword');
            $table->json('secondary_keywords_json')->nullable();
            $table->json('source_item_ids_json')->nullable();
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->string('intent', 32)->nullable();     // informational|commercial|transactional|navigational
            $table->string('region_scope', 16)->default('national');
            $table->string('region_code', 32)->nullable();
            $table->unsignedInteger('search_volume')->nullable();
            $table->decimal('trend_score', 5, 2)->default(0);
            $table->decimal('demand_score', 5, 2)->default(0);
            $table->decimal('gap_score', 5, 2)->default(0);
            $table->decimal('seasonal_score', 5, 2)->default(0);
            $table->decimal('uniqueness_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->string('status', 20)->default('discovered'); // TopicStatus
            $table->string('rejection_reason', 255)->nullable();
            $table->date('selected_for_date')->nullable();
            $table->timestamps();

            $table->foreign('cluster_id')
                ->references('id')
                ->on('keyword_clusters')
                ->nullOnDelete();

            $table->index(['status', 'total_score']);
            $table->index('selected_for_date');
            $table->index('primary_keyword');
        });

        // Artikelentwuerfe (#14, #15, #21).
        Schema::create('article_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_candidate_id')->nullable();
            $table->unsignedBigInteger('article_id')->nullable(); // -> posts.id nach Veroeffentlichung
            $table->string('status', 20)->default('generating');  // DraftStatus
            $table->string('title');
            $table->string('slug');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('short_answer')->nullable();
            $table->json('outline_json')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('faq_json')->nullable();
            $table->json('key_facts_json')->nullable();
            $table->string('region_scope', 16)->default('national'); // national|state|city
            $table->string('region_code', 32)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->json('quality_report_json')->nullable();
            $table->unsignedTinyInteger('attempt')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->decimal('generation_cost_usd', 10, 4)->default(0);
            $table->timestamps();

            $table->foreign('topic_candidate_id')
                ->references('id')
                ->on('topic_candidates')
                ->nullOnDelete();

            $table->index('status');
            $table->index('scheduled_for');
            $table->index('published_at');
            $table->index('article_id');
            $table->index('slug');
        });

        // Die Artikel-Tabelle heisst in diesem Projekt `posts` (siehe docs).
        if (Schema::hasTable('posts')) {
            Schema::table('article_drafts', function (Blueprint $table) {
                $table->foreign('article_id')
                    ->references('id')
                    ->on('posts')
                    ->nullOnDelete();
            });
        }

        // Belegte Quellen je Entwurf (Faktencheck, #15).
        Schema::create('draft_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_draft_id');
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->string('publisher')->nullable();
            $table->text('snippet')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_cited')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('article_draft_id')
                ->references('id')
                ->on('article_drafts')
                ->cascadeOnDelete();

            $table->foreign('source_item_id')
                ->references('id')
                ->on('source_items')
                ->nullOnDelete();

            $table->index(['article_draft_id', 'sort_order']);
        });

        // Einzelne belegbare Fakten (#15): Zahl + Quelle + Stand.
        Schema::create('fact_snippets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_draft_id')->nullable();
            $table->unsignedBigInteger('topic_candidate_id')->nullable();
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->string('fingerprint', 64);
            $table->text('statement');
            $table->string('value', 128)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('period', 64)->nullable();     // z. B. "2025" oder "Q1 2026"
            $table->string('source_name')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Reine Zuordnung, kein Eigentum: Faktenschnipsel sind seit #11
            // gemeinsamer Bestand des Mandanten. Ein geloeschter Entwurf setzt
            // die Spalte zurueck, statt den Schnipsel mitzunehmen (#63).
            $table->foreign('article_draft_id')
                ->references('id')
                ->on('article_drafts')
                ->nullOnDelete();

            $table->foreign('topic_candidate_id')
                ->references('id')
                ->on('topic_candidates')
                ->nullOnDelete();

            $table->foreign('source_item_id')
                ->references('id')
                ->on('source_items')
                ->nullOnDelete();

            $table->index('fingerprint');
        });

        // Saisonkalender (#13): was wann vorproduziert werden muss.
        Schema::create('seasonal_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('primary_keyword');
            $table->json('keywords_json')->nullable();
            $table->unsignedTinyInteger('start_month');   // 1-12
            $table->unsignedTinyInteger('end_month');     // 1-12, kann kleiner sein (Jahreswechsel)
            $table->unsignedSmallInteger('lead_time_days')->default(21);
            $table->string('region_scope', 16)->default('national');
            $table->string('region_code', 32)->nullable();
            $table->decimal('weight', 5, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_month', 'end_month']);
        });

        // Tagesmetriken je veroeffentlichtem Artikel (#23).
        Schema::create('article_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('article_id');     // -> posts.id
            $table->unsignedBigInteger('article_draft_id')->nullable();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->unsignedInteger('pageviews')->default(0);
            $table->decimal('adsense_revenue_usd', 10, 4)->default(0);
            $table->timestamps();

            $table->foreign('article_draft_id')
                ->references('id')
                ->on('article_drafts')
                ->nullOnDelete();

            $table->unique(['article_id', 'date']);
            $table->index('date');
        });

        if (Schema::hasTable('posts')) {
            Schema::table('article_metrics', function (Blueprint $table) {
                $table->foreign('article_id')
                    ->references('id')
                    ->on('posts')
                    ->cascadeOnDelete();
            });
        }

        // Redaktionelle Einstellungen des Mandanten (eine Zeile je Tenant-DB).
        Schema::create('tenant_content_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('articles_per_day')->default(2);
            $table->unsignedTinyInteger('auto_publish_threshold')->default(80); // YMYL: 90
            $table->boolean('is_ymyl')->default(false);
            $table->string('tone', 32)->default('sachlich');
            $table->json('allowed_region_scopes_json')->nullable();
            $table->json('preferred_states_json')->nullable();
            $table->time('publish_window_start')->default('09:00:00');
            $table->time('publish_window_end')->default('17:00:00');
            $table->string('author_name')->nullable();
            $table->text('author_bio')->nullable();
            $table->json('organization_same_as_json')->nullable();
            $table->json('category_mapping_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('article_metrics') && Schema::hasTable('posts')) {
            Schema::table('article_metrics', function (Blueprint $table) {
                $table->dropForeign(['article_id']);
            });
        }

        if (Schema::hasTable('article_drafts') && Schema::hasTable('posts')) {
            Schema::table('article_drafts', function (Blueprint $table) {
                $table->dropForeign(['article_id']);
            });
        }

        Schema::dropIfExists('tenant_content_settings');
        Schema::dropIfExists('article_metrics');
        Schema::dropIfExists('seasonal_topics');
        Schema::dropIfExists('fact_snippets');
        Schema::dropIfExists('draft_sources');
        Schema::dropIfExists('article_drafts');
        Schema::dropIfExists('topic_candidates');
        Schema::dropIfExists('keyword_clusters');
        Schema::dropIfExists('source_items');
    }
};
