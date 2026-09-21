<?php

use App\Guide\Legacy\LegacyPipelineBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rueckbau der alten Content-Pipeline, Teil 3 (#34): Altartikel-Daten
 * uebernehmen, danach `article_drafts` und `draft_sources` droppen.
 *
 * Ablauf je Portal (tenants:migrate), jeder Schritt bricht bei einem Fehler
 * die Migration ab, bevor etwas geloescht ist:
 *
 *  1. Sicherung von article_drafts, draft_sources und article_metrics als
 *     NDJSON (LegacyPipelineBackup, storage/app/backups/legacy-content/<datum>/).
 *  2. Uebernahme der Anzeigebloecke in guide_legacy_articles: je Beitrag die
 *     juengste live stehende Fassung (veroeffentlicht, nicht zurueckgezogen) —
 *     dieselbe, die Artikelseite und Blog bisher gezeigt haben. Idempotent
 *     ueber article_id; ein zweiter Lauf ueberschreibt mit denselben Werten.
 *  3. Pruefung: jede erwartete Zeile muss danach in guide_legacy_articles
 *     stehen.
 *  4. article_metrics.article_draft_id faellt (die Zeilen haengen seit jeher
 *     zusaetzlich an article_id -> posts.id), dann beide Tabellen.
 *
 * `posts` wird nie angefasst.
 *
 * down() legt beide Tabellen mit exakt dem vorherigen Schema leer wieder an
 * und setzt article_metrics.article_draft_id samt Fremdschluessel zurueck
 * (Werte leer). Daten zurueck:
 *
 *   php artisan guide:legacy:backup --restore=<datum> --tables=article_drafts,draft_sources
 */
return new class extends Migration
{
    private const DRAFTS = 'article_drafts';

    private const SOURCES = 'draft_sources';

    public function up(): void
    {
        $tenant = tenant();

        if (Schema::hasTable(self::DRAFTS)) {
            app(LegacyPipelineBackup::class)->backupTenant(
                (int) ($tenant?->getKey() ?? 0),
                tables: [self::DRAFTS, self::SOURCES, 'article_metrics'],
            );

            $this->transfer();
        }

        if (Schema::hasColumn('article_metrics', 'article_draft_id')) {
            Schema::table('article_metrics', function (Blueprint $table): void {
                if ($this->hasForeignKey('article_metrics', 'article_metrics_article_draft_id_foreign')) {
                    $table->dropForeign('article_metrics_article_draft_id_foreign');
                }

                $table->dropColumn('article_draft_id');
            });
        }

        Schema::dropIfExists(self::SOURCES);
        Schema::dropIfExists(self::DRAFTS);
    }

    public function down(): void
    {
        foreach ($this->tables() as $table => $sql) {
            if (! Schema::hasTable($table)) {
                DB::statement($sql);
            }
        }

        if (Schema::hasTable('article_metrics') && ! Schema::hasColumn('article_metrics', 'article_draft_id')) {
            Schema::table('article_metrics', function (Blueprint $table): void {
                $table->unsignedBigInteger('article_draft_id')->nullable()->after('article_id');
                $table->foreign('article_draft_id')->references('id')->on(self::DRAFTS)->nullOnDelete();
            });
        }
    }

    /**
     * Anzeigebloecke je Beitrag nach guide_legacy_articles.
     */
    private function transfer(): void
    {
        $latest = DB::table(self::DRAFTS)
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('article_id')
            ->where('status', 'published')
            ->whereNull('withdrawn_at')
            ->whereIn('article_id', DB::table('posts')->select('id'))
            ->groupBy('article_id');

        $expected = [];

        foreach (DB::table(self::DRAFTS)->whereIn('id', $latest)->orderBy('id')->cursor() as $draft) {
            DB::table('guide_legacy_articles')->updateOrInsert(
                ['article_id' => (int) $draft->article_id],
                [...$this->row($draft), 'updated_at' => now(), 'created_at' => now()],
            );

            $expected[(int) $draft->article_id] = (int) $draft->id;
        }

        $stored = DB::table('guide_legacy_articles')
            ->whereIn('article_id', array_keys($expected))
            ->pluck('origin_draft_id', 'article_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($expected as $articleId => $draftId) {
            if (($stored[$articleId] ?? null) !== $draftId) {
                throw new RuntimeException("Altartikel {$articleId}: Uebernahme von Entwurf {$draftId} fehlt, Drop abgebrochen.");
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $draft): array
    {
        $outline = $this->decode($draft->outline_json);
        $assets = $this->decode($draft->assets_json);
        $publication = $this->decode($draft->publication_json);

        $backlinks = [];

        foreach ((array) ($publication['backlinks'] ?? []) as $backlink) {
            $id = (int) (((array) $backlink)['article_id'] ?? 0);

            if ($id > 0) {
                $backlinks[$id] = $id;
            }
        }

        $sources = DB::table(self::SOURCES)
            ->where('article_draft_id', $draft->id)
            ->where('is_cited', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['title', 'url', 'publisher', 'published_at'])
            ->map(fn (object $source): array => (array) $source)
            ->all();

        $display = array_intersect_key($outline, array_flip(['regional_facts', 'regional_intro', 'regional_outro', 'howto']));

        return [
            'origin_draft_id' => (int) $draft->id,
            'title' => (string) $draft->title,
            'meta_title' => $draft->meta_title,
            'meta_description' => $draft->meta_description,
            'short_answer' => $draft->short_answer,
            'body_html' => $draft->body_html,
            'key_facts_json' => $draft->key_facts_json,
            'faq_json' => $draft->faq_json,
            'sources_json' => $this->encode($sources),
            'changelog_json' => $draft->changelog_json,
            'outline_json' => $this->encode($display),
            'assets_json' => $this->encode(isset($assets['hero']) ? ['hero' => $assets['hero']] : []),
            'backlinks_json' => $this->encode(array_values($backlinks)),
            'hero_image_alt' => $draft->hero_image_alt,
            'hero_image_credit' => $draft->hero_image_credit,
            'hero_image_source' => $draft->hero_image_source,
            'region_scope' => (string) ($draft->region_scope ?: 'national'),
            'region_code' => $draft->region_code,
            'published_at' => $draft->published_at,
            'content_updated_at' => $draft->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $json): array
    {
        $value = $json === null ? null : json_decode($json, true);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function encode(array $value): ?string
    {
        return $value === [] ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['name'] === $name);
    }

    /**
     * Schema vor dem Drop (SHOW CREATE TABLE), Eltern vor Kindern. Der
     * Fremdschluessel auf topic_candidates fehlt bewusst — er fiel schon in
     * 2026_09_22_000005, die Spalte blieb.
     *
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            self::DRAFTS => <<<'SQL'
                CREATE TABLE `article_drafts` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `topic_candidate_id` bigint unsigned DEFAULT NULL,
                  `parent_draft_id` bigint unsigned DEFAULT NULL,
                  `article_id` bigint unsigned DEFAULT NULL,
                  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generating',
                  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `short_answer` text COLLATE utf8mb4_unicode_ci,
                  `outline_json` json DEFAULT NULL,
                  `body_html` longtext COLLATE utf8mb4_unicode_ci,
                  `faq_json` json DEFAULT NULL,
                  `key_facts_json` json DEFAULT NULL,
                  `hero_image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `hero_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `hero_image_credit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `hero_image_source` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `infographic_svg_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `assets_json` json DEFAULT NULL,
                  `publication_json` json DEFAULT NULL,
                  `region_scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'national',
                  `region_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `quality_score` decimal(5,2) DEFAULT NULL,
                  `quality_report_json` json DEFAULT NULL,
                  `attempt` tinyint unsigned NOT NULL DEFAULT '0',
                  `scheduled_for` timestamp NULL DEFAULT NULL,
                  `published_at` timestamp NULL DEFAULT NULL,
                  `needs_refresh` tinyint(1) NOT NULL DEFAULT '0',
                  `needs_refresh_at` timestamp NULL DEFAULT NULL,
                  `refresh_reason_json` json DEFAULT NULL,
                  `changelog_json` json DEFAULT NULL,
                  `generation_cost_usd` decimal(10,4) NOT NULL DEFAULT '0.0000',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  `withdrawn_at` timestamp NULL DEFAULT NULL,
                  `withdrawn_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Pflichtgrund im Klartext, wird im Dashboard angezeigt',
                  `withdrawn_by` bigint unsigned DEFAULT NULL COMMENT 'content_users.id in der Central-DB, daher kein Fremdschluessel',
                  PRIMARY KEY (`id`),
                  KEY `article_drafts_status_index` (`status`),
                  KEY `article_drafts_scheduled_for_index` (`scheduled_for`),
                  KEY `article_drafts_published_at_index` (`published_at`),
                  KEY `article_drafts_article_id_index` (`article_id`),
                  KEY `article_drafts_slug_index` (`slug`),
                  KEY `article_drafts_withdrawn_at_index` (`withdrawn_at`),
                  KEY `article_drafts_needs_refresh_index` (`needs_refresh`),
                  KEY `ad_parent_created_index` (`parent_draft_id`,`created_at`),
                  KEY `article_drafts_topic_candidate_id_foreign` (`topic_candidate_id`),
                  CONSTRAINT `article_drafts_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
                  CONSTRAINT `article_drafts_parent_draft_id_foreign` FOREIGN KEY (`parent_draft_id`) REFERENCES `article_drafts` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            self::SOURCES => <<<'SQL'
                CREATE TABLE `draft_sources` (
                  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                  `article_draft_id` bigint unsigned NOT NULL,
                  `source_item_id` bigint unsigned DEFAULT NULL,
                  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `snippet` text COLLATE utf8mb4_unicode_ci,
                  `published_at` timestamp NULL DEFAULT NULL,
                  `is_cited` tinyint(1) NOT NULL DEFAULT '0',
                  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `draft_sources_article_draft_id_sort_order_index` (`article_draft_id`,`sort_order`),
                  KEY `draft_sources_source_item_id_foreign` (`source_item_id`),
                  CONSTRAINT `draft_sources_article_draft_id_foreign` FOREIGN KEY (`article_draft_id`) REFERENCES `article_drafts` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
        ];
    }
};
