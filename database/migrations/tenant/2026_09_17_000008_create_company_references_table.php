<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projektreferenzen eines Betriebs (#13, Premium). Fotos haengen per
 * Media Library (Collection photos) an App\Models\Portal\CompanyReference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sort_order'], 'cr_company_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_references');
    }
};
