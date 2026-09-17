<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Top-Platzierung als Add-on (#6, Epic #1): Stadt und Branche der Buchung.
 *
 * Nur bei Subscriptions des Add-ons featured-monthly gefuellt; der Webhook
 * vergibt damit den Slot im Tenant. Beide IDs zeigen in die Tenant-DB,
 * deshalb kein FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('featured_city_id')->nullable()->after('subscription_id')->comment('cities.id in der Tenant-DB');
            $table->unsignedBigInteger('featured_category_id')->nullable()->after('featured_city_id')->comment('categories.id in der Tenant-DB');
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['featured_city_id', 'featured_category_id']);
        });
    }
};
