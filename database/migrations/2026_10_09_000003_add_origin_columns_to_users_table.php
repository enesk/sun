<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herkunft einer Registrierung mitschreiben (#8).
 *
 * docs/turnstile.md §1.4: `users` hat bisher keine IP, keinen User-Agent,
 * keinen Referrer und keine Ausfuellzeit — deshalb liess sich die Bot-Analyse
 * in #2 nur auf Name, Mail-Domain und Zeitstempel stuetzen, und die
 * Erkennungsregeln R9/R11 aus §7 waren nicht messbar. Diese beiden Spalten
 * schliessen die Luecke; sie wirken nach vorn, nicht auf den Bestand.
 *
 * `users` liegt ZENTRAL (database/migrations/, Connection `central`) — anders
 * als `companies`, das in der Tenant-DB liegt und seine eigene Migration
 * bekommt (database/migrations/tenant/2026_10_09_000003_...). Das Ticket nennt
 * nur eine Tenant-Migration fuer beide Tabellen; das kann nicht gehen, weil die
 * Registrierung portaluebergreifend in der zentralen Datenbank steht.
 *
 * Datenschutz: NIE die Adresse selbst, nur HMAC-SHA256 mit dem App-Key
 * (App\Turnstile\Models\TurnstileVerification::hashIp(), 64 Hexzeichen) —
 * derselbe Hash wie in turnstile_verifications, damit sich eine Registrierung
 * mit ihren Pruefversuchen verbinden laesst, ohne eine IP zu speichern.
 * Additiv und nullable: der Bestand bleibt unberuehrt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('registration_ip_hash', 64)->nullable();
            $table->string('registration_user_agent', 255)->nullable();

            // Fuer R9 (mehrere Konten aus derselben Herkunft) in #10.
            $table->index('registration_ip_hash', 'users_registration_ip_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_registration_ip_hash_idx');
            $table->dropColumn(['registration_ip_hash', 'registration_user_agent']);
        });
    }
};
