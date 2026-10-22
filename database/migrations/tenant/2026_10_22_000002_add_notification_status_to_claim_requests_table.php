<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versandstatus der Claim-Benachrichtigung (Ticket #25).
 *
 * Bis hierher stand am Antrag nur `status = approved`. Ob der Antragsteller
 * die Mail bekommen hat, war nirgends festgehalten: der Versand laeuft
 * ausserhalb der Transaktion, ein Fehlschlag landete nur im Log.
 *
 * Zwei Spalten, beide nullable — die Freigabe darf daran nie haengen:
 *
 *   notified_at        Zeitpunkt, zu dem der Mailer die Nachricht wirklich
 *                      abgegeben hat (Ereignis MessageSent), nicht der
 *                      Zeitpunkt des Einreihens in die Warteschlange.
 *   notification_error Grund, wenn nichts rausging: fehlende Adresse,
 *                      fehlender Portalkontext oder der Fehler des Jobs.
 *
 * Rueckwirkend bleibt beides leer. Fuer die bereits genehmigten Antraege
 * gibt es keine Quelle, aus der sich der Versand belegen liesse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_requests', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('reviewed_at');
            $table->string('notification_error', 255)->nullable()->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('claim_requests', function (Blueprint $table) {
            $table->dropColumn(['notified_at', 'notification_error']);
        });
    }
};
