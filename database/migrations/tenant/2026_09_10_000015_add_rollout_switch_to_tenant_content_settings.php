<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollout-Schalter der Content-Pipeline (#26).
 *
 * Der gestaffelte Go-Live braucht eine Stelle, an der ein Portal an- und
 * abgeschaltet wird, ohne den Scheduler oder die Konfiguration anzufassen.
 * Vorgabe ist bewusst `false`: nach der Migration laeuft kein Portal
 * automatisch los, freigeschaltet wird einzeln ueber `content:rollout`
 * oder den Schalter in den Panel-Einstellungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('articles_per_day');
            $table->timestamp('activated_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'activated_at']);
        });
    }
};
