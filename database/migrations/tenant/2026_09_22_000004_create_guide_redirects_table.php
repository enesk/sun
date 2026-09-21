<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 301-Weiterleitungen des Ratgebers (#18): geaenderte Kategorie-Slugs und
 * zusammengelegte Altartikel. Pfade ohne Host und ohne Query, mit fuehrendem
 * Schraegstrich (/ratgeber/kategorie/alt → /ratgeber/kategorie/neu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 255)->unique();
            $table->string('target_path', 500);
            $table->string('reason', 30)->default('manual');
            $table->foreignId('guide_category_id')
                ->nullable()
                ->constrained('guide_categories')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_redirects');
    }
};
