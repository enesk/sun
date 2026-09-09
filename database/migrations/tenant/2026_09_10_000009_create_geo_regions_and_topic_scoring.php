<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo-Liste und Scoring-Felder der Themenfindung (#12).
 *
 * `geo_regions` ist die einzige gepflegte Ortsliste der Content-Pipeline: 16
 * Bundeslaender und die Staedte ab 30.000 Einwohnern mit Bundesland-Zuordnung.
 * Sie steht bewusst in der Tenant-DB und nicht zentral, weil der Seeder sie um
 * die Orte des jeweiligen Portals (`cities`) ergaenzt — ein Sanitaerportal in
 * Bayern braucht andere Orte als ein Portal im Ruhrgebiet.
 *
 * Schluessel ist ueberall der ISO-3166-2-Code ('DE-BY') fuer Bundeslaender und
 * der Slug ('koeln') fuer Staedte; genau diese Werte landen in
 * topic_candidates.region_code (siehe #33).
 *
 * Die Gewichte des Gesamtscores stehen in
 * tenant_content_settings.scoring_weights_json und kommen aus der Migration
 * 2026_09_10_000008_add_scoring_weights_to_tenant_content_settings (#20).
 *
 * `topic_candidates` bekommt die Nachvollziehbarkeit dazu: warum ein
 * Regionszuschnitt gewaehlt wurde (region_reason/region_evidence_json), wie
 * der Gesamtscore zustande kam (score_breakdown_json) und die
 * Aehnlichkeitsmerkmale fuer Duplikats- und Kannibalisierungspruefung
 * (simhash/embedding_json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_regions', function (Blueprint $table) {
            $table->id();

            // 'state' | 'city' — dieselben Werte wie region_scope.
            $table->string('scope', 16);

            // ISO-3166-2 bei Laendern, Slug bei Staedten.
            $table->string('code', 64);
            $table->string('name');
            $table->string('slug', 64);

            // Bei Staedten das Bundesland (ISO), bei Laendern null.
            $table->string('state_code', 8)->nullable();

            $table->unsignedInteger('population')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Orte, die nur aus der Portal-Tabelle `cities` stammen und nicht
            // aus der amtlichen Liste, sind als Ergaenzung erkennbar.
            $table->string('source', 16)->default('seed');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope', 'code']);
            $table->index(['scope', 'state_code']);
            $table->index('slug');
            $table->index('population');
        });

        Schema::table('topic_candidates', function (Blueprint $table) {
            // Klartextbegruendung des Regionszuschnitts (Pflicht laut #12).
            $table->string('region_reason', 500)->nullable()->after('region_code');

            // Die Belege dahinter: [{factor, source_item_id, detail}].
            $table->json('region_evidence_json')->nullable()->after('region_reason');

            // Teilscores mit Gewichten, wie sie zum total_score gefuehrt haben.
            $table->json('score_breakdown_json')->nullable()->after('total_score');

            // YMYL: Thema darf nur informierend behandelt werden (#12).
            $table->boolean('informational_only')->default(false)->after('intent');

            $table->text('rationale')->nullable()->after('score_breakdown_json');

            // Aehnlichkeitsmerkmale: SimHash als Vorfilter, Embedding fuer die
            // Cosine-Rechnung in PHP.
            $table->unsignedBigInteger('simhash')->nullable()->after('rationale');
            $table->json('embedding_json')->nullable()->after('simhash');

            $table->index('simhash');
            $table->index(['status', 'selected_for_date'], 'topic_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('topic_candidates', function (Blueprint $table) {
            $table->dropIndex('topic_status_date_index');
            $table->dropIndex(['simhash']);
            $table->dropColumn([
                'region_reason',
                'region_evidence_json',
                'score_breakdown_json',
                'informational_only',
                'rationale',
                'simhash',
                'embedding_json',
            ]);
        });

        Schema::dropIfExists('geo_regions');
    }
};
