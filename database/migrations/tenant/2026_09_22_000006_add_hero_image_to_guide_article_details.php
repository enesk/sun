<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titelbild je Thema (#20, App\Guide\Jobs\GenerateTopicImageJob).
 *
 * hero_image_path zeigt relativ zur Platte 'public' auf die groesste
 * WebP-Variante; die kleineren liegen daneben (HeroImageGenerator::variantPath()).
 * Breite und Hoehe stehen im img-Tag, damit das Bild keinen Layout-Shift
 * ausloest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->string('hero_image_path')->nullable();
            $table->string('hero_image_alt', 255)->nullable();
            $table->unsignedSmallInteger('hero_image_width')->nullable();
            $table->unsignedSmallInteger('hero_image_height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guide_article_details', function (Blueprint $table) {
            $table->dropColumn(['hero_image_path', 'hero_image_alt', 'hero_image_width', 'hero_image_height']);
        });
    }
};
