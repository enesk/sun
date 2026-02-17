<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('administrative_area_level_1');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('community')->nullable()->after('longitude');
            $table->string('slug')->nullable()->unique()->after('community');

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['latitude', 'longitude', 'community', 'slug']);
        });
    }
};
