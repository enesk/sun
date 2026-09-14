<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local Hubs (#11): city_contents gibt es seit 2026_03_04 (Introtext und
 * Meta-Overrides). Ergaenzt werden Stadtteile, FAQ und ein Freigabeschalter;
 * intro_text wird nullable, damit eine Stadt nur Stadtteile oder nur FAQ
 * ueberschreiben kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('city_contents', function (Blueprint $table) {
            $table->longText('intro_text')->nullable()->change();
            $table->json('districts')->nullable()->after('intro_text');
            $table->json('faqs')->nullable()->after('districts');
            $table->boolean('is_published')->default(true)->after('faqs');
        });
    }

    public function down(): void
    {
        Schema::table('city_contents', function (Blueprint $table) {
            $table->dropColumn(['districts', 'faqs', 'is_published']);
        });
    }
};
