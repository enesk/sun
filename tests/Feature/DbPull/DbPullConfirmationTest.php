<?php

declare(strict_types=1);

namespace Tests\Feature\DbPull;

use App\Console\Commands\DbPullCommand;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Die Rueckfrage vor dem ersten vollen Lauf (#8, #10).
 *
 * Ein voller Pull ersetzt die lokale Zentrale: die Test-Tenants mit
 * `.test`-Domains verschwinden. Geprueft wird deshalb nicht nur, dass
 * gefragt wird, sondern dass die genannte Zahl stimmt, dass ein Nein den
 * Lauf beendet und dass die Frage dort entfaellt, wo sie nicht hingehoert.
 *
 * Kein Test fasst Live an: Die Rueckfrage liegt im Befehl vor dem Lock und
 * vor dem ersten SSH-Aufruf, und `Process::fake()` steht zur Sicherheit
 * zusaetzlich davor.
 */
class DbPullConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Richtungssperre: sun:db:pull laeuft ausschliesslich mit
        // APP_ENV=local (LocalMariaDb::localityProblem). In der Testumgebung
        // bricht der Befehl sonst ab, bevor eine Rueckfrage kommt — fuer
        // diese Tests wird die Umgebung deshalb auf local gestellt. Dass die
        // Sperre greift, prueft test_in_fremder_umgebung_laeuft_nichts().
        $this->app->detectEnvironment(fn (): string => 'local');

        // Zugangsdaten muessen gesetzt sein, sonst bricht der Befehl schon
        // vor der Rueckfrage mit "SYNC_DB_USER fehlt" ab.
        config([
            'db-pull.db_user' => 'sync_ro',
            'db-pull.db_password' => 'geheim',
        ]);

        // Letzte Reissleine: kein echter Prozess, kein ssh, kein mysqldump.
        Process::fake(fn () => Process::result('', 'im Test gibt es keine Prozesse', 1));
    }

    public function test_die_rueckfrage_nennt_die_zahl_der_lokalen_und_der_test_portale(): void
    {
        $gesamt = Tenant::query()->count();
        $test = Tenant::query()->where('domain', 'like', '%.test')->count();

        $this->artisan('sun:db:pull')
            ->expectsOutputToContain(sprintf('%d lokale Portal-Zeile(n), davon %d mit .test-Domain', $gesamt, $test))
            ->expectsConfirmation('Lokalen Stand ersetzen?', 'no')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);
    }

    public function test_eine_neue_test_domain_erhoeht_die_genannte_zahl(): void
    {
        $vorher = Tenant::query()->where('domain', 'like', '%.test')->count();

        Tenant::factory()->create(['domain' => 'pruefportal-'.bin2hex(random_bytes(3)).'.test']);

        $this->artisan('sun:db:pull')
            ->expectsOutputToContain(sprintf('davon %d mit .test-Domain', $vorher + 1))
            ->expectsConfirmation('Lokalen Stand ersetzen?', 'no')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);
    }

    public function test_ein_nein_beendet_den_lauf_und_nennt_den_ausweg(): void
    {
        $this->artisan('sun:db:pull')
            ->expectsConfirmation('Lokalen Stand ersetzen?', 'no')
            // In einer Pruefung, nicht in zwei: aufeinanderfolgende
            // expectsOutputToContain arbeiten die Ausgabezeilen der Reihe
            // nach ab, zwei Erwartungen an dieselbe Zeile schlagen fehl.
            // Der Hinweis muss ausserdem lesbar bleiben — mit <db> in
            // spitzen Klammern schluckt der Konsolen-Formatter den Rest.
            ->expectsOutputToContain('Abgebrochen — mit --tenant=sanitaerfinden.com')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);

        // Kein Prozess gestartet heisst: kein Dump, kein ssh, kein drop.
        Process::assertNothingRan();
    }

    public function test_die_warnung_nennt_die_fehlende_sicherung_bei_no_local_backup(): void
    {
        $this->artisan('sun:db:pull --no-local-backup')
            ->expectsOutputToContain('Mit --no-local-backup gibt es KEINE Sicherung.')
            ->expectsConfirmation('Lokalen Stand ersetzen?', 'no')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);
    }

    public function test_ohne_no_local_backup_nennt_die_warnung_den_sicherungsort(): void
    {
        $this->artisan('sun:db:pull')
            ->expectsOutputToContain('storage/app/db-pull/')
            ->expectsConfirmation('Lokalen Stand ersetzen?', 'no')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);
    }

    public function test_bei_central_only_kommt_die_rueckfrage_nicht(): void
    {
        // Ohne expectsConfirmation: kaeme die Frage doch, wuerde der Test an
        // der unerwarteten Interaktion scheitern. Der Lauf scheitert danach
        // am gefakten ssh — das ist hier der Beleg, dass er weitergelaufen ist.
        $this->artisan('sun:db:pull --central-only')
            ->assertFailed();
    }

    public function test_bei_tenant_kommt_die_rueckfrage_nicht(): void
    {
        $this->artisan('sun:db:pull --tenant=tenant_19')
            ->assertFailed();
    }

    public function test_bei_tables_kommt_die_rueckfrage_nicht(): void
    {
        // --tables baut die lokale Datenbank nicht neu auf, es gibt also
        // nichts zu ersetzen und nichts zu fragen (#7).
        $this->artisan('sun:db:pull --tables=companies')
            ->assertFailed();
    }

    public function test_bei_dry_run_kommt_die_rueckfrage_nicht(): void
    {
        $this->artisan('sun:db:pull --dry-run')
            ->assertFailed();
    }

    public function test_no_interaction_ueberspringt_die_rueckfrage(): void
    {
        // Ein Lauf im Skript oder in der CI darf nicht an einer Rueckfrage
        // haengen bleiben.
        $this->artisan('sun:db:pull --no-interaction')
            ->assertFailed();
    }

    public function test_central_only_und_tenant_zusammen_sind_ein_aufruffehler(): void
    {
        $this->artisan('sun:db:pull --central-only --tenant=tenant_19')
            ->expectsOutputToContain('--central-only und --tenant schliessen sich aus.')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);

        Process::assertNothingRan();
    }

    public function test_storage_path_ohne_storage_ist_ein_aufruffehler(): void
    {
        $this->artisan('sun:db:pull --storage-path=media/logos')
            ->expectsOutputToContain('--storage-path und --max-size wirken nur zusammen mit --storage')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);

        Process::assertNothingRan();
    }

    public function test_in_fremder_umgebung_laeuft_nichts(): void
    {
        // Die Richtungssperre ist der aeusserste Schutz: auf einem Server mit
        // APP_ENV=production darf der Befehl gar nichts tun, auch nicht mit
        // --force oder --no-interaction.
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('sun:db:pull --force --no-interaction')
            ->expectsOutputToContain('laeuft ausschliesslich mit APP_ENV=local')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);

        Process::assertNothingRan();
    }

    public function test_fehlende_zugangsdaten_brechen_vor_allem_anderen_ab(): void
    {
        config(['db-pull.db_user' => '', 'db-pull.db_password' => '']);

        $this->artisan('sun:db:pull')
            ->expectsOutputToContain('SYNC_DB_USER oder SYNC_DB_PASSWORD fehlt')
            ->assertExitCode(DbPullCommand::EXIT_USAGE);

        Process::assertNothingRan();
    }
}
