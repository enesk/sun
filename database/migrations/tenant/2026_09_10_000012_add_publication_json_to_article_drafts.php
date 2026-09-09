<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veroeffentlichungsprotokoll des Entwurfs (#21).
 *
 * `publication_json` haelt fest, was bei der Veroeffentlichung passiert ist
 * und ausserhalb der Anwendung wirkt: die Antwort des IndexNow-Pings, der
 * angelegte Fingerprint, die neu geschriebene Ratgeber-Sitemap, die
 * verworfenen Cache-Schluessel und die eingetragenen Backlinks.
 *
 * Bewusst eine JSON-Spalte und keine eigene Tabelle: Es ist ein Protokoll je
 * Entwurf, das nur im Content-Panel und in der Fehlersuche gelesen wird — es
 * wird nie gefiltert oder aggregiert.
 *
 * Struktur:
 *   published_at, article_id, url
 *   fingerprint: {id, has_embedding}
 *   indexnow:    {status, http_status, urls[], key_location, message, pinged_at}
 *   sitemap:     {file, written_at}
 *   backlinks:   [{article_id, url, title, linked_at}]   (auf dem Zielentwurf)
 *   unpublished_at, unpublished_reason
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->json('publication_json')->nullable()->after('assets_json');
        });
    }

    public function down(): void
    {
        Schema::table('article_drafts', function (Blueprint $table) {
            $table->dropColumn('publication_json');
        });
    }
};
