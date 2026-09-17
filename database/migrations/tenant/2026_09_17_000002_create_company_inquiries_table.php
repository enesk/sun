<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kopien der Anfragen aus dem Leadsystem je Betrieb (#32, Epic #29).
 * Aufbewahrung unbegrenzt, deshalb kein Prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('lead_uuid')->unique()->comment('Lead-UUID im Leadsystem, Schluessel fuer doppelte Zustellungen');
            $table->string('status', 16)->default('new')->comment('App\Constants\CompanyInquiryStatus');
            $table->json('answers')->comment('Liste aus key, label, value, value_label; ohne firmenprofil');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->boolean('contact_visible')->default(true)->comment('Vorbereitet fuer ein spaeteres Premium-Gate');
            $table->integer('score')->nullable();
            $table->string('result_key')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'received_at'], 'ci_company_status_received_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_inquiries');
    }
};
