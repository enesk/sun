<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loeschpipeline (SUN-RQ #8): `company_inquiries.company_id` und
 * `company_leads.company_id` werden nullable und loesen mit `nullOnDelete`
 * statt `cascadeOnDelete`.
 *
 * Der Grund ist ein Widerspruch, der sonst still bleibt: die Pipeline
 * anonymisiert die Anfragen und **behaelt** sie, weil der Abrechnungsbezug
 * zu den widileads-Kaeufern einer Aufbewahrungspflicht unterliegt
 * (Art. 17 Abs. 3 lit. b; endgueltige Loeschung erst nach
 * `leads.exclusive.retention_months`, Standard 12 Monate). Mit
 * `cascadeOnDelete` haette das harte Loeschen des Profils nach 30 Tagen
 * genau diese Datensaetze mitgenommen — die Anonymisierung waere nach einem
 * Monat wirkungslos gewesen und niemand haette es gemerkt, weil beide
 * Schritte fuer sich richtig aussehen.
 *
 * Nach dem harten Loeschen bleibt die anonyme Zeile mit `company_id = NULL`
 * stehen: Status, Score, Eingangsdatum und Abrechnungsbezug ohne jeden
 * Personenbezug und ohne Bezug zum geloeschten Betrieb. Die Panel-Listen
 * fragen immer ueber `company_id` eines bestehenden Betriebs — eine Zeile
 * ohne Betrieb erscheint dort also nirgends.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['company_inquiries', 'company_leads'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['company_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('company_id')->nullable()->change();

                $blueprint->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Zeilen ohne Betrieb liessen sich nicht zurueckbinden — sie
            // haetten das NOT NULL gebrochen. Sie gehoeren zu geloeschten
            // Profilen und sind bereits anonymisiert.
            DB::table($table)->whereNull('company_id')->delete();

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['company_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('company_id')->nullable(false)->change();

                $blueprint->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            });
        }
    }
};
