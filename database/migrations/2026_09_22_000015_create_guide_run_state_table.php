<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Globaler Zustand des Tageslaufs (#33, design/guide-dashboard.md §9.1):
 * genau eine Zeile. paused_at gesetzt = Tageslauf global pausiert; kein
 * Portal startet dann einen neuen Lauf. Central statt im Cache, weil der
 * Schalter einen Neustart und ein cache:clear ueberleben muss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_run_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedBigInteger('paused_by')->nullable(); // users.id
            $table->string('paused_by_name')->nullable();
            $table->timestamps();

            $table->foreign('paused_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_run_state');
    }
};
