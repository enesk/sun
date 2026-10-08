<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herkunft einer Firmeneintragung mitschreiben (#8).
 *
 * Gegenstueck zu database/migrations/2026_10_09_000003_add_origin_columns_to_users_table.php:
 * dort die zentrale `users`, hier die `companies` aus der Tenant-DB. Zwei
 * Migrationen, weil die beiden Tabellen auf verschiedenen Verbindungen liegen.
 *
 * Gefuellt beim Anlegen durch App\Livewire\Portal\CompanySignup; die Werte
 * dienen in #10 der Erkennung von Dubletten und Serien aus derselben Herkunft
 * (R2, R9 in docs/turnstile.md §7).
 *
 * Datenschutz: nur HMAC-SHA256 der IP (64 Hexzeichen), nie die Adresse selbst.
 * Additiv und nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->char('created_ip_hash', 64)->nullable();
            $table->string('created_user_agent', 255)->nullable();

            // Indexname explizit: `companies` ist kurz, aber die Konvention im
            // Projekt ist, ihn nicht Laravel zu ueberlassen (lange Tenant-
            // Tabellennamen reissen sonst die 64-Zeichen-Grenze von MySQL).
            $table->index('created_ip_hash', 'companies_created_ip_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_created_ip_hash_idx');
            $table->dropColumn(['created_ip_hash', 'created_user_agent']);
        });
    }
};
