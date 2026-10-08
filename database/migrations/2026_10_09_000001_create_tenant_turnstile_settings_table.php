<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnstile-Einstellungen je Portal (#3, docs/turnstile.md §4).
 *
 * Liegt central, genau eine Zeile je Tenant: so liest das Admin-Panel (#9) und
 * das Kennzahlen-Widget alle Portale in einer Abfrage, und der Rollout-Schalter
 * braucht keine Tenant-Migration. Fehlt die Zeile, gilt ausschliesslich
 * config/turnstile.php — App\Turnstile\Config\TurnstileConfigResolver legt
 * beides uebereinander.
 *
 * `secret_key` ist im Model `encrypted` gecastet, liegt also auch in der
 * Datenbanksicherung nicht im Klartext. `site_key` ist oeffentlich (er steht im
 * HTML) und bleibt deshalb lesbar. Leere Keys heissen "Vorgabe aus der .env".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_turnstile_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->unique()
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Rollout-Schalter je Portal (docs/turnstile.md §9). An heisst: die
            // Rule prueft, soweit die jeweilige Aktion ebenfalls an ist.
            $table->boolean('is_enabled')->default(true);

            // Eigenes Cloudflare-Widget dieses Portals; leer = Widget aus der .env.
            $table->string('site_key')->nullable();
            // Verschluesselt (encrypted cast), daher text statt string.
            $table->text('secret_key')->nullable();

            // open|closed; null = config('turnstile.fail_mode').
            $table->string('fail_mode', 16)->nullable();

            // {"registration":{"enabled":true,"mode":"managed"}, ...}
            $table->json('actions_json')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable(); // users.id
            $table->timestamps();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_turnstile_settings');
    }
};
