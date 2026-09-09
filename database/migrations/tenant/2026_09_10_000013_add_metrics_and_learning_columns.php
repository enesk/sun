<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metrik-Collector und Lernschleife (#23).
 *
 * Drei Ergaenzungen am Bestand:
 *
 *  1. `article_metrics` bekommt die Snapshot-Flags d7/d30/d90 und das
 *     Fenster, aus dem die Zeile stammt. `pageviews` und
 *     `adsense_revenue_usd` werden nullable: ohne AdSense-Zuordnung ist der
 *     Wert unbekannt und nicht null Euro — der Unterschied entscheidet
 *     spaeter darueber, ob ein Artikel als ertragslos oder als ungemessen
 *     gilt.
 *  2. `article_drafts` bekommt die Refresh-Markierung, die der Collector bei
 *     Positionsverlust oder CTR-Einbruch setzt. Sie ist die Eingangsgroesse
 *     des Refresh-Loops (#24).
 *  3. `tenant_content_settings` bekommt `cluster_performance_json`, das
 *     Ergebnis der woechentlichen Lernschleife, und `topic_candidates` den
 *     zugehoerigen Teilscore `performance_score` (#12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_metrics', function (Blueprint $table) {
            // Enddatum und Laenge des Search-Console-Fensters, aus dem die
            // Zeile verdichtet wurde. `date` bleibt der Tag der Erhebung.
            $table->unsignedSmallInteger('window_days')->default(28)->after('date');

            $table->boolean('is_d7')->default(false)->after('adsense_revenue_usd');
            $table->boolean('is_d30')->default(false)->after('is_d7');
            $table->boolean('is_d90')->default(false)->after('is_d30');

            $table->index(['article_id', 'is_d7'], 'am_article_d7_index');
            $table->index(['article_id', 'is_d30'], 'am_article_d30_index');
            $table->index(['article_id', 'is_d90'], 'am_article_d90_index');
        });

        Schema::table('article_metrics', function (Blueprint $table) {
            $table->unsignedInteger('pageviews')->nullable()->default(null)->change();
            $table->decimal('adsense_revenue_usd', 10, 4)->nullable()->default(null)->change();
        });

        Schema::table('article_drafts', function (Blueprint $table) {
            $table->boolean('needs_refresh')->default(false)->after('published_at');
            $table->timestamp('needs_refresh_at')->nullable()->after('needs_refresh');
            $table->json('refresh_reason_json')->nullable()->after('needs_refresh_at');

            $table->index('needs_refresh');
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->json('cluster_performance_json')->nullable()->after('category_mapping_json');
        });

        Schema::table('topic_candidates', function (Blueprint $table) {
            $table->decimal('performance_score', 5, 2)->default(0)->after('uniqueness_score');
        });
    }

    public function down(): void
    {
        Schema::table('topic_candidates', function (Blueprint $table) {
            $table->dropColumn('performance_score');
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('cluster_performance_json');
        });

        Schema::table('article_drafts', function (Blueprint $table) {
            $table->dropIndex(['needs_refresh']);
            $table->dropColumn(['needs_refresh', 'needs_refresh_at', 'refresh_reason_json']);
        });

        Schema::table('article_metrics', function (Blueprint $table) {
            $table->dropIndex('am_article_d7_index');
            $table->dropIndex('am_article_d30_index');
            $table->dropIndex('am_article_d90_index');
            $table->dropColumn(['window_days', 'is_d7', 'is_d30', 'is_d90']);
        });

        Schema::table('article_metrics', function (Blueprint $table) {
            $table->unsignedInteger('pageviews')->default(0)->change();
            $table->decimal('adsense_revenue_usd', 10, 4)->default(0)->change();
        });
    }
};
