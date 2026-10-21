<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zentrale Anfragen-Inbox (SUN-RQ, Ticket #3; docs/profile-requests.md 9).
 *
 * Eine Zeile je Vorgang — Aenderung, Loeschung, Uebernahme oder Sonstiges —
 * aus allen Portalen. Die Tabelle liegt in der Central-DB, damit die Inbox
 * (#7) eine Abfrage ueber alle Portale ist und nicht 23 Kontextwechsel.
 *
 * `company_id` traegt bewusst **keinen** Fremdschluessel: der Betrieb liegt in
 * der Tenant-DB des Portals. Ein geloeschter Betrieb laesst seine Anfragen
 * also stehen, denn das Protokoll der Loeschung muss die Loeschung
 * ueberleben. Aufgeloest wird die Referenz ueber
 * App\ProfileRequests\Support\TenantCompanyResolver.
 *
 * `tenant_id` ist nullable: bei einer Mail ohne erkennbares Zielportal
 * (Kopfzeilen greifen nicht, KI unsicher) landet die Anfrage trotzdem in der
 * Inbox und wird dort von Hand zugeordnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_requests', function (Blueprint $table) {
            $table->id();

            // tenants.id ist in diesem Projekt ein bigint, siehe
            // 2024_06_16_091235_create_tenants_table.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // Referenz nach companies in der Tenant-DB, deshalb ohne FK.
            $table->unsignedBigInteger('company_id')->nullable();

            // App\ProfileRequests\Enums\RequestType: change|delete|claim|other
            $table->string('type', 16);

            // App\ProfileRequests\Enums\RequestSource: form|email|claim_flow
            $table->string('source', 16);

            // App\ProfileRequests\Enums\RequestStatus
            $table->string('status', 32)->default('new');

            $table->string('requester_name')->nullable();
            $table->string('requester_email');
            $table->string('requester_phone')->nullable();

            // Freitext des Anfragenden (Formularnachricht bzw. Mailtext).
            $table->text('message')->nullable();

            // .eml unter storage/app/private/requests/{id}/, Loeschung nach
            // config('profile-requests.retention_days') per Scheduler (#5).
            $table->string('raw_email_path')->nullable();

            // Herkunftsbezug, z. B. claim_requests.id des Tenants (#6).
            $table->string('external_ref')->nullable();

            // App\ProfileRequests\Enums\VerificationMethod
            $table->string('verification_method', 32)->nullable();

            // Nur der SHA-256 des Magic-Link-Tokens; der Klartext existiert
            // ausschliesslich in der versendeten E-Mail.
            $table->string('verification_token_hash', 64)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_email')->nullable();

            // KI-Vorschlag (#6) — immer nur ein Vorschlag.
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('ai_reply_draft')->nullable();

            // Setzen die Auto-Regeln (#9): darf diese Anfrage ohne Enes laufen?
            $table->boolean('auto_applicable')->default(false);

            // Ende der Karenzfrist bei verifizierten Loeschungen (#8/#9).
            $table->timestamp('grace_until')->nullable();

            $table->timestamp('applied_at')->nullable();

            // null = automatisch durch eine Auto-Regel erledigt.
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('rejected_reason')->nullable();

            $table->timestamps();

            // Inbox: offene Anfragen, aelteste zuerst.
            $table->index(['status', 'created_at']);
            // Alle Anfragen zu einem Profil bzw. einem Portal.
            $table->index(['tenant_id', 'company_id']);
            // Dedupe und Verlauf eines Absenders (#5).
            $table->index('requester_email');
            // Filter der Inbox nach Typ (#7).
            $table->index(['type', 'status']);
            // Einloesen des Magic-Links (#6).
            $table->index('verification_token_hash');
            // Faellige Karenzfristen (#9).
            $table->index('grace_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_requests');
    }
};
