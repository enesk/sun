<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugriffsstand der Search-Console-Property (#116).
 *
 * `gsc_property` allein sagt nichts darueber, ob das Dienstkonto die Property
 * ueberhaupt lesen darf: eine Property ohne Nutzereintrag antwortet nicht mit
 * einem Fehler, sondern mit leeren Listen. Der Befund von
 * `content:metrics:preflight` bekommt deshalb einen Platz in der
 * Tenant-Datenbank, damit ihn das Content-Panel zeigen kann, ohne bei jedem
 * Seitenaufruf bei Google nachzufragen.
 *
 * Der Status ist bewusst eine Zeichenkette und kein Enum in der Datenbank:
 * ein neuer Befund braucht dann keine Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->string('gsc_check_status', 20)->nullable()->after('gsc_property');
            $table->timestamp('gsc_checked_at')->nullable()->after('gsc_check_status');
            $table->string('gsc_check_detail', 500)->nullable()->after('gsc_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_content_settings', function (Blueprint $table) {
            $table->dropColumn(['gsc_check_status', 'gsc_checked_at', 'gsc_check_detail']);
        });
    }
};
