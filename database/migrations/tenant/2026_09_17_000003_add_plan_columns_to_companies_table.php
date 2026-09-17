<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-Felder des Premium-Moduls (#3, Epic #1).
 * plan_tier bleibt String (Cast App\Enums\PlanTier), damit neue Stufen ohne Migration moeglich sind.
 * subscription_ref zeigt auf die Subscription in der Central-DB, deshalb kein FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('plan_tier', 32)->default('free')->comment('App\Enums\PlanTier');
            $table->timestamp('plan_started_at')->nullable();
            $table->timestamp('plan_ends_at')->nullable();
            $table->timestamp('plan_grace_until')->nullable();
            $table->string('subscription_ref')->nullable()->comment('Subscription-ID in der Central-DB');
            $table->timestamp('verified_at')->nullable();

            $table->index('plan_tier', 'companies_plan_tier_index');
            $table->index('subscription_ref', 'companies_subscription_ref_index');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_plan_tier_index');
            $table->dropIndex('companies_subscription_ref_index');
            $table->dropColumn([
                'plan_tier',
                'plan_started_at',
                'plan_ends_at',
                'plan_grace_until',
                'subscription_ref',
                'verified_at',
            ]);
        });
    }
};
