<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stand der neu geschriebenen Profilbeschreibungen je Firma
 * (profiles:rewrite-descriptions). Das Original wird vor jeder Uebernahme
 * gesichert und ist Grundlage fuer profiles:restore-descriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_description_rewrites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('status', 16)->default('pending')
                ->comment('pending, processing, done, failed, skipped');
            $table->mediumText('original_description')->nullable();
            $table->string('original_source', 32)->nullable()
                ->comment('companies.description_source vor der Uebernahme');
            $table->mediumText('generated_description')->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedSmallInteger('prompt_version')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'company_id'], 'pdr_status_company_index');
            $table->index(['applied_at', 'status'], 'pdr_applied_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_description_rewrites');
    }
};
