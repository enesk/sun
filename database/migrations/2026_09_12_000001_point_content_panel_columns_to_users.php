<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Das Content-Panel meldet sich seit dem Umbau ueber den SaaSykit-Login an
 * ('web'-Guard, App\Models\User). Zwei Spalten zeigten bisher auf
 * content_users und muessen mitziehen:
 *
 * - prompt_templates.created_by trug einen Fremdschluessel auf content_users;
 *   er zeigt jetzt auf users. Bestehende Werte stammen aus der alten Tabelle
 *   und sind in der neuen bedeutungslos, sie werden geleert.
 * - content_source_settings.updated_by_content_user_id heisst jetzt
 *   updated_by_user_id (nie ein Fremdschluessel gewesen) und wird ebenfalls
 *   geleert.
 *
 * content_users selbst bleibt stehen: die Tabelle wird nicht mehr gelesen,
 * das Loeschen von Konten gehoert aber nicht in eine Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        DB::table('prompt_templates')->whereNotNull('created_by')->update(['created_by' => null]);

        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::table('content_source_settings')
            ->whereNotNull('updated_by_content_user_id')
            ->update(['updated_by_content_user_id' => null]);

        Schema::table('content_source_settings', function (Blueprint $table) {
            $table->renameColumn('updated_by_content_user_id', 'updated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_source_settings', function (Blueprint $table) {
            $table->renameColumn('updated_by_user_id', 'updated_by_content_user_id');
        });

        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        DB::table('prompt_templates')->whereNotNull('created_by')->update(['created_by' => null]);

        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->foreign('created_by')
                ->references('id')
                ->on('content_users')
                ->nullOnDelete();
        });
    }
};
