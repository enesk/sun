<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergaenzungen fuer das SourceConnector-Framework (#7).
 *
 * `source_items` bekommt die beiden Felder des SourceItemDto, die im
 * Datenmodell (#3) noch keine eigene Spalte hatten: die vom Connector
 * gelieferten Keywords und die Signalstaerke. Beides wird vom Scoring (#12)
 * gelesen und darf deshalb nicht im Rohpayload verschwinden.
 *
 * `tenant_content_settings` bekommt die Branchen-Keyword-Liste, die der Texter
 * (#13) pflegt und die den Connectoren ueber den TenantContext zur Verfuegung
 * steht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_items', function (Blueprint $table) {
            $table->json('keywords_json')->nullable()->after('region_code');
            $table->decimal('signal_strength', 4, 3)->default(0)->after('keywords_json'); // 0.000 - 1.000
        });

        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->json('branch_keywords_json')->nullable()->after('preferred_states_json');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn('branch_keywords_json');
        });

        Schema::table('source_items', function (Blueprint $table) {
            $table->dropColumn(['keywords_json', 'signal_strength']);
        });
    }
};
