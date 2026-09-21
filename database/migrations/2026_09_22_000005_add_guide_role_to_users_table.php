<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolle im Ratgeber-Dashboard (#14), Werte aus App\Guide\Enums\GuideRole.
 *
 * NULL heisst: kein Zugang, ausser das Konto ist Administrator (gilt dann als
 * owner). Kein Index — gelesen wird nur am geladenen Benutzer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('guide_role', 16)->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guide_role');
        });
    }
};
