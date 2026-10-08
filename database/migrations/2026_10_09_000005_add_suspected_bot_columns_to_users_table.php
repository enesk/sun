<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarantaene-Spalten fuer verdaechtige Konten (#10, docs/turnstile.md §7).
 *
 * Gegenstueck zu database/migrations/tenant/2026_10_09_000004_add_suspected_bot_columns_to_companies_table.php:
 * dort die `companies` je Portal, hier die zentrale `users`. Zwei Migrationen,
 * weil die Tabellen auf verschiedenen Verbindungen liegen.
 *
 * Alle Spalten additiv und nullable, keine Aenderung an Bestandsdaten:
 *
 *   suspected_bot_at           — Zeitpunkt der Markierung; ab hier laeuft die
 *                                14-Tage-Frist (antispam:expire-quarantine)
 *   suspected_bot_score        — 0..100 aus App\AntiSpam\BotScorer
 *   suspected_bot_reasons_json — {"reasons":[{code,label,weight}],"previous":{…}}
 *                                `previous` haelt den Zustand vor der
 *                                Quarantaene, damit "Freigeben" ihn genau
 *                                zuruecksetzt und nichts freischaltet, was
 *                                vorher nie offen war
 *   suspected_bot_cleared_at   — von Hand freigegeben. Ein freigegebener
 *                                Datensatz wird nie wieder markiert und nie
 *                                automatisch geloescht
 *   deleted_at                 — Soft-Delete; hart geloescht wird nichts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspected_bot_at')->nullable();
            $table->unsignedTinyInteger('suspected_bot_score')->nullable();
            $table->json('suspected_bot_reasons_json')->nullable();
            $table->timestamp('suspected_bot_cleared_at')->nullable();
            $table->softDeletes();

            $table->index('suspected_bot_at', 'users_suspected_bot_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_suspected_bot_at_idx');
            $table->dropColumn([
                'suspected_bot_at',
                'suspected_bot_score',
                'suspected_bot_reasons_json',
                'suspected_bot_cleared_at',
                'deleted_at',
            ]);
        });
    }
};
