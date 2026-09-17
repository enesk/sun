<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loeschvermerk fuer Verifizierungsnachweise (#11): die Datei wird 90 Tage
 * nach der Entscheidung entfernt, der Datensatz bleibt als Protokoll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_verifications', function (Blueprint $table) {
            $table->timestamp('document_purged_at')->nullable()->after('rejection_reason');
            $table->index(['document_purged_at', 'reviewed_at'], 'cv_purged_reviewed_index');
        });
    }

    public function down(): void
    {
        Schema::table('company_verifications', function (Blueprint $table) {
            $table->dropIndex('cv_purged_reviewed_index');
            $table->dropColumn('document_purged_at');
        });
    }
};
