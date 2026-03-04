<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->string('employment_type', 30)->comment('vollzeit, teilzeit, minijob, ausbildung, praktikum');
            $table->string('location')->nullable()->comment('Standort — falls abweichend von Firmenadresse');
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->unsignedInteger('salary_min')->nullable()->comment('Brutto in EUR');
            $table->unsignedInteger('salary_max')->nullable()->comment('Brutto in EUR');
            $table->string('salary_type', 20)->nullable()->comment('monthly, hourly, yearly');
            $table->date('application_deadline')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('Auto-set: published_at + 30 Tage');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('applications_count')->default(0);
            $table->timestamps();

            // Performance-Indexes
            $table->index(['company_id', 'is_active', 'expires_at'], 'idx_jobs_company_active_expires');
            $table->index(['is_active', 'expires_at', 'published_at'], 'idx_jobs_active_expires_published');
            $table->index(['employment_type', 'is_active'], 'idx_jobs_type_active');
            $table->index(['city_id', 'is_active'], 'idx_jobs_city_active');
            $table->fullText(['title', 'description'], 'ft_jobs_title_description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
