<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AdSense Auto Ads je Portal (Ticket #15, Altbestands-Uebernahme).
 *
 * Auto Ads sind laut Architektur-Freeze bewusst kein Anker im Katalog
 * (docs/ad-slots.md, Abschnitt 1): das Skript platziert selbst, ausserhalb
 * jeder Hoehenreservierung. Sie bleiben damit eine Einstellung je Portal —
 * und genau die fehlte bisher, obwohl 15 Portale Auto Ads im Altsystem
 * aktiv haben (Alt-Position `auto_ads`, nur der Loader ohne <ins>-Block).
 *
 * Ohne diese Spalte wuerde die Uebernahme (`ads:legacy-takeover`) diesen Teil
 * des Bestands verlieren. Ausgespielt wird der Schalter erst von #5/#9
 * (Loader nur nach Einwilligung), gepflegt in #10; Vorgabe ist deshalb false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_settings', function (Blueprint $table) {
            $table->boolean('adsense_auto_ads')
                ->default(false)
                ->after('adsense_publisher_id');
        });
    }

    public function down(): void
    {
        Schema::table('ad_settings', function (Blueprint $table) {
            $table->dropColumn('adsense_auto_ads');
        });
    }
};
