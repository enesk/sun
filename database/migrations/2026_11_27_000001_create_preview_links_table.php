<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorschau-Links (SUN-PV-001, Ticket #2).
 *
 * Ein Link zeigt eingeladenen Besuchern auf der echten Portal-Domain das
 * Theme sun-v2. Gespeichert wird nur der sha256-Hash des Tokens; der
 * Klartext existiert ausschliesslich bei der Erzeugung
 * (App\Preview\PreviewTokenService::issue()).
 *
 * Geltung: `applies_to_all_tenants` oder die Auswahl in preview_link_tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->boolean('applies_to_all_tenants')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_links');
    }
};
