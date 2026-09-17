<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rohevents der Betriebsstatistik (#15, Epic #1). Keine personenbezogenen Daten:
 * keine IP, kein User-Agent, session_hash = HMAC aus Session-ID und Tagesschluessel.
 * occurred_hour traegt die Deduplikation (ein Event je Betrieb, Typ, Sitzung und Stunde).
 * Rohdaten bleiben 14 Tage, danach loescht stats:aggregate-daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('event_type', 32)->comment('App\Constants\CompanyEventType');
            $table->timestamp('occurred_at');
            $table->timestamp('occurred_hour')->comment('occurred_at auf die Stunde abgerundet');
            $table->char('session_hash', 64);
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('source', 32)->nullable();

            $table->unique(['company_id', 'event_type', 'session_hash', 'occurred_hour'], 'ce_dedup_unique');
            $table->index(['occurred_at', 'company_id', 'event_type'], 'ce_occurred_company_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_events');
    }
};
