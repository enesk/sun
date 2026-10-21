<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loeschpipeline (SUN-RQ #8): die beiden Tabellen, die eine Loeschung
 * nachweisbar und dauerhaft machen.
 *
 * `gone_urls` haelt die Pfade geloeschter Profile. Die Middleware
 * {@see App\Http\Middleware\GoneUrls} liest sie (gecacht) und antwortet mit
 * **410 Gone** statt 404 — „dauerhaft weg" ist fuer Suchmaschinen ein
 * anderes Signal als „gerade nicht da". Beide URL-Muster eines Portals
 * (`/{id}-{slug}` und `/{stadt}/{id}-{slug}`) bekommen je eine Zeile, damit
 * ein Wechsel von `companies.url_pattern` die Regel nicht aushebelt.
 * Wird derselbe Betrieb neu eingetragen, nimmt
 * {@see App\Companies\Deletion\GoneUrlRegistry::release()} die Zeilen wieder
 * heraus.
 *
 * `deletion_log` ist das Protokoll je Schritt und Lauf. Es bleibt auch nach
 * dem harten Loeschen des Profils stehen (Nachweis nach Art. 17) und traegt
 * deshalb `company_name` und `company_path` als Kopie — ohne Kontaktdaten,
 * ohne Freitext des Antragstellers.
 *
 * Beide Tabellen liegen je Tenant-DB: die Pfade sind portalspezifisch, und
 * die 410-Pruefung laeuft ohnehin im Tenant-Kontext.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gone_urls')) {
            Schema::create('gone_urls', function (Blueprint $table) {
                $table->id();

                // Pfad mit fuehrendem Slash, klein geschrieben, ohne Query.
                // 191 Zeichen wegen utf8mb4 und dem Unique-Index.
                $table->string('path', 191)->unique();

                // Referenz auf das (ggf. schon hart geloeschte) Profil —
                // bewusst ohne Fremdschluessel, damit die Regel den Hard
                // Delete nach 30 Tagen ueberlebt.
                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->string('reason', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('deletion_log')) {
            Schema::create('deletion_log', function (Blueprint $table) {
                $table->id();

                // Ein Lauf der Pipeline, viele Zeilen — eine je Schritt.
                $table->uuid('run_id')->index();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('company_name', 191)->nullable();
                $table->string('company_path', 191)->nullable();

                // profile_requests.id aus der Central-DB, ohne Fremdschluessel
                // (andere Datenbank).
                $table->unsignedBigInteger('request_id')->nullable()->index();

                $table->string('step', 64);
                $table->string('status', 16);
                $table->text('message')->nullable();
                $table->json('payload')->nullable();

                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['company_id', 'step']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_log');
        Schema::dropIfExists('gone_urls');
    }
};
