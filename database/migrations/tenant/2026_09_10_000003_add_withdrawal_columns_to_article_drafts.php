<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zurueckziehen (Depublizieren/Verwerfen) von Entwuerfen und Artikeln.
 *
 * Bewusst additive Spalten statt eines weiteren DraftStatus-Falls: Der
 * Pipeline-Status bleibt erhalten, der Grund steht im Klartext im Dashboard.
 *
 * Der veroeffentlichte Artikel selbst liegt in `posts` und kennt dort bereits
 * den Status `archived`; der Entwurf bleibt die fuehrende Quelle fuer Grund,
 * Zeitpunkt und Person. Existiert `article_drafts` noch nicht, ueberspringt
 * die Migration sie (siehe docs/content-pipeline.md, Abschnitt 2).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = ['article_drafts'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'withdrawn_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('withdrawn_at')->nullable()->index();
                $blueprint->text('withdrawn_reason')->nullable()
                    ->comment('Pflichtgrund im Klartext, wird im Dashboard angezeigt');
                $blueprint->unsignedBigInteger('withdrawn_by')->nullable()
                    ->comment('content_users.id in der Central-DB, daher kein Fremdschluessel');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'withdrawn_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['withdrawn_at', 'withdrawn_reason', 'withdrawn_by']);
            });
        }
    }
};
