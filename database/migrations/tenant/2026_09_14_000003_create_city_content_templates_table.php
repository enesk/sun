<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-weite Vorlage fuer Introtext und FAQ der Stadtseiten (#11).
 * Eine Zeile je Tenant-Datenbank, siehe CityContentTemplate::current().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_content_templates', function (Blueprint $table) {
            $table->id();
            $table->text('intro_template')->nullable();
            $table->json('faq_templates')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_content_templates');
    }
};
