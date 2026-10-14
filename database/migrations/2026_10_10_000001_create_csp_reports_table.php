<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelstelle fuer CSP-Verstoesse (#51).
 *
 * Bis #51 setzte config/csp.php weder `report-uri` noch `report-to`: im Modus
 * `CSP_MODE=report` meldete der Browser nur in seine eigene Konsole, und die
 * 24-Stunden-Sichtung aus docs/turnstile-golive.md §1.5 konnte nichts
 * einsammeln. Jetzt nimmt POST /csp-bericht die Meldungen an
 * (App\Http\Controllers\Portal\CspReportController).
 *
 * Liegt ZENTRAL, nicht in der Tenant-DB — anders als turnstile_verifications:
 * die Auswertung ist "eine Liste je Portal" ueber alle 23 Portale, und die
 * Adresse ist eine unauthentifizierte POST-Route. Ein zentrales Ziel heisst
 * eine Tabelle, ein Pruning-Lauf und keine Tenant-Migration je Portal; der
 * Browser meldet ohnehin je Dokument-Host, nicht je Datenbank.
 *
 * Entdopplung: ein kaputtes Drittanbieter-Schnipsel erzeugt sonst Tausende
 * gleicher Meldungen. `fingerprint` ist SHA-256 aus Portal +
 * `violated_directive` + normalisierter `blocked_uri` und ist eindeutig; ein
 * weiterer Treffer erhoeht `hits` und `last_seen_at`
 * (App\Csp\Support\CspReportCollector).
 *
 * Datenschutz: keine IP. `user_agent` wird auf 255 Zeichen gekuerzt und nur
 * vom ersten Treffer behalten — er sagt, welcher Browser meldet, nicht wer.
 *
 * Aufbewahrung: config('csp.report.retention_days') (Vorgabe 30 Tage),
 * geraeumt von `csp:prune`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csp_reports', function (Blueprint $table) {
            $table->id();

            // Portal laut Dokument-Host. tenant_id ist null, wenn die Meldung
            // von einer zentralen Domain kommt (Admin-Panel, Checkout).
            $table->string('portal', 190);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Der Entdopplungsschluessel, SHA-256 als 64 Hexzeichen. Als
            // eigene Spalte, weil blocked_uri/document_uri zu lang fuer einen
            // MySQL-Index aus mehreren Textspalten sind.
            $table->char('fingerprint', 64)->unique('csp_reports_fingerprint_uq');

            // Aus dem Bericht. violated_directive ist die alte Schreibweise
            // (Safari/Firefox), effective_directive die neue (Chrome).
            $table->string('violated_directive', 64);
            $table->string('effective_directive', 64)->nullable();
            $table->string('blocked_uri', 500);
            $table->string('document_uri', 500);

            // "report" oder "enforce" — sagt, ob der Browser nur gemeldet oder
            // auch blockiert hat.
            $table->string('disposition', 16)->nullable();

            // Wo im Dokument; bei Inline-Verstoessen die Zeile im HTML.
            $table->string('source_file', 500)->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->unsignedInteger('column_number')->nullable();

            // Nur vom ersten Treffer; gekuerzt auf Spaltenbreite.
            $table->string('user_agent', 255)->nullable();

            // Zaehler statt Zeile.
            $table->unsignedBigInteger('hits')->default(1);

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Auswertung: je Portal, nach Haeufigkeit bzw. Zeitraum.
            $table->index(['portal', 'last_seen_at'], 'csp_reports_portal_last_seen_idx');
            $table->index('last_seen_at', 'csp_reports_last_seen_idx');
            $table->index('violated_directive', 'csp_reports_directive_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csp_reports');
    }
};
