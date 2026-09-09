<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rohdaten der Search Console je Seite und Suchanfrage (#9).
 *
 * `article_metrics` haelt die verdichtete Tagesmetrik je veroeffentlichtem
 * Ratgeber. Der Gap-Connector braucht daneben die unverdichtete Ebene:
 * Suchanfrage x Seite ueber ein 28-Tage-Fenster, auch fuer Seiten, die noch
 * gar kein Ratgeber sind. Genau das steht hier und ist die Grundlage des
 * Metrik-Collectors (#23) und des Refresh-Loops (#24).
 *
 * `page` und `query` sind zu lang fuer einen Index, deshalb liegt die
 * Eindeutigkeit auf den sha256-Hashes beider Werte.
 *
 * Zusaetzlich bekommt `tenant_content_settings` die Property-URL des
 * Mandanten (z. B. 'sc-domain:example.de' oder 'https://example.de/').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_metrics_raw', function (Blueprint $table) {
            $table->id();

            // Enddatum des abgerufenen Fensters; ein Snapshot je Abruftag.
            $table->date('window_end');
            $table->unsignedSmallInteger('window_days')->default(28);

            $table->string('property');
            $table->string('page', 2048);
            $table->string('page_path', 512);
            $table->char('page_hash', 64);
            $table->string('query', 512);
            $table->char('query_hash', 64);

            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('position', 6, 2)->nullable();

            // Liegt die Seite unter dem Ratgeber-Praefix?
            $table->boolean('is_article')->default(false);

            $table->timestamps();

            $table->unique(['window_end', 'page_hash', 'query_hash'], 'amr_window_page_query_unique');
            $table->index(['window_end', 'is_article'], 'amr_window_article_index');
            $table->index(['window_end', 'impressions'], 'amr_window_impressions_index');
            $table->index(['page_hash', 'window_end'], 'amr_page_window_index');
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->string('gsc_property')->nullable()->after('branch_keywords_json');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('gsc_property');
        });

        Schema::dropIfExists('article_metrics_raw');
    }
};
