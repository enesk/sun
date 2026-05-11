<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ch_cities', function (Blueprint $table) {
            $table->id();
            $table->string('locality_name');
            $table->string('postal_code', 4)->index();
            $table->unsignedSmallInteger('additional_digit')->default(0);
            $table->unsignedInteger('zip_id')->unique();
            $table->string('municipality_name')->index();
            $table->unsignedInteger('bfs_number')->index();
            $table->string('canton', 2)->index();
            $table->string('address_share', 10)->nullable();
            $table->decimal('coord_east', 12, 3)->nullable();
            $table->decimal('coord_north', 12, 3)->nullable();
            $table->string('language', 2)->default('de');
            $table->date('valid_from')->nullable();
            $table->timestamps();

            $table->index(['postal_code', 'locality_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ch_cities');
    }
};
