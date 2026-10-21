<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loeschpipeline (SUN-RQ #8): die Spalten, die eine Loeschung am Profil
 * selbst festhalten.
 *
 *  * `deleted_reason` — warum das Profil weg ist (Anfrage-Nummer, Panel,
 *    Artisan). Ohne Grund ist ein Soft Delete im Bestand nicht von einer
 *    Bestandsbereinigung zu unterscheiden.
 *  * `hard_delete_after` — ab wann der Scheduler hart loeschen darf. Der
 *    Soft Delete haelt eine Fehlbedienung 30 Tage rueckholbar; danach ist
 *    `is_active=false` fuer Art. 17 nicht genug.
 *  * `pre_deletion_json` — `slug` und `google_places_id` im Original. Beide
 *    Spalten tragen einen Unique-Index, den ein soft-geloeschter Datensatz
 *    weiter belegt. Die Pipeline haengt deshalb beim Loeschen ein Suffix an
 *    den Slug und leert die Places-ID, damit derselbe Betrieb sofort neu
 *    eingetragen werden kann; zum Zurueckholen braucht es die alten Werte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'deleted_reason')) {
                $table->string('deleted_reason', 191)->nullable()->after('deleted_at');
            }

            if (! Schema::hasColumn('companies', 'hard_delete_after')) {
                $table->timestamp('hard_delete_after')->nullable()->after('deleted_reason')->index();
            }

            if (! Schema::hasColumn('companies', 'pre_deletion_json')) {
                $table->json('pre_deletion_json')->nullable()->after('hard_delete_after');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'hard_delete_after')) {
                $table->dropIndex(['hard_delete_after']);
                $table->dropColumn('hard_delete_after');
            }

            foreach (['deleted_reason', 'pre_deletion_json'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
