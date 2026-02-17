<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('moderation_status')->default('pending')->after('is_approved');
            $table->text('moderation_note')->nullable()->after('moderation_status');
            $table->string('moderated_by')->nullable()->after('moderation_note');

            $table->index('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['moderation_status', 'moderation_note', 'moderated_by']);
        });
    }
};
