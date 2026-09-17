<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachweise fuer das Verifiziert-Badge (#3, Epic #1).
 * reviewed_by_user_id zeigt auf users in der Central-DB, deshalb kein FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_path');
            $table->string('document_type', 32)->comment('App\Constants\CompanyVerificationDocumentType');
            $table->string('status', 16)->default('pending')->comment('App\Constants\CompanyVerificationStatus');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->comment('users.id in der Central-DB');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'cv_company_status_index');
            $table->index(['status', 'created_at'], 'cv_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_verifications');
    }
};
