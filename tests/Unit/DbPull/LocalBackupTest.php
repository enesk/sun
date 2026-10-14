<?php

declare(strict_types=1);

namespace Tests\Unit\DbPull;

use App\Services\DbPull\LocalBackup;
use App\Services\DbPull\LocalMariaDb;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

/**
 * Sicherung des lokalen Standes vor dem `drop database` (#8, #10).
 *
 * Der wichtigste Fall steht zuerst: reicht der Plattenplatz nicht, muss der
 * Pull abbrechen, BEVOR irgendetwas ersetzt wird. Live ist hier nie
 * beteiligt — mysqldump laeuft gefakt, es wird nichts gedumpt.
 */
class LocalBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/db-pull-'.bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);

        Mockery::close();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_zu_wenig_platz_meldet_ein_problem_und_nennt_die_zahlen(): void
    {
        // 10 GB Datenbank, Faktor 0,5 → 5 GB noetig. So viel ist auf keiner
        // Testmaschine im Testverzeichnis frei zu machen, also wird der
        // freie Platz nicht gefaelscht, sondern die Datenbank riesig gesetzt.
        $backup = $this->backup(bytes: 10 * 1024 ** 4);

        $problem = $backup->spaceProblem('sun');

        $this->assertNotNull($problem, 'Bei 10 TB Datenbank muss die Platzpruefung anschlagen.');
        $this->assertStringContainsString('Zu wenig Plattenplatz', $problem);
        $this->assertStringContainsString('`sun`', $problem);
        $this->assertStringContainsString('--no-local-backup', $problem, 'Die Meldung muss den Ausweg nennen.');
    }

    public function test_genug_platz_meldet_kein_problem(): void
    {
        $backup = $this->backup(bytes: 1024);

        $this->assertNull($backup->spaceProblem('sun'));
    }

    public function test_leere_datenbank_blockiert_den_pull_nicht(): void
    {
        // Ohne Daten ist der geforderte Platz 0: eine frische lokale
        // Datenbank darf den Pull nicht an der Platzpruefung scheitern lassen.
        $backup = $this->backup(bytes: 0);

        $this->assertSame(0, $backup->requiredBytes('sun'));
        $this->assertNull($backup->spaceProblem('sun'));
    }

    public function test_der_dateiname_folgt_dem_muster_vor_pull_zeitstempel(): void
    {
        $backup = $this->backup(bytes: 1024);

        $this->assertSame(
            $this->directory.'/vor-pull-20261014-183000.sql.gz',
            $backup->path('20261014-183000'),
        );
    }

    public function test_erfolgreicher_lauf_benennt_die_teildatei_um_und_meldet_die_groesse(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 18:30:00'));

        $backup = $this->backup(bytes: 1024);
        $erwartet = $backup->path('20261014-183000');

        // Der gefakte mysqldump schreibt die .part-Datei, die der Code danach
        // umbenennt — ohne Inhalt waere der Lauf laut Code kein Erfolg.
        Process::fake(function () use ($erwartet) {
            @mkdir(dirname($erwartet), 0775, true);
            file_put_contents($erwartet.'.part', str_repeat('x', 4096));

            return Process::result('', '', 0);
        });

        $result = $backup->run('sun');

        $this->assertTrue($result['ok'], 'Lauf mit geschriebener Datei muss als Erfolg gelten: '.$result['note']);
        $this->assertSame($erwartet, $result['path']);
        $this->assertSame(4096, $result['bytes']);
        $this->assertFileExists($erwartet);
        $this->assertFileDoesNotExist($erwartet.'.part', 'Die Teildatei muss nach dem Umbenennen weg sein.');
    }

    public function test_ein_winziger_dump_gilt_als_fehlschlag(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 18:31:00'));

        $backup = $this->backup(bytes: 1024);
        $erwartet = $backup->path('20261014-183100');

        // Lehre aus der Live-Sicherung, die monatelang leere Dateien schrieb:
        // ein Dump unter 1 KB ist kein Erfolg.
        Process::fake(function () use ($erwartet) {
            @mkdir(dirname($erwartet), 0775, true);
            file_put_contents($erwartet.'.part', 'zu kurz');

            return Process::result('', '', 0);
        });

        $result = $backup->run('sun');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('mysqldump hat nichts geliefert', $result['note']);
    }

    public function test_fehlgeschlagener_dump_hinterlaesst_keine_halbe_sicherung(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 18:32:00'));

        $backup = $this->backup(bytes: 1024);
        $erwartet = $backup->path('20261014-183200');

        Process::fake(function () use ($erwartet) {
            @mkdir(dirname($erwartet), 0775, true);
            file_put_contents($erwartet.'.part', str_repeat('x', 4096));

            return Process::result('', "mysqldump: Got error 1045\n", 2);
        });

        $result = $backup->run('sun');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('1045', $result['note']);
        $this->assertFileDoesNotExist($erwartet.'.part');
        $this->assertFileDoesNotExist($erwartet);
    }

    public function test_angefangene_teildateien_eines_abgebrochenen_laufs_werden_aufgeraeumt(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 18:33:00'));

        $backup = $this->backup(bytes: 1024);
        @mkdir($this->directory, 0775, true);
        $waise = $this->directory.'/vor-pull-20260101-000000.sql.gz.part';
        file_put_contents($waise, 'rest eines abbruchs');

        Process::fake(fn () => Process::result('', '', 0));

        $backup->run('sun');

        $this->assertFileDoesNotExist($waise, 'Reste eines abgebrochenen Laufs belegen sonst Gigabytes.');
    }

    public function test_prune_haelt_die_jungsten_zwei_sicherungen(): void
    {
        $backup = $this->backup(bytes: 1024, keep: 2);
        @mkdir($this->directory, 0775, true);

        foreach (['20261010-100000', '20261011-100000', '20261012-100000', '20261013-100000'] as $stamp) {
            file_put_contents($backup->path($stamp), str_repeat('x', 2048));
        }

        $removed = $backup->prune();

        $this->assertSame(
            ['vor-pull-20261011-100000.sql.gz', 'vor-pull-20261010-100000.sql.gz'],
            $removed,
            'Geloescht werden die aeltesten, nicht die neuesten.',
        );
        $this->assertFileExists($backup->path('20261013-100000'));
        $this->assertFileExists($backup->path('20261012-100000'));
        $this->assertCount(2, $backup->existing());
    }

    public function test_existing_zaehlt_teildateien_nicht_als_sicherung(): void
    {
        $backup = $this->backup(bytes: 1024);
        @mkdir($this->directory, 0775, true);

        file_put_contents($backup->path('20261013-100000'), str_repeat('x', 2048));
        file_put_contents($backup->path('20261014-100000').'.part', str_repeat('x', 2048));

        $this->assertSame([$backup->path('20261013-100000')], $backup->existing());
    }

    public function test_aus_der_konfiguration_kommen_verzeichnis_und_anzahl(): void
    {
        config([
            'db-pull.local_backup.directory' => 'app/db-pull',
            'db-pull.local_backup.keep' => 2,
            'db-pull.local_backup.free_factor' => 0.5,
            'db-pull.local_backup.timeout' => 7200,
        ]);

        $backup = LocalBackup::fromConfig($this->local(0));

        $this->assertSame(storage_path('app/db-pull'), $backup->directory());
        $this->assertSame(2, $backup->keep());
    }

    private function backup(int $bytes, int $keep = 2): LocalBackup
    {
        return new LocalBackup(
            local: $this->local($bytes),
            directory: $this->directory,
            keep: $keep,
            freeFactor: 0.5,
            timeout: 60,
        );
    }

    private function local(int $bytes): LocalMariaDb
    {
        $mock = Mockery::mock(LocalMariaDb::class);
        $mock->shouldReceive('statistics')->andReturn(['tables' => 1, 'bytes' => $bytes]);
        $mock->shouldReceive('dumpCommand')->andReturn("'mysqldump' '--databases' 'sun'");
        $mock->shouldReceive('clientEnv')->andReturn(['MYSQL_PWD' => 'geheim']);

        /** @var LocalMariaDb $local */
        $local = $mock;

        return $local;
    }
}
