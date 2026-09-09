<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fact_snippets.article_draft_id` von cascadeOnDelete auf nullOnDelete (#63).
 *
 * Der Fremdschluessel aus #3 hat den Entwurf zum Eigentuemer des Schnipsels
 * gemacht. Seit #11 sind Faktenschnipsel aber gemeinsamer Bestand des
 * Mandanten: sie haengen an `fact_key` + Region + Berichtszeitraum, derselbe
 * Foerdersatz belegt einen bundesweiten und einen bayerischen Ratgeber.
 * Sobald irgendetwas `article_draft_id` auf so einem Schnipsel setzte, nahm das
 * Loeschen des Entwurfs die Quelldaten anderer Artikel mit (beim Testen von #14
 * reproduzierbar: vier DE-BY-Schnipsel weg).
 *
 * Die Spalte ist damit eine reine Zuordnung ("bei der Generierung dieses
 * Entwurfs entstanden") und darf auch fuer Pool-Schnipsel gesetzt werden — ein
 * geloeschter Entwurf setzt sie nur zurueck.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'fact_snippets_article_draft_id_foreign';

    public function up(): void
    {
        $this->replaceForeignKey(fn (Blueprint $table) => $table
            ->foreign('article_draft_id', self::CONSTRAINT)
            ->references('id')
            ->on('article_drafts')
            ->nullOnDelete());
    }

    public function down(): void
    {
        $this->replaceForeignKey(fn (Blueprint $table) => $table
            ->foreign('article_draft_id', self::CONSTRAINT)
            ->references('id')
            ->on('article_drafts')
            ->cascadeOnDelete());
    }

    /**
     * Der Fremdschluessel wird nur dann geloescht, wenn es ihn gibt: bei einer
     * frischen Tenant-Datenbank hat die Basis-Migration ihn bereits mit dem
     * gewuenschten Verhalten angelegt.
     */
    private function replaceForeignKey(\Closure $define): void
    {
        if (! Schema::hasTable('fact_snippets')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            Schema::table('fact_snippets', function (Blueprint $table): void {
                $table->dropForeign(self::CONSTRAINT);
            });
        }

        Schema::table('fact_snippets', $define);
    }

    private function foreignKeyExists(): bool
    {
        $rows = Schema::getConnection()->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['fact_snippets', self::CONSTRAINT, 'FOREIGN KEY']
        );

        return $rows !== [];
    }
};
