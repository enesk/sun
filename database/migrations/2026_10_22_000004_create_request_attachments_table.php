<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anhaenge einer Anfrage und die Rohmail (SUN-RQ, Ticket #3).
 *
 * Ablage auf der Platte `local` unter requests/{request_id}/, also in
 * storage/app/private/requests/{id}/ — nie oeffentlich. `purge_after` ist
 * created_at + config('profile-requests.retention_days'); der Scheduler (#5)
 * loescht danach nur die Datei und diese Zeile, Anfrage, Diff und Protokoll
 * bleiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')
                ->constrained('profile_requests')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // true = die .eml selbst, nicht ein Anhang daraus.
            $table->boolean('is_raw_mail')->default(false);

            $table->timestamp('purge_after')->nullable();

            $table->timestamps();

            $table->index('request_id');
            // Faellige Aufbewahrungsfristen.
            $table->index('purge_after');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_attachments');
    }
};
