<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarantaene-Spalten fuer verdaechtige Firmeneintraege (#10, docs/turnstile.md §7).
 *
 * Gegenstueck zu database/migrations/2026_10_09_000005_add_suspected_bot_columns_to_users_table.php;
 * Bedeutung der Spalten steht dort. `companies` liegt je Portal in der
 * Tenant-DB, deshalb eine eigene Migration auf der Verbindung `tenant`.
 *
 * Quarantaene heisst bei einem Eintrag `is_active = 0` — dieselbe Stellung wie
 * ein noch nicht freigegebener Eintrag ("pending"). Die Portalseiten fragen
 * ausschliesslich ueber Company::active() ab, ein markierter Eintrag ist damit
 * sofort aus Suche, Stadtseiten und Sitemap heraus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('suspected_bot_at')->nullable();
            $table->unsignedTinyInteger('suspected_bot_score')->nullable();
            $table->json('suspected_bot_reasons_json')->nullable();
            $table->timestamp('suspected_bot_cleared_at')->nullable();
            $table->softDeletes();

            $table->index('suspected_bot_at', 'companies_suspected_bot_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_suspected_bot_at_idx');
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
