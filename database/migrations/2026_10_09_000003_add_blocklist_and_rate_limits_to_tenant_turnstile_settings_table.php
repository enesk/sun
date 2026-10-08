<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Felder, die das Admin-Panel (#9) pflegt und die Tiefenverteidigung (#8)
 * liest (docs/turnstile.md §4).
 *
 * `blocklist_domains_json` sind die portaleigenen Mail-Domains, die zusaetzlich
 * zur netzweiten Wegwerf-Sperrliste aus #8 abgewiesen werden — eine Liste von
 * Domains ohne Schema und ohne `www.`.
 *
 * `rate_limits_json` sind Obergrenzen je Aktion; `null` oder ein fehlender
 * Schluessel heisst "Vorgabe aus #8", es gibt also keinen Zustand "halb
 * konfiguriert":
 *
 *   {"registration": {"per_ip_per_hour": 10, "per_email_per_day": 3}, ...}
 *
 * Beides gehoert in diese zentrale Tabelle und nicht in die Tenant-DB: der
 * Betreiber pflegt alle Portale in einer Abfrage, so wie die uebrigen
 * Turnstile-Einstellungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_turnstile_settings', function (Blueprint $table) {
            $table->json('blocklist_domains_json')->nullable()->after('actions_json');
            $table->json('rate_limits_json')->nullable()->after('blocklist_domains_json');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_turnstile_settings', function (Blueprint $table) {
            $table->dropColumn(['blocklist_domains_json', 'rate_limits_json']);
        });
    }
};
