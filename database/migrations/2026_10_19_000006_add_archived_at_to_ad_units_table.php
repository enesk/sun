<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiv der Werbemittel (SUN-AD-010, Ticket #10).
 *
 * `ad_units` haengt per ON DELETE CASCADE an `ad_placements`: ein geloeschtes
 * Werbemittel nimmt jede Belegung mit, auch die laufende. Darum darf ein
 * Werbemittel in Verwendung nicht geloescht, sondern nur archiviert werden —
 * die Belegungen bleiben nachvollziehbar, in der Auswahl eines neuen
 * Placements (#8) taucht das Werbemittel aber nicht mehr auf.
 *
 * Eigene Spalte und kein `is_active`: `is_active` ist der Not-Aus, mit dem ein
 * laufendes Werbemittel kurz still gestellt und wieder angeschaltet wird.
 * Archivieren ist das Ende seines Lebens. Beides zu vermischen hiesse, dass
 * ein Not-Aus das Werbemittel aus allen Auswahllisten nimmt.
 *
 * Kein SoftDeletes: `deleted_at` wuerde die Zeilen aus jeder Standardabfrage
 * nehmen — auch aus der, mit der der Resolver das Werbemittel einer noch
 * bestehenden Belegung laedt. Archiviert heisst sichtbar, aber nicht waehlbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_units', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_active');

            // Zugriffspfad der Auswahllisten (#8/#10): nur nicht archivierte.
            $table->index(['archived_at', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('ad_units', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'name']);
            $table->dropColumn('archived_at');
        });
    }
};
