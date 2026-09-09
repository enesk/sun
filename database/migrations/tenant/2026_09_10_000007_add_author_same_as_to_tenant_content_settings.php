<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil-Verweise der Redaktion (#18).
 *
 * `organization_same_as_json` beschreibt das Portal als Herausgeber, die
 * Autorenseite braucht daneben eigene Verweise (Fachprofile, Autorenkonten).
 * Bleibt die Spalte leer, faellt die Autorenseite auf die Organisationsprofile
 * zurueck — dann steht dort kein falscher, sondern gar kein sameAs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_content_settings') || Schema::hasColumn('tenant_content_settings', 'author_same_as_json')) {
            return;
        }

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->json('author_same_as_json')->nullable()->after('organization_same_as_json');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenant_content_settings', 'author_same_as_json')) {
            return;
        }

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('author_same_as_json');
        });
    }
};
