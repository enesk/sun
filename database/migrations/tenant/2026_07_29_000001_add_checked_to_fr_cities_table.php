<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fr_cities')) {
            return;
        }

        Schema::table('fr_cities', function (Blueprint $table) {
            $table->boolean('checked')->default(false)->after('region_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fr_cities')) {
            return;
        }

        Schema::table('fr_cities', function (Blueprint $table) {
            $table->dropColumn('checked');
        });
    }
};
