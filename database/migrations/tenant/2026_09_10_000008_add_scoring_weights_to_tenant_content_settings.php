<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gewichte des Themen-Scorings je Mandant (#20).
 *
 * Das Content-Dashboard stellt sie als Schieberegler ein; verrechnet werden
 * sie vom Themen-Scoring (#12). Die fuenf Dimensionen sind die bereits in
 * `topic_candidates` gefuehrten Teilscores — Trend, Nachfrage, Luecke,
 * Saison und Eigenstaendigkeit. Fehlt der Wert, gilt die Gleichverteilung
 * aus TenantContentSetting::scoringWeights().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->json('scoring_weights_json')->nullable()->after('branch_keywords_json');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('scoring_weights_json');
        });
    }
};
