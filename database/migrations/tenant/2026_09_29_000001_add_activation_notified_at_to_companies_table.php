<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merker fuer die Freischalt-Mail an den Betriebsinhaber: verhindert, dass
 * ein erneutes Aus- und Einschalten des Eintrags die Mail wiederholt.
 * Bestandseintraege gelten als bereits benachrichtigt (siehe up()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('activation_notified_at')->nullable()->after('is_active');
        });

        // Keine Nachzuegler-Mails fuer bereits freigeschaltete Bestandseintraege.
        \Illuminate\Support\Facades\DB::table('companies')
            ->where('is_active', true)
            ->update(['activation_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('activation_notified_at');
        });
    }
};
