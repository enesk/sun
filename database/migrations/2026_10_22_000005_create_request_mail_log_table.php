<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eingangsprotokoll des IMAP-Pollers (SUN-RQ, Ticket #5).
 *
 * Eine Zeile je gelesener Mail des Sammelpostfachs — auch fuer die, aus der
 * keine Anfrage wird. Zwei Aufgaben:
 *
 * 1. Dedupe. `message_id_hash` ist eindeutig; eine zweite Zustellung
 *    derselben Mail (Wiederholung des Postfachs, abgebrochener Lauf vor dem
 *    Verschieben) prallt am Index ab, statt eine zweite Anfrage anzulegen.
 *    Gehasht, weil eine Message-ID nach RFC 5322 laenger werden darf als ein
 *    MySQL-Index traegt; der Klartext steht daneben, damit man sie lesen kann.
 *
 * 2. Protokoll fuer Verworfenes. Eine Auto-Antwort oder ein Newsletter
 *    bekommt keine Anfrage und koennte deshalb auch keine Zeile in
 *    `request_events` haben — hier steht trotzdem, dass sie da war und
 *    warum sie wegfiel (`outcome` = discarded, `reason`).
 *
 * Gehoert in die Central-DB, wie die ganze Inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_mail_log', function (Blueprint $table) {
            $table->id();

            // SHA-256 der Message-ID — der Dedupe-Schluessel.
            $table->char('message_id_hash', 64)->unique();

            // Klartext, gekuerzt auf das, was eine Spalte traegt.
            $table->string('message_id', 512)->nullable();

            // created | replied | discarded (App\ProfileRequests\Ingestion\MailOutcome)
            $table->string('outcome', 16);

            // Grund bei discarded: auto_reply, newsletter, blocked_sender, ...
            $table->string('reason', 64)->nullable();

            // Die Anfrage, zu der die Mail wurde bzw. gehoert. Bleibt bei
            // verworfenen Mails leer.
            $table->foreignId('request_id')->nullable()
                ->constrained('profile_requests')->nullOnDelete();

            $table->string('from_email')->nullable();
            // Erkannte Portal-Adresse (Delivered-To o. ae.).
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();

            // Datum der Mail selbst, nicht des Laufs.
            $table->timestamp('mail_date')->nullable();

            $table->timestamps();

            // Verlauf eines Vorgangs und Auswertung der verworfenen Mails.
            $table->index('request_id');
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_mail_log');
    }
};
