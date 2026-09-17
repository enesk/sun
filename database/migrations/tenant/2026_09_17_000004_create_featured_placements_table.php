<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Top-Platzierungen je Stadt x Branche (#3, Epic #1), max. 3 aktive Slots.
 * MySQL kennt keine Partial-Indizes: active_slot ist nur bei status=active gefuellt,
 * der Unique-Index darauf sichert die Slot-Logik des Services zusaetzlich in der DB ab.
 * city_id/category_id bewusst ohne FK, damit Stadt-/Kategorie-Pflege keine Buchungen loescht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedTinyInteger('slot')->comment('1..3');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('active')->comment('App\Constants\FeaturedPlacementStatus');
            $table->string('subscription_ref')->nullable()->comment('Subscription-ID in der Central-DB');
            $table->unsignedTinyInteger('active_slot')
                ->nullable()
                ->storedAs("CASE WHEN `status` = 'active' THEN `slot` END")
                ->comment('Hilfsspalte fuer den Unique-Index aktiver Slots');
            $table->timestamps();

            $table->unique(['city_id', 'category_id', 'active_slot'], 'fp_city_category_active_slot_unique');
            $table->index(['city_id', 'category_id', 'status'], 'fp_city_category_status_index');
            $table->index(['company_id', 'status'], 'fp_company_status_index');
            $table->index('subscription_ref', 'fp_subscription_ref_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_placements');
    }
};
