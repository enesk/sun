<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll einer Anfrage (SUN-RQ, Ticket #3; docs/profile-requests.md 8).
 *
 * Append-only: jeder Statuswechsel schreibt genau eine Zeile, erzwungen von
 * App\ProfileRequests\Observers\ProfileRequestObserver. Zeilen werden nie
 * geaendert und nie geloescht — nur mit der Anfrage selbst, deshalb kein
 * `updated_at`.
 *
 * `actor_type`/`actor_id` statt einer gemischten Spalte: `system` (Scheduler,
 * Auto-Regel), `requester` (Magic-Link, Stornolink) und `user` (Enes im
 * Panel, dann mit users.id in `actor_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')
                ->constrained('profile_requests')
                ->cascadeOnDelete();

            // App\ProfileRequests\Enums\RequestEventType
            $table->string('event', 64);

            // system | requester | user
            $table->string('actor_type', 16)->default('system');

            // users.id, nur bei actor_type = user. Ohne FK, damit das
            // Protokoll ein geloeschtes Konto ueberlebt.
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();

            $table->json('payload')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'id']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
    }
};
