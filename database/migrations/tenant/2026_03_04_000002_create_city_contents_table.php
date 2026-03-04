<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->longText('intro_text');
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_generated')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_contents');
    }
};
