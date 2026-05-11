<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ch_cities', function (Blueprint $table) {
            $table->boolean('checked')->default(false)->after('valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('ch_cities', function (Blueprint $table) {
            $table->dropColumn('checked');
        });
    }
};
