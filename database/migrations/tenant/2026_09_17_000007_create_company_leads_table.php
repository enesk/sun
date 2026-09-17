<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exklusive Anfragen aus dem Profil-Dialog (#9). Sie gehen nicht an das
 * Leadsystem, sondern nur an den Betrieb. Kontaktdaten und Freitexte loescht
 * leads:purge-contacts nach 12 Monaten (contact_purged_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 16)->default('new')->comment('App\Constants\CompanyLeadStatus');
            $table->json('answers')->nullable()->comment('Liste aus key, label, type, value, value_label; ohne Kontaktfelder');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->unsignedInteger('funnel_version')->nullable();
            $table->string('quota_period', 7)->nullable()->comment('Kontingent-Monat YYYY-MM, auf den die Anfrage zaehlt');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('contact_purged_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at'], 'cl_company_status_created_index');
            $table->index(['contact_purged_at', 'created_at'], 'cl_purge_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_leads');
    }
};
