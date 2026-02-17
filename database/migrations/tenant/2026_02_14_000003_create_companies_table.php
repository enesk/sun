<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('street')->nullable();
            $table->string('house_no', 20)->nullable();
            $table->string('zipcode', 10)->nullable()->index();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete()->index();
            $table->string('tel', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('google_places_id')->nullable()->unique();
            $table->decimal('rating', 2, 1)->default(0.0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('logo_path', 500)->nullable();
            $table->timestamps();

            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
