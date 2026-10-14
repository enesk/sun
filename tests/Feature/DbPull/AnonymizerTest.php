<?php

declare(strict_types=1);

namespace Tests\Feature\DbPull;

use App\Services\DbPull\Anonymizer;
use App\Services\DbPull\LocalMariaDb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Feature\FeatureTest;

/**
 * Anonymisierung nach dem Import (#5, #7, #10).
 *
 * Gearbeitet wird auf eigenen Hilfstabellen in der Testdatenbank, nicht auf
 * `users` & Co.: der Anonymisierer schreibt mit UPDATE und TRUNCATE, und
 * TRUNCATE committet in MySQL implizit — ein Lauf gegen die Tabellen der
 * Testsuite wuerde deren Daten mitnehmen. Live ist nie beteiligt, der
 * Anonymisierer hat dort grundsaetzlich kein Schreibrecht.
 *
 * Der wichtigste Fall ist der Teil-Pull: `--tables=companies` darf die
 * Regeln fuer alles andere NICHT anfassen, sonst wird lokaler Arbeitsstand
 * ueberschrieben, den niemand geholt hat.
 */
class AnonymizerTest extends FeatureTest
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) DB::connection()->getDatabaseName();

        Schema::dropIfExists('pruef_personen');
        Schema::dropIfExists('pruef_firmen');
        Schema::dropIfExists('pruef_tokens');

        Schema::create('pruef_personen', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('passwort')->nullable();
        });

        Schema::create('pruef_firmen', function ($table) {
            $table->id();
            $table->string('tel')->nullable();
        });

        Schema::create('pruef_tokens', function ($table) {
            $table->id();
            $table->string('token');
        });

        DB::table('pruef_personen')->insert([
            ['name' => 'Echter Mensch', 'email' => 'echt@kunde.de', 'passwort' => 'alt'],
            ['name' => 'Zweiter Mensch', 'email' => 'zwei@kunde.de', 'passwort' => 'alt'],
        ]);
        DB::table('pruef_firmen')->insert([['tel' => '+49 7222 123456']]);
        DB::table('pruef_tokens')->insert([['token' => 'echtes-token']]);

        config([
            'db-pull.anonymize_rules.central' => [
                'pruef_personen' => [
                    'name' => "CONCAT('Nutzer ', id)",
                    'email' => "CONCAT('nutzer', id, '@example.test')",
                    'passwort' => ':password_hash',
                    // Spalte, die es in dieser Tabelle nicht gibt: muss
                    // uebersprungen werden, nicht abbrechen.
                    'gibt_es_nicht' => 'NULL',
                ],
                'pruef_firmen' => [
                    'tel' => "'+49 000 0000000'",
                ],
                // Tabelle, die es nicht gibt: ebenfalls ueberspringen.
                'pruef_gibt_es_nicht' => ['egal' => 'NULL'],
            ],
            'db-pull.anonymize_json.central' => [],
            'db-pull.anonymize_truncate.central' => ['pruef_tokens'],
            'db-pull.anonymize_password' => 'passwort',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pruef_personen');
        Schema::dropIfExists('pruef_firmen');
        Schema::dropIfExists('pruef_tokens');

        Mockery::close();

        parent::tearDown();
    }

    public function test_voller_lauf_maskiert_alle_regeltabellen_und_leert_die_tokentabelle(): void
    {
        $report = $this->anonymizer()->run($this->database, 'central');

        $personen = DB::table('pruef_personen')->orderBy('id')->get();

        $this->assertSame('Nutzer 1', $personen[0]->name);
        $this->assertSame('nutzer1@example.test', $personen[0]->email);
        $this->assertSame('nutzer2@example.test', $personen[1]->email);
        $this->assertStringStartsWith('$2y$', (string) $personen[0]->passwort, 'Das Passwort muss ein Hash sein.');

        $this->assertSame('+49 000 0000000', DB::table('pruef_firmen')->first()->tel);
        $this->assertSame(0, DB::table('pruef_tokens')->count(), 'Tokentabellen werden geleert.');

        $this->assertSame(2, $report['tables']);
        $this->assertSame(1, $report['truncated']);
    }

    public function test_zweiter_lauf_liefert_dieselben_werte(): void
    {
        // Deterministisch ueber die ID, kein Faker: lokale Testlogins und
        // Lesezeichen bleiben nach einem zweiten Pull gueltig.
        $this->anonymizer()->run($this->database, 'central');
        $ersterLauf = DB::table('pruef_personen')->orderBy('id')->pluck('email')->all();

        $this->anonymizer()->run($this->database, 'central');

        $this->assertSame($ersterLauf, DB::table('pruef_personen')->orderBy('id')->pluck('email')->all());
    }

    public function test_teil_pull_begrenzt_die_anonymisierung_auf_die_geholten_tabellen(): void
    {
        // Kern von #7: geholt wurde nur pruef_firmen, also bleibt
        // pruef_personen unberuehrt — es sind lokale Daten, kein Live-Stand.
        $report = $this->anonymizer()->run($this->database, 'central', pulledTables: ['pruef_firmen']);

        $this->assertSame('+49 000 0000000', DB::table('pruef_firmen')->first()->tel);

        $person = DB::table('pruef_personen')->orderBy('id')->first();
        $this->assertSame('Echter Mensch', $person->name, 'Nicht geholte Tabellen duerfen nicht angefasst werden.');
        $this->assertSame('echt@kunde.de', $person->email);

        $this->assertSame(1, $report['tables']);
        $this->assertSame(0, $report['truncated'], 'Auch das TRUNCATE gehoert zum Teil-Pull-Vertrag.');
        $this->assertSame(1, DB::table('pruef_tokens')->count());
    }

    public function test_teil_pull_leert_eine_tokentabelle_nur_wenn_sie_dabei_war(): void
    {
        $report = $this->anonymizer()->run($this->database, 'central', pulledTables: ['pruef_tokens']);

        $this->assertSame(0, DB::table('pruef_tokens')->count());
        $this->assertSame(1, $report['truncated']);
        $this->assertSame('echt@kunde.de', DB::table('pruef_personen')->orderBy('id')->first()->email);
    }

    public function test_unbekannte_tabellen_und_spalten_brechen_den_lauf_nicht_ab(): void
    {
        // Die Regeln duerfen mehr abdecken als ein einzelnes Portal besitzt:
        // ein Portal ohne Stellenanzeigen hat die Tabelle nicht.
        $report = $this->anonymizer()->run($this->database, 'central');

        $this->assertSame(2, $report['tables']);
        $this->assertSame(4, $report['columns'], 'Drei vorhandene Spalten in pruef_personen, eine in pruef_firmen.');
    }

    public function test_json_regeln_entfernen_nur_die_genannten_schluessel(): void
    {
        Schema::dropIfExists('pruef_json');
        Schema::create('pruef_json', function ($table) {
            $table->id();
            $table->json('data')->nullable();
        });

        DB::table('pruef_json')->insert([
            ['data' => json_encode(['kontakt' => 'echt@kunde.de', 'farbe' => '#0077B6'])],
        ]);

        config([
            'db-pull.anonymize_rules.central' => [],
            'db-pull.anonymize_truncate.central' => [],
            'db-pull.anonymize_json.central' => ['pruef_json' => ['data' => ['kontakt']]],
        ]);

        $this->anonymizer()->run($this->database, 'central');

        $data = json_decode((string) DB::table('pruef_json')->first()->data, true);

        $this->assertArrayNotHasKey('kontakt', $data);
        $this->assertSame('#0077B6', $data['farbe'], 'Fachliche Schluessel muessen stehen bleiben.');

        Schema::dropIfExists('pruef_json');
    }

    private function anonymizer(): Anonymizer
    {
        $local = Mockery::mock(LocalMariaDb::class);
        $local->shouldReceive('connection')->andReturn(DB::connection());

        return new Anonymizer($local);
    }
}
