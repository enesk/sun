<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh-Loop (#24).
 *
 * Ein Refresh schreibt den veroeffentlichten Artikel nicht um, sondern legt
 * eine neue Fassung des Entwurfs an. Dafuer zwei Spalten:
 *
 *  1. `parent_draft_id` zeigt auf die Fassung, aus der die neue entstanden
 *     ist. Daran haengt alles Weitere: die Gegenueberstellung in der Pruefung
 *     (#20) vergleicht Kind und Elternfassung, die Uebersicht (#19) zaehlt
 *     Kinder nicht gegen das Tagesziel von zwei neuen Artikeln, und das
 *     Tageslimit von drei Refreshes je Mandant zaehlt genau diese Zeilen.
 *     `nullOnDelete`: verschwindet die Elternfassung, bleibt die neue gueltig
 *     — sie ist der Artikel, der live steht.
 *
 *  2. `changelog_json` haelt die Aenderungshinweise ("Aktualisiert am ...")
 *     in der Reihenfolge ihres Entstehens. Jede Fassung erbt die Liste ihrer
 *     Elternfassung und haengt einen Eintrag an; das Ratgeber-Template
 *     rendert sie unter der Autorenbox.
 *
 * Der zusammengesetzte Index traegt die Tageszaehlung
 * (parent_draft_id IS NOT NULL AND created_at >= heute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_draft_id')->nullable()->after('topic_candidate_id');
            $table->json('changelog_json')->nullable()->after('refresh_reason_json');

            $table->foreign('parent_draft_id')
                ->references('id')
                ->on('article_drafts')
                ->nullOnDelete();

            $table->index(['parent_draft_id', 'created_at'], 'ad_parent_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->dropForeign(['parent_draft_id']);
            $table->dropIndex('ad_parent_created_index');
            $table->dropColumn(['parent_draft_id', 'changelog_json']);
        });
    }
};
