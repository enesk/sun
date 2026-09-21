<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknuepft die bestehende Artikel-Tabelle `posts` mit dem Ratgebersystem.
 *
 * `posts` bleibt das Ziel fuer veroeffentlichte Inhalte (docs/guide-system.md,
 * §4); handgeschriebene Beitraege behalten beide Spalten leer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('guide_topic_id')
                ->nullable()
                ->after('category_id')
                ->constrained('guide_topics')
                ->nullOnDelete();
            $table->foreignId('guide_category_id')
                ->nullable()
                ->after('guide_topic_id')
                ->constrained('guide_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['guide_topic_id']);
            $table->dropForeign(['guide_category_id']);
            $table->dropColumn(['guide_topic_id', 'guide_category_id']);
        });
    }
};
