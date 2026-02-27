<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('first_claim_at')->nullable()->after('last_seen_at');
            $table->timestamp('onboarding_dismissed_at')->nullable()->after('first_claim_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_claim_at', 'onboarding_dismissed_at']);
        });
    }
};
