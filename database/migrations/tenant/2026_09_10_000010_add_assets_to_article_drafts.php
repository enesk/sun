<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bild- und Infografik-Spalten des Entwurfs (#16).
 *
 * Die Pfade sind relativ zur Platte `public` des Mandanten (der
 * FilesystemTenancyBootstrapper haengt sie an storage/tenant<uuid>/app/public),
 * also z. B. `ratgeber/<tenant>/<slug>/hero-1200.webp`. Die Groessenvarianten
 * und ihre Dateigroessen stehen daneben in `assets_json`; die Spalte traegt
 * ausserdem den Bild-Prompt und die Herkunft (fal|unsplash|fallback), damit im
 * Content-Panel nachvollziehbar bleibt, woher ein Titelbild stammt.
 *
 * `brand_colors_json` in den Mandanteneinstellungen faerbt die SVG-Infografik.
 * Ohne Pflege zieht der InfographicRenderer die Branding-Farben des Tenants
 * aus der Central-DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->string('hero_image_path', 512)->nullable()->after('key_facts_json');
            $table->string('hero_image_alt', 255)->nullable()->after('hero_image_path');
            $table->string('hero_image_credit', 255)->nullable()->after('hero_image_alt');
            $table->string('hero_image_source', 32)->nullable()->after('hero_image_credit');
            $table->string('infographic_svg_path', 512)->nullable()->after('hero_image_source');
            $table->json('assets_json')->nullable()->after('infographic_svg_path');
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->json('brand_colors_json')->nullable()->after('category_mapping_json');
        });
    }

    public function down(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'hero_image_path',
                'hero_image_alt',
                'hero_image_credit',
                'hero_image_source',
                'infographic_svg_path',
                'assets_json',
            ]);
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('brand_colors_json');
        });
    }
};
