<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Raeumt die Reste des alten Content-Guards weg (#139/#140).
 *
 * Seit der Umstellung auf den SaaSykit-Login liest niemand mehr
 * `content_users` oder `content_password_reset_tokens`. Vor dem Loeschen
 * werden die Konten ohne Passwort-Hash als JSON nach
 * `storage/app/backups/content-users-<zeitstempel>.json` geschrieben —
 * auf Produktion gibt es keine brauchbare Datenbanksicherung, und die
 * E-Mail-Adressen sagen hinterher, wer ein Administratorkonto braucht.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_users')) {
            $this->archiveAccounts();
        }

        Schema::dropIfExists('content_password_reset_tokens');
        Schema::dropIfExists('content_users');
    }

    /**
     * Die Tabellen kommen leer zurueck: die Konten sind bewusst nicht
     * wiederherstellbar, das Archiv nennt nur, wer eines hatte.
     */
    public function down(): void
    {
        Schema::create('content_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 32)->default('editor');
            $table->boolean('is_active')->default(true);
            $table->json('tenant_ids_json')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('content_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function archiveAccounts(): void
    {
        $accounts = DB::table('content_users')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'tenant_ids_json', 'last_login_at', 'created_at'])
            ->all();

        if ($accounts === []) {
            return;
        }

        Storage::disk('local')->put(
            'backups/content-users-'.now()->format('Y-m-d-His').'.json',
            (string) json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }
};
