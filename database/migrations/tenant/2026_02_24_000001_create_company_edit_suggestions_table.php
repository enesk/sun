<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_edit_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('field', 30)->comment('address, phone, hours, description, other');
            $table->text('suggested_value');
            $table->text('reason')->nullable();
            $table->string('reporter_name', 100)->nullable();
            $table->string('reporter_email', 255)->nullable();
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_edit_suggestions');
    }
};
