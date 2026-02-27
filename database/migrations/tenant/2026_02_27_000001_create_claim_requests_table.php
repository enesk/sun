<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index(); // Central DB user, kein FK
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->text('comment')->nullable(); // Optionaler Kommentar vom Antragsteller
            $table->string('rejection_reason')->nullable(); // Ablehnungsgrund vom Admin
            $table->unsignedBigInteger('reviewed_by')->nullable(); // Admin der geprüft hat
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('status'); // Für Admin-Queue
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_requests');
    }
};
