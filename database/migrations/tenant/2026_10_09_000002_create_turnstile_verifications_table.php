<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log jedes Pruefversuchs (#3, geschrieben von #4, ausgewertet in #9 und #12).
 *
 * Liegt in der Tenant-DB, weil die geschuetzten Formulare (Registrierung ueber
 * ein Portal, Firmeneintragung, Anfrage, Kontakt) immer im Tenant-Kontext
 * laufen und die Daten damit auch bei einer Portalabgabe mitwandern. Das
 * Kennzahlen-Widget in #9 summiert je Portal und legt die Reihen zusammen.
 *
 * Datenschutz: kein Token, keine Klartext-IP, keine Klartext-E-Mail. `ip_hash`
 * und `email_hash` sind HMAC-SHA256 mit dem App-Key als Schluessel — das reicht,
 * um Versuche derselben Herkunft zu korrelieren, ohne die Person zu speichern.
 *
 * Aufbewahrung: 90 Tage, geraeumt von turnstile:prune (#12),
 * config('turnstile.log.retention_days').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnstile_verifications', function (Blueprint $table) {
            $table->id();

            // App\Turnstile\Enums\TurnstileAction
            $table->string('action', 32);
            // App\Turnstile\Enums\VerificationOutcome
            $table->string('outcome', 16);

            // Fehlercodes aus der Siteverify-Antwort, z.B. ["invalid-input-response"].
            $table->json('error_codes_json')->nullable();

            // Hostname laut Cloudflare gegen den erwarteten Hostname der Anfrage.
            $table->string('hostname_reported')->nullable();
            $table->string('hostname_expected')->nullable();

            // HMAC-SHA256, 64 Hexzeichen; nie die IP selbst.
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('email_hash', 64)->nullable();

            // Dauer des Siteverify-Aufrufs; null, wenn gar nicht geprueft wurde.
            $table->unsignedInteger('duration_ms')->nullable();

            // Nur created_at: eine Logzeile wird nie geaendert.
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'outcome', 'created_at'], 'turnstile_verif_action_outcome_created_idx');
            $table->index('created_at', 'turnstile_verif_created_idx');
            $table->index('ip_hash', 'turnstile_verif_ip_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnstile_verifications');
    }
};
