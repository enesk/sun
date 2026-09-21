<?php

use App\Guide\Legacy\LegacyPipelineBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rueckbau der alten Content-Pipeline, Central-Teil (#23): Quellen-Einstellungen
 * der Connectoren und Fingerprints der Dublettenpruefung. Sicherung vorab wie
 * im Tenant-Teil (tenant/2026_09_22_000005), down() legt beide Tabellen leer
 * mit dem vorherigen Schema wieder an.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LegacyPipelineBackup::class)->backupCentral();

        foreach (LegacyPipelineBackup::CENTRAL_TABLES as $table) {
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
    }

    /**
     * Schema vor dem Drop (SHOW CREATE TABLE).
     *
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            'content_source_settings' => <<<'SQL'
                CREATE TABLE `content_source_settings` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `source_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
                  `frequency_override` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `weight` smallint unsigned NOT NULL DEFAULT '100',
                  `disabled_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `updated_by_user_id` bigint unsigned DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `content_source_settings_source_key_unique` (`source_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            'content_fingerprints' => <<<'SQL'
                CREATE TABLE `content_fingerprints` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `tenant_id` bigint unsigned NOT NULL,
                  `article_id` bigint unsigned NOT NULL,
                  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `simhash` bigint unsigned NOT NULL,
                  `embedding_json` json DEFAULT NULL,
                  `primary_keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `published_at` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `content_fingerprints_tenant_id_article_id_unique` (`tenant_id`,`article_id`),
                  KEY `content_fingerprints_simhash_index` (`simhash`),
                  KEY `content_fingerprints_tenant_id_index` (`tenant_id`),
                  KEY `content_fingerprints_primary_keyword_index` (`primary_keyword`),
                  CONSTRAINT `content_fingerprints_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
        ];
    }
};
