<?php

use App\Guide\Legacy\LegacyPipelineBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rueckbau der alten Content-Pipeline, Tenant-Teil (#23, Liste docs/guide-system.md §9).
 *
 * Entfernt die Tabellen der Themenfindung. Vorher sichert up() sie samt
 * article_drafts/draft_sources als NDJSON (LegacyPipelineBackup); scheitert
 * die Sicherung, bricht die Migration ab und nichts wird geloescht.
 *
 * `posts` wird nie angefasst. article_drafts und draft_sources bleiben:
 * Altartikel (GuidePageData) und Leistungsdaten (article_metrics) lesen sie
 * noch. Deren Spalten topic_candidate_id/source_item_id bleiben mit ihren
 * Werten stehen, nur die Fremdschluessel fallen.
 *
 * down() legt die Tabellen mit exakt dem vorherigen Schema leer wieder an und
 * setzt die beiden Fremdschluessel zurueck (ohne Pruefung der Altwerte, damit
 * die Rueckspielung per `guide:legacy:backup --restore=<datum>` passt).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenant = tenant();

        app(LegacyPipelineBackup::class)->backupTenant((int) ($tenant?->getKey() ?? 0));

        if (Schema::hasTable('article_drafts') && $this->hasForeignKey('article_drafts', 'article_drafts_topic_candidate_id_foreign')) {
            Schema::table('article_drafts', function (Blueprint $table): void {
                $table->dropForeign('article_drafts_topic_candidate_id_foreign');
            });
        }

        if (Schema::hasTable('draft_sources') && $this->hasForeignKey('draft_sources', 'draft_sources_source_item_id_foreign')) {
            Schema::table('draft_sources', function (Blueprint $table): void {
                $table->dropForeign('draft_sources_source_item_id_foreign');
            });
        }

        foreach (LegacyPipelineBackup::DROPPED_TENANT_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table => $sql) {
            if (! Schema::hasTable($table)) {
                DB::statement($sql);
            }
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            if (Schema::hasTable('article_drafts') && ! $this->hasForeignKey('article_drafts', 'article_drafts_topic_candidate_id_foreign')) {
                Schema::table('article_drafts', function (Blueprint $table): void {
                    $table->foreign('topic_candidate_id')->references('id')->on('topic_candidates')->nullOnDelete();
                });
            }

            if (Schema::hasTable('draft_sources') && ! $this->hasForeignKey('draft_sources', 'draft_sources_source_item_id_foreign')) {
                Schema::table('draft_sources', function (Blueprint $table): void {
                    $table->foreign('source_item_id')->references('id')->on('source_items')->nullOnDelete();
                });
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['name'] === $name);
    }

    /**
     * Schema vor dem Drop (SHOW CREATE TABLE), Eltern vor Kindern.
     *
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            'keyword_clusters' => <<<'SQL'
                CREATE TABLE `keyword_clusters` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `primary_keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `keywords_json` json DEFAULT NULL,
                  `centroid_json` json DEFAULT NULL,
                  `serp_json` json DEFAULT NULL,
                  `serp_fetched_at` timestamp NULL DEFAULT NULL,
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `article_count` int unsigned NOT NULL DEFAULT '0',
                  `last_article_at` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `keyword_clusters_slug_unique` (`slug`),
                  KEY `keyword_clusters_region_scope_region_code_index` (`region_scope`,`region_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'source_items' => <<<'SQL'
                CREATE TABLE `source_items` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `source_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `source_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `summary` text COLLATE utf8mb4_unicode_ci,
                  `body` longtext COLLATE utf8mb4_unicode_ci,
                  `url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `language` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'de',
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `keywords_json` json DEFAULT NULL,
                  `signal_strength` decimal(4,3) NOT NULL DEFAULT '0.000',
                  `payload_json` json DEFAULT NULL,
                  `published_at` timestamp NULL DEFAULT NULL,
                  `fetched_at` timestamp NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `source_items_source_key_fingerprint_unique` (`source_key`,`fingerprint`),
                  KEY `source_items_source_key_fetched_at_index` (`source_key`,`fetched_at`),
                  KEY `source_items_published_at_index` (`published_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'topic_candidates' => <<<'SQL'
                CREATE TABLE `topic_candidates` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `primary_keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `secondary_keywords_json` json DEFAULT NULL,
                  `source_item_ids_json` json DEFAULT NULL,
                  `cluster_id` bigint unsigned DEFAULT NULL,
                  `intent` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `informational_only` tinyint(1) NOT NULL DEFAULT '0',
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `region_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `region_evidence_json` json DEFAULT NULL,
                  `search_volume` int unsigned DEFAULT NULL,
                  `trend_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `demand_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `gap_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `seasonal_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `uniqueness_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `performance_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `total_score` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `score_breakdown_json` json DEFAULT NULL,
                  `rationale` text COLLATE utf8mb4_unicode_ci,
                  `simhash` bigint unsigned DEFAULT NULL,
                  `embedding_json` json DEFAULT NULL,
                  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'discovered',
                  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `selected_for_date` date DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `topic_candidates_cluster_id_foreign` (`cluster_id`),
                  KEY `topic_candidates_status_total_score_index` (`status`,`total_score`),
                  KEY `topic_candidates_selected_for_date_index` (`selected_for_date`),
                  KEY `topic_candidates_primary_keyword_index` (`primary_keyword`),
                  KEY `topic_candidates_simhash_index` (`simhash`),
                  KEY `topic_status_date_index` (`status`,`selected_for_date`),
                  CONSTRAINT `topic_candidates_cluster_id_foreign` FOREIGN KEY (`cluster_id`) REFERENCES `keyword_clusters` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'seasonal_topics' => <<<'SQL'
                CREATE TABLE `seasonal_topics` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `primary_keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `keywords_json` json DEFAULT NULL,
                  `start_month` tinyint unsigned NOT NULL,
                  `end_month` tinyint unsigned NOT NULL,
                  `lead_time_days` smallint unsigned NOT NULL DEFAULT '21',
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `weight` decimal(5,2) NOT NULL DEFAULT '1.00',
                  `is_active` tinyint(1) NOT NULL DEFAULT '1',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `seasonal_topics_is_active_start_month_end_month_index` (`is_active`,`start_month`,`end_month`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'geo_regions' => <<<'SQL'
                CREATE TABLE `geo_regions` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `state_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `population` int unsigned DEFAULT NULL,
                  `latitude` decimal(10,7) DEFAULT NULL,
                  `longitude` decimal(10,7) DEFAULT NULL,
                  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'seed',
                  `is_active` tinyint(1) NOT NULL DEFAULT '1',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `geo_regions_scope_code_unique` (`scope`,`code`),
                  KEY `geo_regions_scope_state_code_index` (`scope`,`state_code`),
                  KEY `geo_regions_slug_index` (`slug`),
                  KEY `geo_regions_population_index` (`population`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'fact_snippets' => <<<'SQL'
                CREATE TABLE `fact_snippets` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `article_draft_id` bigint unsigned DEFAULT NULL,
                  `topic_candidate_id` bigint unsigned DEFAULT NULL,
                  `source_item_id` bigint unsigned DEFAULT NULL,
                  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `fact_key` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `statement` text COLLATE utf8mb4_unicode_ci NOT NULL,
                  `value` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `unit` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `period` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `source_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `source_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `retrieved_at` timestamp NULL DEFAULT NULL,
                  `valid_until` timestamp NULL DEFAULT NULL,
                  `confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
                  `verified_at` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `fact_snippets_topic_candidate_id_foreign` (`topic_candidate_id`),
                  KEY `fact_snippets_source_item_id_foreign` (`source_item_id`),
                  KEY `fact_snippets_fingerprint_index` (`fingerprint`),
                  KEY `idx_fact_key_region` (`fact_key`,`region_scope`,`region_code`),
                  KEY `fact_snippets_valid_until_index` (`valid_until`),
                  KEY `fact_snippets_article_draft_id_foreign` (`article_draft_id`),
                  CONSTRAINT `fact_snippets_article_draft_id_foreign` FOREIGN KEY (`article_draft_id`) REFERENCES `article_drafts` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `fact_snippets_source_item_id_foreign` FOREIGN KEY (`source_item_id`) REFERENCES `source_items` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `fact_snippets_topic_candidate_id_foreign` FOREIGN KEY (`topic_candidate_id`) REFERENCES `topic_candidates` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
        ];
    }
};
