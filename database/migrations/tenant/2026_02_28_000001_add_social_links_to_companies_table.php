<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('social_facebook', 255)->nullable()->after('website');
            $table->string('social_instagram', 255)->nullable()->after('social_facebook');
            $table->string('social_linkedin', 255)->nullable()->after('social_instagram');
            $table->string('social_youtube', 255)->nullable()->after('social_linkedin');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'social_facebook',
                'social_instagram',
                'social_linkedin',
                'social_youtube',
            ]);
        });
    }
};
