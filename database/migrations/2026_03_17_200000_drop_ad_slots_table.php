<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->dropIfExists('ad_slots');
    }

    public function down(): void
    {
        // Table was removed as part of the old ad system cleanup.
        // The old migration no longer exists.
    }
};
