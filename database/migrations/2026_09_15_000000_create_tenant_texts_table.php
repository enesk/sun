<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-spezifische Ueberschreibungen einzelner Theme-Texte (#3).
 *
 * Bewusst in der Central-DB: die Pflege laeuft ueber das Admin-Panel quer
 * ueber alle Portale (#13). key steht ohne Gruppenpraefix, z. B.
 * group = portal, key = profile.request_cta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('group', 64);
            $table->string('key', 191);
            $table->text('value');
            $table->timestamps();

            // Deckt auch die Ladeabfrage des Loaders (tenant_id, locale, group) ab.
            $table->unique(['tenant_id', 'locale', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_texts');
    }
};
