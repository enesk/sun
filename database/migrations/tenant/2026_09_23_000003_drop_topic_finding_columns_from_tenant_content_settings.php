<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rueckbau der alten Content-Pipeline, Teil 3 (#34): Spalten der
 * Themenfindung und Tagesplanung aus tenant_content_settings.
 *
 * Die Themenfindung ist seit #23 geloescht, Tagesplanung und Faelligkeit
 * stehen im Ratgebersystem (tenant_guide_settings, config/guide.php). Es
 * bleiben Rollout-Schalter, Auto-Live-Schwelle, YMYL, Tonalitaet,
 * Schwerpunktregion, Autor/Organisation, Markenfarben und Search Console.
 *
 * down() legt die Spalten mit Typ, Vorgabe und Position wieder an; die Werte
 * sind weg (Branchen-Keywords liessen sich aus BranchResolver neu ableiten,
 * der Rest war Themenfindung).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'articles_per_day',
        'allowed_region_scopes_json',
        'branch_keywords_json',
        'scoring_weights_json',
        'publish_window_start',
        'publish_window_end',
        'category_mapping_json',
        'cluster_performance_json',
    ];

    public function up(): void
    {
        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('tenant_content_settings', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('tenant_content_settings', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('articles_per_day')->default(2)->after('id');
            $table->json('allowed_region_scopes_json')->nullable()->after('tone');
            $table->json('branch_keywords_json')->nullable()->after('preferred_states_json');
            $table->json('scoring_weights_json')->nullable()->after('branch_keywords_json');
            $table->time('publish_window_start')->default('09:00:00')->after('gsc_check_detail');
            $table->time('publish_window_end')->default('17:00:00')->after('publish_window_start');
            $table->json('category_mapping_json')->nullable()->after('author_same_as_json');
            $table->json('cluster_performance_json')->nullable()->after('category_mapping_json');
        });
    }
};
