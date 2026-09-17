<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leistungskatalog eines Betriebs (#13, ab Pro). price_from ist der
 * "ab"-Preis in Cent (brutto), null = Preis auf Anfrage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_from')->nullable()->comment('Cent, brutto');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'sort_order'], 'cs_company_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_services');
    }
};
