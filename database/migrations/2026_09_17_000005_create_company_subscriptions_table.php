<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung SaasyKit-Subscription => Betrieb im Tenant (#5, Epic #1).
 *
 * Bewusst in der Central-DB: der Stripe-Webhook laeuft im Central-Kontext und
 * muss ohne Tenant-Wechsel wissen, in welchem Portal welcher Betrieb gemeint
 * ist. company_id zeigt in die Tenant-DB, deshalb kein FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->comment('companies.id in der Tenant-DB');
            $table->foreignId('subscription_id')->unique()->constrained('subscriptions')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_subscriptions');
    }
};
