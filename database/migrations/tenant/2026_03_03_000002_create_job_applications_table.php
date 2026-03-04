<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone', 50)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('pending')->comment('pending, reviewed, contacted, rejected');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Performance-Indexes
            $table->index(['job_id', 'created_at'], 'idx_applications_job_date');
            $table->index(['job_id', 'status'], 'idx_applications_job_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
