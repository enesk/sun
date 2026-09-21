<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualitaetsgate (#11): Ergebnis des Link-Checks je Quelle. broken_at ist
 * gesetzt, solange die URL mit 4xx/5xx antwortet; ein spaeterer erfolgreicher
 * Check leert es wieder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_sources', function (Blueprint $table) {
            $table->timestamp('link_checked_at')->nullable()->after('retrieved_at');
            $table->unsignedSmallInteger('link_status_code')->nullable()->after('link_checked_at');
            $table->timestamp('broken_at')->nullable()->after('link_status_code');
        });
    }

    public function down(): void
    {
        Schema::table('guide_sources', function (Blueprint $table) {
            $table->dropColumn(['link_checked_at', 'link_status_code', 'broken_at']);
        });
    }
};
