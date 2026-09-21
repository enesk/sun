<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alarme der Content-Pipeline (#22).
 *
 * Central und nicht je Mandant: die Uebersicht des Content-Panels liest die
 * Alarme aller Portale in einer Abfrage, und der Tagesbericht um 20:00 fasst
 * sie netzwerkweit zusammen. Ein tenantgetrennter Ablageort waere pro Aufruf
 * ein weiterer Durchgang durch 20 Datenbanken.
 *
 * `dedupe_key` traegt Mandant, Ursache, Tag und Slot in einem Wert. Er ist
 * eindeutig, damit der halbstuendliche Watchdog denselben Alarm hochzaehlt,
 * statt die Uebersicht mit Wiederholungen zu fluten (NULL-Werte in einem
 * mehrspaltigen Unique-Index wuerden bei netzwerkweiten Alarmen ohne
 * tenant_id nicht greifen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->unsignedBigInteger('tenant_id')->nullable(); // null = netzwerkweit
            $table->string('key', 64);                           // App\Guide\Models\Central\ContentAlert::KEY_*
            $table->string('level', 16)->default('warning');     // critical|warning|info
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->date('for_date')->nullable();
            $table->unsignedSmallInteger('slot')->nullable();
            $table->string('status', 16)->default('open');       // open|resolved
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['status', 'level']);
            $table->index(['tenant_id', 'for_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_alerts');
    }
};
