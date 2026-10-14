<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DbPull\AdminAccount;
use App\Services\DbPull\Anonymizer;
use App\Services\DbPull\LocalBackup;
use App\Services\DbPull\LocalMariaDb;
use App\Services\DbPull\RemoteMariaDb;
use App\Services\DbPull\TableFilter;
use App\Services\DbPull\TenantStorageSync;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Holt die zentrale Datenbank und alle Tenant-Datenbanken der Produktion
 * lesend auf den Laptop (#1). Hintergrund und Sicherheitsmodell:
 * docs/db-pull.md.
 *
 *   php artisan sun:db:pull
 *   php artisan sun:db:pull --tenant=sanitaerfinder.com
 *   php artisan sun:db:pull --central-only --dry-run
 *
 * Exit-Codes (an scripts/sun-db-sicherung.sh angelehnt, damit die Zahlen im
 * Projekt dieselbe Bedeutung behalten):
 *
 *   0   alle gewaehlten Datenbanken geholt und importiert
 *  64   falscher Aufruf oder Richtungssperre gegriffen
 *  65   Live nicht erreichbar (SSH oder Datenbank)
 *  66   zu wenige Tenant-Datenbanken gefunden
 *  70   mindestens ein Dump oder Import ist fehlgeschlagen
 */
class DbPullCommand extends Command
{
    public const EXIT_USAGE = 64;

    public const EXIT_UNREACHABLE = 65;

    public const EXIT_TOO_FEW = 66;

    public const EXIT_FAILED = 70;

    /** DEFINER-Klauseln verweisen auf den Live-Benutzer, den es lokal nicht gibt. */
    private const DEFINER_FILTER = "sed -E 's/DEFINER=`[^`]+`@`[^`]+` //g'";

    protected $signature = 'sun:db:pull
        {--central-only : Nur die zentrale Datenbank holen}
        {--tenant=* : Nur diese Portale (Domain, Name, ID oder Datenbankname)}
        {--tables= : Nur diese Tabellen (Komma-getrennt), ohne die lokale Datenbank neu aufzubauen}
        {--only-tables= : Daten nur dieser Tabellen holen (Struktur weiterhin vollstaendig)}
        {--skip-data= : Von diesen Tabellen nur die Struktur holen, zusaetzlich zur Liste in config/db-pull.php}
        {--parallel= : Gleichzeitig geholte Portale (Vorgabe 2, harte Obergrenze 4)}
        {--no-migrate : Nach dem Import kein migrate/tenants:migrate/cache:clear}
        {--no-anonymize : Personendaten NICHT anonymisieren (Vorgabe ist anonymisieren)}
        {--no-local-backup : Den lokalen Stand der Zentrale NICHT vor dem Ersetzen sichern}
        {--force : Rueckfragen unterdruecken (--no-anonymize und die Volumen-Vorwarnung von --storage)}
        {--storage : Zusaetzlich storage/app/public je Portal per rsync holen}
        {--storage-only : Nur die Dateien holen, keine Datenbank anfassen}
        {--storage-path= : Nur diesen Unterbaum von app/public spiegeln, z.B. media/logos}
        {--max-size= : Groessere Dateien auslassen, rsync-Schreibweise (z.B. 2M)}
        {--dry-run : Nur zeigen, was geholt wuerde}';

    protected $description = 'Spiegelt die Live-Datenbanken (zentral + alle Portale) lesend auf den lokalen Rechner, anonymisiert';

    /** @var resource|null */
    private $logHandle = null;

    /** @var resource|null */
    private $lockHandle = null;

    private ?string $startedAt = null;

    /**
     * Ein Tabellenfilter je Bereich ('central'/'tenant'), siehe tableFilter().
     *
     * @var array<string, TableFilter>
     */
    private array $filters = [];

    public function handle(): int
    {
        $local = LocalMariaDb::fromConfig();

        // Richtungssperre: zuerst, vor jeder Verbindung nach Live.
        $problem = $local->localityProblem();

        if ($problem !== null) {
            $this->error($problem);

            return self::EXIT_USAGE;
        }

        // Fehlende Zugangsdaten sind ein Aufruffehler, kein Netzproblem —
        // deshalb vor dem Lock und mit eigenem Exit-Code. Mit --storage-only
        // wird keine Datenbank angefasst, dann genuegt der SSH-Zugang.
        if (! $this->option('storage-only')
            && ((string) config('db-pull.db_user') === '' || (string) config('db-pull.db_password') === '')) {
            $this->error('SYNC_DB_USER oder SYNC_DB_PASSWORD fehlt in der lokalen .env (siehe docs/db-pull.md).');

            return self::EXIT_USAGE;
        }

        if ($this->option('central-only') && array_filter((array) $this->option('tenant')) !== []) {
            $this->error('--central-only und --tenant schliessen sich aus.');

            return self::EXIT_USAGE;
        }

        // Beides begrenzt die Tabellen, aber unterschiedlich: --tables holt
        // NUR die genannten (ohne Neuaufbau, auch keine fremden Strukturen),
        // --only-tables holt die ganze Struktur und nur diese Daten.
        if ($this->commaOption('tables') !== [] && $this->commaOption('only-tables') !== []) {
            $this->error('--tables und --only-tables schliessen sich aus (--tables holt nur die genannten Tabellen, --only-tables die ganze Struktur).');

            return self::EXIT_USAGE;
        }

        if ($this->option('storage-only') && $this->option('central-only')) {
            $this->error('--storage-only und --central-only schliessen sich aus: die Zentrale hat keinen Tenant-Storage.');

            return self::EXIT_USAGE;
        }

        if (! $this->wantsStorage()
            && ((string) ($this->option('storage-path') ?? '') !== '' || (string) ($this->option('max-size') ?? '') !== '')) {
            $this->error('--storage-path und --max-size wirken nur zusammen mit --storage oder --storage-only.');

            return self::EXIT_USAGE;
        }

        // rsync fehlt lokal: Aufruffehler, bevor irgendetwas geholt wird.
        if ($this->wantsStorage()) {
            $hint = TenantStorageSync::missingRsyncHint();

            if ($hint !== null) {
                $this->error($hint);

                return self::EXIT_USAGE;
            }

            // Teilpfad und Groesse jetzt pruefen, nicht erst beim ersten
            // rsync — ein Tippfehler soll nicht nach dem Import auffallen.
            try {
                TenantStorageSync::normalizeSubPath((string) ($this->option('storage-path') ?? config('db-pull.storage_path', '')));
                TenantStorageSync::normalizeMaxSize((string) ($this->option('max-size') ?? config('db-pull.storage_max_size') ?? ''));
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::EXIT_USAGE;
            }
        }

        if (! $this->confirmWithoutAnonymization()) {
            return self::EXIT_USAGE;
        }

        if (! $this->confirmLocalReplacement()) {
            return self::EXIT_USAGE;
        }

        $lockProblem = $this->acquireLock();

        if ($lockProblem !== null) {
            $this->error($lockProblem);

            return self::EXIT_USAGE;
        }

        $this->openLog();

        try {
            return $this->pull($local);
        } catch (RuntimeException $exception) {
            $this->protocol('FEHLER '.$exception->getMessage());
            $this->error($exception->getMessage());

            return self::EXIT_UNREACHABLE;
        } finally {
            $this->closeLog();
            $this->releaseLock();
        }
    }

    private function pull(LocalMariaDb $local): int
    {
        $remote = RemoteMariaDb::fromConfig();
        $prefix = (string) config('db-pull.tenant_database_prefix');

        if ($this->option('storage-only')) {
            return $this->storageOnly($remote, $prefix);
        }
        $anonymize = (bool) config('db-pull.anonymize') && ! $this->option('no-anonymize');

        $this->protocol(sprintf(
            'Start: Host %s, Read-only-Benutzer %s, Anonymisierung %s',
            $remote->sshHost(),
            (string) config('db-pull.db_user'),
            $anonymize ? 'an' : 'AUS',
        ));

        $remote->ensureBinaries();

        $started = microtime(true);
        $central = $remote->centralDatabase();
        $wanted = array_values(array_filter((array) $this->option('tenant')));
        $onlyTables = $this->requestedTables();
        $anonymizeNote = $anonymize;

        // Mit --tenant wird NUR dieses Portal nachgezogen: die zentrale
        // Datenbank bleibt dann unangetastet, sonst wuerde ein
        // "nur ein Portal nachziehen" den lokalen Stand der Zentrale
        // ueberschreiben.
        $pullCentral = $wanted === [];
        $liveTenants = $this->option('central-only') ? [] : $remote->tenantDatabases($prefix);
        $tenantDatabases = [];

        if ($wanted !== []) {
            $tenantDatabases = $this->selectTenantDatabases($liveTenants, $wanted, $prefix);

            if ($tenantDatabases === []) {
                $this->error('Keines der mit --tenant genannten Portale ist auf Live vorhanden — Abbruch.');

                return self::EXIT_USAGE;
            }
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($remote, $local, $central, $pullCentral, $wanted === [] ? $liveTenants : $tenantDatabases, $onlyTables, $prefix);
        }

        $results = [];

        // Vor dem Neuaufbau der Zentrale den lokalen Stand sichern (#8).
        // Mit --tables wird nichts neu aufgebaut, dann ist auch nichts zu
        // retten.
        if ($pullCentral && $onlyTables === []) {
            $abort = $this->backupLocalCentral($local, $central);

            if ($abort !== null) {
                return $abort;
            }
        }

        if ($pullCentral) {
            $results = $this->pullMany($remote, $local, $central, [$central], $onlyTables, $anonymize);

            if (! $results[0]['ok']) {
                $this->renderSummary($results, $started);
                $this->error('Die zentrale Datenbank konnte nicht geholt werden — ohne sie ist die Portal-Liste unbekannt.');

                return self::EXIT_FAILED;
            }

            // Die zentrale Datenbank wurde neu aufgebaut; die offene
            // Verbindung zeigt auf ein Schema, das es zwischenzeitlich nicht
            // gab.
            $local->reconnectCentral();

            // Erst JETZT steht die Portal-Liste fest — sie kommt aus der
            // frisch importierten Zentrale, nicht aus dem alten lokalen
            // Stand.
            if (! $this->option('central-only')) {
                $tenantDatabases = $this->tenantDatabasesFromCentral($liveTenants, $prefix);
            }
        }

        if (! $this->option('central-only') && $wanted === []) {
            $minimum = (int) config('db-pull.min_tenant_databases');

            if (count($tenantDatabases) < $minimum) {
                $message = sprintf(
                    'Nur %d Tenant-Datenbanken gefunden, erwartet mindestens %d — Abbruch (lieber kein Pull als ein halber).',
                    count($tenantDatabases),
                    $minimum,
                );
                $this->protocol('FEHLER '.$message);
                $this->error($message);

                return self::EXIT_TOO_FEW;
            }
        }

        if ($tenantDatabases !== []) {
            $results = array_merge(
                $results,
                $this->pullMany($remote, $local, $central, $tenantDatabases, $onlyTables, $anonymize),
            );
        }

        $failed = array_filter($results, fn (array $result): bool => ! $result['ok']) !== [];
        $imported = array_column(
            array_values(array_filter($results, fn (array $result): bool => $result['ok'])),
            'database'
        );

        if ($this->option('storage')) {
            $failed = $this->pullStorage($remote, $imported, $central, $prefix) || $failed;
        }

        if (! $this->option('no-migrate')) {
            $this->runMigrations(
                in_array($central, $imported, true),
                array_values(array_filter($imported, fn (string $database): bool => $database !== $central)),
                $prefix,
            );
        }

        // Beim Teil-Pull nur, wenn `users` selbst geholt wurde: sonst
        // schriebe ein `--tables=currencies` den lokalen Administrator um
        // (#7, gleiche Zusage wie bei der Anonymisierung).
        $adminWanted = $onlyTables === [] || in_array('users', $onlyTables, true);

        $admin = $anonymize && $adminWanted && in_array($central, $imported, true)
            ? (new AdminAccount($local))->ensure($central)
            : null;

        $this->renderSummary($results, $started);
        $this->line('Protokoll: '.$this->logPath());

        if ($anonymizeNote) {
            $this->components->info(sprintf(
                'Personendaten sind anonymisiert, Passwoerter lauten lokal "%s".',
                (string) config('db-pull.anonymize_password'),
            ));
        } else {
            $this->components->warn('ACHTUNG: ohne Anonymisierung geholt — echte Personendaten liegen lokal.');
        }

        if ($admin !== null) {
            $this->reportAdminAccount($admin);
        }

        if ($this->option('no-migrate')) {
            $this->components->warn('Mit --no-migrate: php artisan migrate, tenants:migrate und cache:clear fehlen noch.');
        }

        return $failed ? self::EXIT_FAILED : self::SUCCESS;
    }

    /**
     * Rueckfrage zu --no-anonymize (#5). Ein Lauf ohne Anonymisierung legt
     * echte Namen, E-Mail-Adressen und Telefonnummern von Firmen und
     * Nutzern auf den Laptop — das soll niemand aus Gewohnheit tippen.
     * `--force` (und `--dry-run`, das nichts holt) ueberspringt die Frage.
     */
    private function confirmWithoutAnonymization(): bool
    {
        if (! $this->option('no-anonymize') || $this->option('force') || $this->option('dry-run')) {
            return true;
        }

        $this->components->warn(
            'ACHTUNG: --no-anonymize holt ECHTE Personendaten (Namen, E-Mail, Telefon) unveraendert '
            .'auf diesen Rechner. Sie unterliegen danach lokal der DSGVO wie auf dem Server: '
            .'kein Backup, keine Weitergabe, nach Gebrauch loeschen.'
        );

        if ($this->confirm('Trotzdem OHNE Anonymisierung holen?', false)) {
            return true;
        }

        $this->line('Abgebrochen — ohne --no-anonymize laeuft der Pull anonymisiert.');

        return false;
    }

    /**
     * Rueckfrage vor einem vollen Lauf (#8). Ohne --tenant und ohne
     * --central-only wird die lokale Zentrale ersetzt: die Test-Tenants mit
     * `.test`-Domains verschwinden aus `tenants`, ihre Datenbanken
     * `tenant_<n>` bleiben als Waisen liegen. Deshalb vorher die Zahl
     * nennen und fragen.
     *
     * Uebersprungen wird die Frage bei --dry-run, --storage-only, --tables
     * (kein Neuaufbau), --force und ohne Terminal (--no-interaction).
     */
    private function confirmLocalReplacement(): bool
    {
        if ($this->option('dry-run') || $this->option('storage-only') || $this->option('force')) {
            return true;
        }

        if ($this->option('central-only') || array_filter((array) $this->option('tenant')) !== []) {
            return true;
        }

        if ($this->requestedTables() !== []) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return true;
        }

        $local = $this->localTenantCount();

        $this->components->warn(sprintf(
            'Ein voller Lauf ersetzt die lokale Zentrale: %d lokale Portal-Zeile(n), davon %d mit .test-Domain, '
            .'werden durch die Live-Portale ersetzt. Die Datenbanken tenant_<n> der Test-Tenants bleiben als '
            .'Waisen liegen.%s',
            $local['total'],
            $local['test'],
            $this->option('no-local-backup')
                ? ' Mit --no-local-backup gibt es KEINE Sicherung.'
                : ' Vorher wird der lokale Stand nach storage/app/db-pull/ gesichert.',
        ));

        if ($this->confirm('Lokalen Stand ersetzen?', false)) {
            return true;
        }

        // Ohne spitze Klammern: der Konsolen-Formatter deutet <db> als Stil-Tag
        // und schluckt den halben Satz (im Test aufgefallen, #10).
        $this->line('Abgebrochen — mit --tenant=sanitaerfinden.com laesst sich ein einzelnes Portal holen, ohne die Zentrale anzufassen.');

        return false;
    }

    /**
     * Lokale Portal-Zeilen fuer die Rueckfrage. Ist die Tabelle nicht
     * lesbar (erster Pull auf einer leeren lokalen Datenbank), zaehlt sie
     * als leer — die Frage bleibt, die Zahlen sind dann 0.
     *
     * @return array{total: int, test: int}
     */
    private function localTenantCount(): array
    {
        try {
            return [
                'total' => Tenant::query()->count(),
                'test' => Tenant::query()->where('domain', 'like', '%.test')->count(),
            ];
        } catch (Throwable) {
            return ['total' => 0, 'test' => 0];
        }
    }

    /**
     * Sichert die lokale Zentrale, bevor sie durch den Live-Stand ersetzt
     * wird (#8). Lokal → lokal, Live ist nicht beteiligt.
     *
     * Rueckgabe ist der Exit-Code, mit dem abgebrochen werden soll, sonst
     * null. Scheitert die Sicherung, wird NICHTS geholt: ein Lauf, der den
     * Arbeitsstand ungesichert wegwirft, ist genau der Fall, den diese
     * Sicherung verhindern soll.
     */
    private function backupLocalCentral(LocalMariaDb $local, string $central): ?int
    {
        if ($this->option('no-local-backup')) {
            $this->components->warn("Mit --no-local-backup: der lokale Stand von `{$central}` wird ohne Sicherung ersetzt.");
            $this->protocol('Ohne lokale Sicherung (--no-local-backup)');

            return null;
        }

        if (! $local->databaseExists($central)) {
            $this->line("Lokal gibt es `{$central}` noch nicht — keine Sicherung noetig.");

            return null;
        }

        $backup = LocalBackup::fromConfig($local);

        $this->components->info(sprintf(
            'Sicherung des lokalen Standes: `%s` ist %s gross, der Dump nach %s dauert einige Minuten.',
            $central,
            $backup->human($backup->estimateBytes($central)),
            $backup->directory(),
        ));

        $problem = $backup->spaceProblem($central);

        if ($problem !== null) {
            $this->protocol('FEHLER '.$problem);
            $this->error($problem);

            return self::EXIT_USAGE;
        }

        $result = $backup->run($central);

        if (! $result['ok']) {
            $message = 'Sicherung des lokalen Standes fehlgeschlagen: '.$result['note'];
            $this->protocol('FEHLER '.$message);
            $this->error($message);
            $this->line('Es wurde nichts geholt. Mit --no-local-backup laeuft der Pull ohne Sicherung.');

            return self::EXIT_FAILED;
        }

        $this->line(sprintf(
            'Lokaler Stand gesichert: %s (%s in %.0f s). Zurueck mit: gunzip -c %s | %s',
            basename($result['path']),
            $backup->human($result['bytes']),
            $result['seconds'],
            $result['path'],
            (string) config('db-pull.local_mysql_cmd'),
        ));
        $this->protocol(sprintf(
            'Lokale Sicherung %s: %d Byte in %.1f s',
            $result['path'],
            $result['bytes'],
            $result['seconds'],
        ));

        $removed = $backup->prune();

        if ($removed !== []) {
            $this->line(sprintf(
                '%d aeltere Sicherung(en) geloescht (%d bleiben): %s',
                count($removed),
                $backup->keep(),
                implode(', ', $removed),
            ));
            $this->protocol('Alte Sicherungen geloescht: '.implode(', ', $removed));
        }

        return null;
    }

    /**
     * Zugang nach dem Lauf. Das Passwort steht absichtlich im Klartext auf
     * dem Bildschirm: es ist ein lokaler Dummy aus der Konfiguration.
     *
     * @param  array{ok: bool, email: string, password: string, created: bool, role: bool, note: string}  $admin
     */
    private function reportAdminAccount(array $admin): void
    {
        if (! $admin['ok']) {
            $this->components->warn('Administrator konnte nicht gesetzt werden: '.$admin['note']);
            $this->protocol('WARNUNG Administrator nicht gesetzt: '.$admin['note']);

            return;
        }

        $this->components->info(sprintf(
            'Administrator %s: %s / %s%s',
            $admin['created'] ? 'angelegt' : 'zurueckgesetzt',
            $admin['email'],
            $admin['password'],
            $admin['role'] ? '' : ' (Rolle "admin" nicht vergeben — Rolle fehlt in der Datenbank)',
        ));

        $this->protocol(sprintf(
            'Administrator %s: %s, Rolle %s',
            $admin['created'] ? 'angelegt' : 'zurueckgesetzt',
            $admin['email'],
            $admin['role'] ? 'admin' : '—',
        ));
    }

    /**
     * Holt eine Liste Datenbanken, standardmaessig zwei gleichzeitig. Jeder
     * Dump ist ein eigener Prozess; faellt einer aus, laufen die uebrigen
     * weiter und der Fehler landet in der Zusammenfassung.
     *
     * @param  array<int, string>  $databases
     * @param  array<int, string>  $onlyTables
     * @return array<int, array<string, mixed>>
     */
    private function pullMany(
        RemoteMariaDb $remote,
        LocalMariaDb $local,
        string $central,
        array $databases,
        array $onlyTables,
        bool $anonymize,
    ): array {
        $tablesByDatabase = $remote->tablesByDatabase($databases);

        if ($onlyTables === []) {
            $this->warnUnknownTables($tablesByDatabase, $central);
        }

        $parallel = min($this->parallelCount(), max(1, count($databases)));
        $queue = array_values($databases);
        $running = [];
        $results = [];

        $this->protocol(sprintf('Hole %d Datenbank(en), %d gleichzeitig', count($queue), $parallel));

        $bar = $this->output->createProgressBar(count($queue));
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage('Start …');
        $bar->start();

        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $parallel) {
                $database = (string) array_shift($queue);

                try {
                    $running[$database] = $this->startDatabase(
                        $remote,
                        $local,
                        $database,
                        $central,
                        $tablesByDatabase[$database] ?? [],
                        $onlyTables,
                    );
                } catch (Throwable $exception) {
                    $results[] = $this->failedResult($database, $exception->getMessage());
                    $this->protocol("FEHLER {$database}: ".$exception->getMessage());
                    $bar->advance();
                }
            }

            $bar->setMessage($running === [] ? 'fertig' : implode(', ', array_keys($running)));
            $bar->display();

            foreach ($running as $database => $job) {
                $overdue = microtime(true) > $job['deadline'];

                if ($job['process']->running() && ! $overdue) {
                    continue;
                }

                if ($overdue && $job['process']->running()) {
                    $this->protocol("FEHLER {$database}: Zeitgrenze von ".$remote->timeout().' s ueberschritten');
                    $job['process']->stop();
                }

                unset($running[$database]);

                $bar->clear();
                $result = $this->finishDatabase($local, $central, $job, $anonymize);
                $results[] = $result;
                $this->line($this->progressLine($result));
                $bar->advance();
                $bar->display();
            }

            if ($running !== []) {
                usleep(250_000);
            }
        }

        $bar->finish();
        $this->newLine();

        return $results;
    }

    /**
     * Baut die lokale Zieldatenbank und startet die Dump-Pipeline. Kommt
     * hier eine Ausnahme, ist nichts gestartet.
     *
     * @param  array<int, string>  $liveTables
     * @param  array<int, string>  $onlyTables
     * @return array<string, mixed>
     */
    private function startDatabase(
        RemoteMariaDb $remote,
        LocalMariaDb $local,
        string $database,
        string $central,
        array $liveTables,
        array $onlyTables,
    ): array {
        $scripts = $this->dumpScripts($remote, $database, $central, $liveTables, $onlyTables);

        if ($onlyTables === []) {
            // Voller Pull: die lokale Datenbank wird neu aufgebaut, damit
            // keine Tabellen aus einem alten Stand stehenbleiben.
            $local->recreateDatabase($database);
        } else {
            $local->createDatabase($database);
        }

        $bytesFile = (string) tempnam(sys_get_temp_dir(), 'sun-db-pull-');
        $started = microtime(true);

        $process = Process::timeout($remote->timeout())
            ->env($local->clientEnv())
            ->input($this->pipelineScript($remote, $local, $database, $scripts, $bytesFile))
            ->start(['bash', '-s']);

        // Welche Tabellen ohne Daten kommen, steht hier fest und wird bis in
        // die Zusammenfassung durchgetragen (#4) — nicht hinterher aus der
        // lokalen Datenbank geraten.
        $skipped = $onlyTables === []
            ? $this->skipDataTables($database, $central, $liveTables)
            : array_values(array_diff($liveTables, $onlyTables));

        return [
            'database' => $database,
            'process' => $process,
            'bytes_file' => $bytesFile,
            'started' => $started,
            'deadline' => $started + $remote->timeout(),
            'skipped' => $skipped,
            // Beim Teil-Pull die geholten Tabellen, sonst leer: die
            // Anonymisierung darf nur anfassen, was dieser Lauf auch
            // ueberschrieben hat (#7).
            'pulled' => $onlyTables === []
                ? []
                : array_values(array_intersect($onlyTables, $liveTables)),
        ];
    }

    /**
     * Wertet einen beendeten Dumplauf aus: Dauer, uebertragene Bytes,
     * danach Anonymisierung und lokale Statistik.
     *
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function finishDatabase(LocalMariaDb $local, string $central, array $job, bool $anonymize): array
    {
        $database = (string) $job['database'];
        $result = $job['process']->wait();
        $seconds = microtime(true) - (float) $job['started'];
        $transferred = $this->readBytes((string) $job['bytes_file']);
        $ok = $result->successful();
        $note = '';

        if (! $ok) {
            $note = $this->errorLine($result->errorOutput()) !== ''
                ? $this->errorLine($result->errorOutput())
                : "Dump-Pipeline mit Code {$result->exitCode()} beendet";
            $this->protocol("FEHLER {$database}: {$note}");
        }

        $anonymized = '—';

        if ($ok && $anonymize) {
            $report = (new Anonymizer($local))->run(
                $database,
                $database === $central ? 'central' : 'tenant',
                array_map('strval', (array) ($job['pulled'] ?? [])),
            );
            $anonymized = sprintf('%d Tab. / %d Zeilen', $report['tables'], $report['rows']);
            $this->protocol(sprintf(
                '%s anonymisiert: %d Tabellen, %d Spalten, %d Zeilen, %d Tabellen geleert',
                $database,
                $report['tables'],
                $report['columns'],
                $report['rows'],
                $report['truncated'],
            ));
        }

        $statistics = $ok ? $local->statistics($database) : ['tables' => 0, 'bytes' => 0];
        $skipped = (array) ($job['skipped'] ?? []);

        if ($ok && $skipped !== []) {
            $this->protocol(sprintf(
                '%s ohne Daten (nur Struktur), %d Tabelle(n): %s',
                $database,
                count($skipped),
                implode(', ', $skipped),
            ));
        }

        $this->protocol(sprintf(
            '%s %s in %.1f s, %s uebertragen, lokal %s, %d Tabellen%s',
            $database,
            $ok ? 'geholt' : 'FEHLGESCHLAGEN',
            $seconds,
            $this->humanBytes($transferred),
            $this->humanBytes($statistics['bytes']),
            $statistics['tables'],
            $note === '' ? '' : " ({$note})",
        ));

        return [
            'database' => $database,
            'ok' => $ok,
            'note' => $note,
            'seconds' => $seconds,
            'transferred' => $transferred,
            'tables' => $statistics['tables'],
            'bytes' => $statistics['bytes'],
            'anonymized' => $anonymized,
            'skipped' => $skipped,
        ];
    }

    /** @return array<string, mixed> */
    private function failedResult(string $database, string $note): array
    {
        return [
            'database' => $database,
            'ok' => false,
            'note' => $note,
            'seconds' => 0.0,
            'transferred' => 0,
            'tables' => 0,
            'bytes' => 0,
            'anonymized' => '—',
            'skipped' => [],
        ];
    }

    /**
     * Die Dumpskripte einer Datenbank: erst Struktur und Daten der
     * Nutztabellen, dann die Struktur der uebersprungenen Protokoll-/
     * Cache-Tabellen. Die Struktur kommt immer mit, damit lokal keine
     * Tabelle fehlt.
     *
     * @param  array<int, string>  $liveTables
     * @param  array<int, string>  $onlyTables
     * @return array<int, string>
     */
    private function dumpScripts(
        RemoteMariaDb $remote,
        string $database,
        string $central,
        array $liveTables,
        array $onlyTables,
    ): array {
        if ($onlyTables !== []) {
            $selected = array_values(array_intersect($onlyTables, $liveTables));

            if ($selected === []) {
                throw new RuntimeException('Keine der mit --tables genannten Tabellen existiert auf Live.');
            }

            return [$remote->dumpScript($database, tables: $selected)];
        }

        $filter = $this->tableFilter($database, $central);
        $skip = $filter->skipData($liveTables);

        // --only-tables: die Struktur der GANZEN Datenbank in einem Lauf,
        // danach die Daten der genannten Tabellen. Einzeln `--no-data` fuer
        // jede uebrige Tabelle aufzufuehren waere dieselbe Wirkung bei
        // dreistelliger Argumentzahl.
        if ($filter->hasOnlyTables()) {
            $selected = $filter->dataTables($liveTables);

            if ($selected === []) {
                throw new RuntimeException('Keine der mit --only-tables genannten Tabellen existiert auf Live.');
            }

            return [
                $remote->dumpScript($database, noData: true),
                $remote->dumpScript($database, tables: $selected),
            ];
        }

        if ($skip === []) {
            return [$remote->dumpScript($database)];
        }

        // Reihenfolge in der Pipe: erst die Struktur der uebersprungenen
        // Tabellen, dann der Voll-Dump des Rests. Beide Mengen sind disjunkt
        // (`--ignore-table` schneidet genau die Skip-Liste weg), die
        // Reihenfolge ist also nur eine Frage der Lesbarkeit im Protokoll —
        // die leeren Tabellen stehen vorn.
        return [
            $remote->dumpScript($database, tables: $skip, noData: true),
            $remote->dumpScript($database, ignore: $skip),
        ];
    }

    /**
     * Die Pipeline als lokales Shell-Skript: Dump auf Live, komprimiert
     * ueber SSH, entpackt und direkt in den lokalen Client. Keine
     * Zwischendatei — weder auf Live noch hier.
     *
     * Das Skript geht ueber STDIN an `bash -s`, das Remote-Skript ueber ein
     * Here-Document an `ssh`: so steht kein Passwort in der Prozessliste,
     * weder lokal noch auf der Produktion.
     *
     * `sed` entfernt DEFINER-Klauseln: sie verweisen auf den Live-Benutzer,
     * den es lokal nicht gibt, und lassen den Import sonst scheitern.
     *
     * Der Zaehler neben der Pipeline (FIFO + `wc -c`) liefert die
     * uebertragenen, also komprimierten Bytes fuer die Zusammenfassung.
     *
     * @param  array<int, string>  $scripts
     */
    private function pipelineScript(
        RemoteMariaDb $remote,
        LocalMariaDb $local,
        string $database,
        array $scripts,
        string $bytesFile,
    ): string {
        $ssh = implode(' ', array_map('escapeshellarg', $remote->sshCommand()));
        $import = $local->importCommand($database);
        $token = bin2hex(random_bytes(4));

        $lines = [
            'set -euo pipefail',
            'BYTES='.escapeshellarg($bytesFile),
            ': > "$BYTES"',
            'WORK="$(mktemp -d)"',
            'trap \'rm -rf "$WORK"\' EXIT',
        ];

        foreach (array_values($scripts) as $index => $script) {
            $delimiter = "SUN_DUMP_{$index}_{$token}";

            $lines[] = 'mkfifo "$WORK/'.$index.'"';
            $lines[] = 'wc -c < "$WORK/'.$index.'" >> "$BYTES" &';
            $lines[] = 'COUNTER=$!';
            $lines[] = $ssh." <<'{$delimiter}' | tee \"\$WORK/{$index}\" | gunzip | "
                .self::DEFINER_FILTER.' | '.$import;
            $lines[] = rtrim($script, "\n");
            $lines[] = $delimiter;
            $lines[] = 'wait "$COUNTER"';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Probelauf: zeigt die geplanten Datenbanken und die Befehle, die
     * laufen wuerden — mit maskiertem Passwort. Es wird nichts geholt und
     * nichts lokal angelegt.
     *
     * @param  array<int, string>  $tenantDatabases
     * @param  array<int, string>  $onlyTables
     */
    private function dryRun(
        RemoteMariaDb $remote,
        LocalMariaDb $local,
        string $central,
        bool $pullCentral,
        array $tenantDatabases,
        array $onlyTables,
        string $prefix,
    ): int {
        $databases = $pullCentral ? array_merge([$central], $tenantDatabases) : $tenantDatabases;
        $tablesByDatabase = $remote->tablesByDatabase($databases);

        $this->table(
            ['Datenbank', 'Tabellen auf Live', 'mit Daten', 'nur Struktur'],
            array_map(function (string $database) use ($central, $tablesByDatabase, $onlyTables): array {
                $live = $tablesByDatabase[$database] ?? [];
                $skip = $onlyTables === []
                    ? $this->skipDataTables($database, $central, $live)
                    : array_values(array_diff($live, $onlyTables));

                return [
                    $database,
                    (string) count($live),
                    (string) (count($live) - count($skip)),
                    (string) count($skip),
                ];
            }, $databases),
        );

        $this->showTableFilter($central, $tablesByDatabase, $onlyTables);

        $this->line(sprintf(
            '%d Datenbank(en), %d gleichzeitig, Anonymisierung %s, Migrationen %s.',
            count($databases),
            min($this->parallelCount(), max(1, count($databases))),
            (bool) config('db-pull.anonymize') && ! $this->option('no-anonymize') ? 'an' : 'AUS',
            $this->option('no-migrate') ? 'AUS' : 'an',
        ));

        if ($pullCentral && ! $this->option('central-only')) {
            $this->line('Portal-Liste hier von "show databases" auf Live — im echten Lauf aus der frisch importierten Zentrale.');
        }

        $this->showLocalBackupPlan($local, $central, $pullCentral && $onlyTables === []);

        $example = $databases[0] ?? null;

        if ($example !== null) {
            $this->newLine();
            $this->line("Befehle am Beispiel {$example} (Passwort maskiert):");
            $this->line($remote->maskSecrets($this->pipelineScript(
                $remote,
                $local,
                $example,
                $this->dumpScripts($remote, $example, $central, $tablesByDatabase[$example] ?? [], $onlyTables),
                sys_get_temp_dir().'/sun-db-pull-bytes',
            )));
        }

        if (! $this->option('no-migrate')) {
            $this->line('Danach: php artisan migrate, php artisan tenants:migrate, php artisan cache:clear');
        }

        $this->protocol('Probelauf, nichts geholt: '.implode(', ', $databases));

        if ($this->option('storage')) {
            $this->pullStorage($remote, $databases, $central, $prefix, dryRun: true);
        }

        return self::SUCCESS;
    }

    /**
     * Im Probelauf: was die Sicherung des lokalen Standes kosten wuerde
     * (#8) — Groesse, freier Platz und die Staende, die danach noch da
     * waeren. Es wird nichts geschrieben.
     */
    private function showLocalBackupPlan(LocalMariaDb $local, string $central, bool $recreatesCentral): void
    {
        if (! $recreatesCentral) {
            return;
        }

        if ($this->option('no-local-backup')) {
            $this->line("--no-local-backup: der lokale Stand von `{$central}` wuerde ohne Sicherung ersetzt.");

            return;
        }

        if (! $local->databaseExists($central)) {
            $this->line("Lokal gibt es `{$central}` nicht — keine Sicherung noetig.");

            return;
        }

        $backup = LocalBackup::fromConfig($local);
        $tenants = $this->localTenantCount();

        $this->line(sprintf(
            'Vorher Sicherung nach %s: `%s` ist %s gross, gefordert sind %s frei, vorhanden %s.',
            $backup->path('<zeitstempel>'),
            $central,
            $backup->human($backup->estimateBytes($central)),
            $backup->human($backup->requiredBytes($central)),
            $backup->human($backup->freeBytes()),
        ));

        $this->line(sprintf(
            'Betroffen: %d lokale Portal-Zeile(n), davon %d mit .test-Domain. Vorhandene Sicherungen: %d (behalten werden %d).',
            $tenants['total'],
            $tenants['test'],
            count($backup->existing()),
            $backup->keep(),
        ));
    }

    /**
     * Im Probelauf: welcher Filter greift und welche genannten Tabellen es
     * auf Live gar nicht gibt (#4). Ein Tippfehler in --only-tables oder
     * --skip-data soll hier auffallen, nicht erst am abgebrochenen Dump.
     *
     * @param  array<string, array<int, string>>  $tablesByDatabase
     * @param  array<int, string>  $onlyTables
     */
    private function showTableFilter(string $central, array $tablesByDatabase, array $onlyTables): void
    {
        if ($onlyTables !== []) {
            $this->line('--tables: nur '.implode(', ', $onlyTables).' — die lokale Datenbank wird NICHT neu aufgebaut.');

            return;
        }

        $this->warnUnknownTables($tablesByDatabase, $central);

        $skipExtra = $this->commaOption('skip-data');

        if ($skipExtra !== []) {
            $this->line('--skip-data ergaenzt die Liste aus config/db-pull.php um: '.implode(', ', $skipExtra));
        }

        if ($this->commaOption('only-tables') !== []) {
            $this->line(
                '--only-tables: Daten nur von '.implode(', ', $this->commaOption('only-tables'))
                .' — die Struktur kommt vollstaendig, die Skip-Liste aus der Konfiguration greift nicht.'
            );
        }
    }

    /**
     * Mit --only-tables genannte Tabellen, die es auf Live nicht gibt (#4).
     *
     * Gewarnt wird einmal je Bereich, nicht je Datenbank: die 23 Portale
     * haben dieselben Tabellen, und eine Warnung 23-mal untereinander liest
     * niemand. Ein Tippfehler faellt dadurch vor dem ersten Dump auf.
     *
     * @param  array<string, array<int, string>>  $tablesByDatabase
     */
    private function warnUnknownTables(array $tablesByDatabase, string $central): void
    {
        $seen = [];

        foreach ($tablesByDatabase as $database => $live) {
            $scope = (string) $database === $central ? 'central' : 'tenant';

            if (isset($seen[$scope])) {
                continue;
            }

            $seen[$scope] = true;
            $unknown = $this->tableFilter((string) $database, $central)->unknownOnlyTables($live);

            if ($unknown !== []) {
                $this->components->warn(sprintf(
                    '--only-tables nennt %s — in %s nicht vorhanden (%s).',
                    implode(', ', $unknown),
                    $scope === 'central' ? 'der Zentrale' : 'den Portal-Datenbanken',
                    $database,
                ));
                $this->protocol('WARNUNG --only-tables unbekannt in '.$database.': '.implode(', ', $unknown));
            }
        }
    }

    /**
     * Die Portal-Liste aus der frisch importierten zentralen Datenbank
     * (Tenant-Model), Datenbanknamen ueber das Praefix der Tenancy-Config.
     * Gegengeprueft gegen `show databases` auf Live: eine Tenant-Zeile ohne
     * Datenbank laesst sich nicht holen, eine Datenbank ohne Tenant-Zeile
     * gehoert zu einem geloeschten Portal (#39).
     *
     * @param  array<int, string>  $liveTenants
     * @return array<int, string>
     */
    private function tenantDatabasesFromCentral(array $liveTenants, string $prefix): array
    {
        try {
            $expected = Tenant::query()->pluck('id')
                ->map(fn ($id): string => $prefix.$id)
                ->all();
        } catch (Throwable $exception) {
            $this->warn('Portal-Liste nicht aus der Zentrale lesbar ('.$exception->getMessage().') — es gilt die Liste von Live.');
            $this->protocol('WARNUNG tenants-Tabelle nicht lesbar: '.$exception->getMessage());

            return $liveTenants;
        }

        if ($expected === []) {
            $this->warn('Die Tabelle tenants ist leer — es gilt die Datenbank-Liste von Live.');

            return $liveTenants;
        }

        foreach (array_diff($expected, $liveTenants) as $database) {
            $this->warn("Portal {$database} ist eingetragen, hat auf Live aber keine Datenbank — uebersprungen.");
            $this->protocol("WARNUNG {$database} ohne Datenbank auf Live");
        }

        $orphans = array_values(array_diff($liveTenants, $expected));

        if ($orphans !== []) {
            $this->line(sprintf(
                '%d Datenbank(en) ohne Tenant-Zeile uebersprungen (Reste geloeschter Portale): %s',
                count($orphans),
                implode(', ', $orphans),
            ));
            $this->protocol('Ohne Tenant-Zeile uebersprungen: '.implode(', ', $orphans));
        }

        return array_values(array_intersect($liveTenants, $expected));
    }

    /**
     * Nach dem Import: lokale Migrationen nachziehen (der lokale Stand ist
     * oft weiter als Live) und den Cache leeren, damit Tenant-Domains und
     * Einstellungen nicht aus dem alten Stand kommen.
     *
     * Ein Fehlschlag ist hier eine Warnung, kein Abbruch: lokal stehen oft
     * fremde Tenant-Migrationen aus, das macht den geholten Datenstand aber
     * nicht unbrauchbar.
     *
     * @param  array<int, string>  $tenantDatabases
     */
    private function runMigrations(bool $central, array $tenantDatabases, string $prefix): void
    {
        $this->components->info('Lokale Migrationen nachziehen …');

        if ($central) {
            $this->callSafely('migrate', ['--force' => true]);
        }

        $tenantIds = array_map(
            fn (string $database): string => substr($database, strlen($prefix)),
            $tenantDatabases,
        );

        // tenants:migrate loest jede ID ueber die zentrale `tenants`-Tabelle
        // auf und bricht beim ersten Unbekannten ganz ab. Ohne Pull der
        // Zentrale (--tenant) kann eine geholte Datenbank lokal noch keine
        // Tenant-Zeile haben.
        $known = $this->knownTenantIds($tenantIds);
        $unknown = array_values(array_diff($tenantIds, $known));

        if ($unknown !== []) {
            $this->components->warn(sprintf(
                'tenants:migrate ohne %d Portal(e) ohne Tenant-Zeile (%s) — erst mit einem Pull der Zentrale nachziehbar.',
                count($unknown),
                implode(', ', $unknown),
            ));
        }

        if ($known !== []) {
            $this->callSafely('tenants:migrate', [
                '--force' => true,
                '--tenants' => $known,
            ]);
        }

        $this->callSafely('cache:clear');
    }

    /**
     * Die Teilmenge der IDs, zu denen es lokal eine Tenant-Zeile gibt.
     *
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private function knownTenantIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            return Tenant::query()->whereIn('id', $ids)->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        } catch (Throwable $exception) {
            $this->protocol('WARNUNG tenants-Tabelle fuer Migrationen nicht lesbar: '.$exception->getMessage());

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callSafely(string $command, array $arguments = []): void
    {
        try {
            $code = $this->call($command, $arguments);
        } catch (Throwable $exception) {
            $this->components->warn("{$command} ist fehlgeschlagen: ".$exception->getMessage());
            $this->protocol("WARNUNG {$command}: ".$exception->getMessage());

            return;
        }

        if ($code !== 0) {
            $this->components->warn("{$command} endete mit Code {$code}.");
            $this->protocol("WARNUNG {$command} Code {$code}");
        }
    }

    /**
     * Gleichzeitige Dumps. Mehr als drei bringt am Uplink nichts und
     * belastet nur Live-MySQL, deshalb die harte Obergrenze aus der
     * Konfiguration.
     */
    private function parallelCount(): int
    {
        $option = trim((string) ($this->option('parallel') ?? ''));
        $value = $option === '' ? (int) config('db-pull.parallel') : (int) $option;
        $maximum = (int) config('db-pull.parallel_max');

        if ($value < 1) {
            $value = 1;
        }

        if ($value > $maximum) {
            $this->warn("--parallel={$value} ist zu hoch — auf {$maximum} begrenzt (Live-MySQL und Uplink).");
            $value = $maximum;
        }

        return $value;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function renderSummary(array $results, float $started): void
    {
        $this->newLine();
        $this->table(
            ['Datenbank', 'Stand', 'Dauer', 'Uebertragen', 'Lokal', 'Tabellen', 'Ohne Daten', 'Anonymisiert'],
            array_map(fn (array $result): array => [
                $result['database'],
                $result['ok'] ? 'ok' : 'FEHLER',
                sprintf('%.1f s', $result['seconds']),
                $this->humanBytes((int) $result['transferred']),
                $this->humanBytes((int) $result['bytes']),
                (string) $result['tables'],
                (string) count((array) ($result['skipped'] ?? [])),
                (string) $result['anonymized'],
            ], $results),
        );

        $this->renderSkipped($results);

        $failed = array_values(array_filter($results, fn (array $result): bool => ! $result['ok']));

        $line = sprintf(
            'Gesamt: %d Datenbank(en) in %s, %s uebertragen, %d Fehler.',
            count($results),
            $this->humanTime(microtime(true) - $started),
            $this->humanBytes((int) array_sum(array_column($results, 'transferred'))),
            count($failed),
        );

        $this->line($line);
        $this->protocol($line);

        foreach ($failed as $result) {
            $this->error("FEHLER {$result['database']}: {$result['note']}");
        }
    }

    /**
     * Welche Tabellen ohne Daten uebertragen wurden (#4).
     *
     * Die Namen stehen nicht in der Hauptspalte: bei 23 Portalen mit
     * derselben Liste waere das 23-mal dasselbe. Stattdessen gruppiert nach
     * identischer Liste — in der Praxis zwei Zeilen, eine fuer die Zentrale
     * und eine fuer die Portale.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function renderSkipped(array $results): void
    {
        $groups = [];

        foreach ($results as $result) {
            $skipped = (array) ($result['skipped'] ?? []);

            if ($skipped === [] || ! $result['ok']) {
                continue;
            }

            sort($skipped);
            $groups[implode(', ', $skipped)][] = (string) $result['database'];
        }

        if ($groups === []) {
            return;
        }

        $this->line('Ohne Daten uebertragen (Struktur vorhanden, 0 Zeilen):');

        foreach ($groups as $tables => $databases) {
            $names = count($databases) > 3
                ? implode(', ', array_slice($databases, 0, 3)).sprintf(' und %d weitere', count($databases) - 3)
                : implode(', ', $databases);

            $this->line(sprintf('  %s: %s', $names, $this->shortList(explode(', ', $tables))));
        }
    }

    /**
     * Eine lange Tabellenliste fuer das Terminal kuerzen. Mit
     * --only-tables stehen hier schnell 49 Namen; vollstaendig stehen sie
     * im Protokoll.
     *
     * @param  array<int, string>  $tables
     */
    private function shortList(array $tables, int $limit = 12): string
    {
        if (count($tables) <= $limit) {
            return implode(', ', $tables);
        }

        return implode(', ', array_slice($tables, 0, $limit))
            .sprintf(' … und %d weitere (vollstaendig im Protokoll)', count($tables) - $limit);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function progressLine(array $result): string
    {
        return sprintf(
            '  %s %s — %.1f s, %s uebertragen%s',
            $result['ok'] ? '✓' : '✗',
            $result['database'],
            $result['seconds'],
            $this->humanBytes((int) $result['transferred']),
            $result['ok'] ? '' : ' ('.$result['note'].')',
        );
    }

    /** Summe der vom Zaehler neben der Pipeline gemeldeten Bytes. */
    private function readBytes(string $path): int
    {
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        @unlink($path);

        $sum = 0;

        foreach (explode("\n", $content) as $line) {
            $sum += (int) trim($line);
        }

        return $sum;
    }

    /**
     * Die aussagekraeftige Zeile aus stderr einer abgebrochenen Pipeline.
     * Reisst ein Glied der Pipeline ab, melden die vorderen Glieder nur
     * "Broken pipe" — die eigentliche Ursache steht davor oder danach.
     */
    private function errorLine(string $text): string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line): bool => $line !== '' && preg_match('/broken pipe|stdout: |^tee: |^gzip: /i', $line) !== 1,
        ));

        return $lines === [] ? '' : $lines[0];
    }

    private function humanTime(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1f s', $seconds);
        }

        return sprintf('%d:%02d min', (int) ($seconds / 60), (int) fmod($seconds, 60));
    }

    /**
     * Tabellen, von denen nur die Struktur geholt wird — geschnitten auf
     * die, die es auf Live wirklich gibt (`mariadb-dump` bricht sonst ab).
     *
     * @param  array<int, string>  $liveTables
     * @return array<int, string>
     */
    private function skipDataTables(string $database, string $central, array $liveTables): array
    {
        return $this->tableFilter($database, $central)->skipData($liveTables);
    }

    /**
     * Der Tabellenfilter je Bereich (#4): Liste aus `config/db-pull.php`,
     * ergaenzt um --skip-data, begrenzt durch --only-tables. Eine Instanz je
     * Bereich, damit die Musterauflösung einmal laeuft und nicht je Abfrage.
     */
    private function tableFilter(string $database, string $central): TableFilter
    {
        $scope = $database === $central ? 'central' : 'tenant';

        return $this->filters[$scope] ??= TableFilter::forScope(
            $scope,
            $this->commaOption('skip-data'),
            $this->commaOption('only-tables'),
        );
    }

    /**
     * Loest `--tenant=` auf: Datenbankname, Tenant-ID, Domain oder Name.
     * Die Zuordnung Domain → Datenbank kommt aus der lokalen `tenants`-
     * Tabelle; fehlt die (erster Pull), bleibt der Vergleich auf den
     * Datenbanknamen.
     *
     * @param  array<int, string>  $available
     * @param  array<int, string>  $wanted
     * @return array<int, string>
     */
    private function selectTenantDatabases(array $available, array $wanted, string $prefix): array
    {
        $selected = [];

        foreach ($wanted as $value) {
            $value = trim((string) $value);

            if (in_array($value, $available, true)) {
                $selected[] = $value;

                continue;
            }

            $database = $this->databaseForTenant($value, $prefix);

            if ($database !== null && in_array($database, $available, true)) {
                $selected[] = $database;

                continue;
            }

            $matches = array_values(array_filter(
                $available,
                fn (string $candidate): bool => str_contains($candidate, $value),
            ));

            if (count($matches) === 1) {
                $selected[] = $matches[0];

                continue;
            }

            $this->warn("Kein Portal zu \"{$value}\" gefunden — uebersprungen.");
            $this->protocol("WARNUNG --tenant={$value} nicht aufloesbar");
        }

        return array_values(array_unique($selected));
    }

    private function databaseForTenant(string $value, string $prefix): ?string
    {
        try {
            $tenant = Tenant::query()
                ->where('id', $value)
                ->orWhere('domain', $value)
                ->orWhere('name', $value)
                ->first();
        } catch (Throwable) {
            return null;
        }

        return $tenant === null ? null : $prefix.$tenant->getKey();
    }

    /** Beide Schalter brauchen rsync. */
    private function wantsStorage(): bool
    {
        return (bool) $this->option('storage') || (bool) $this->option('storage-only');
    }

    /**
     * `--storage-only`: nur die Dateien, keine Datenbank wird angefasst.
     * Die Portal-Liste kommt aus der LOKALEN Zentrale — ohne DB-Import gibt
     * es keine frischere, und ueber die Live-Verzeichnisse darf nicht
     * iteriert werden (dort liegen Ordner geloeschter Portale, #2).
     */
    private function storageOnly(RemoteMariaDb $remote, string $prefix): int
    {
        $wanted = array_values(array_filter((array) $this->option('tenant')));
        $dryRun = (bool) $this->option('dry-run');

        $this->protocol(sprintf(
            '%sStart Storage-Sync, Host %s, nur Dateien',
            $dryRun ? 'Probelauf ' : '',
            $remote->sshHost(),
        ));

        try {
            $all = Tenant::query()->pluck('id')
                ->map(fn ($id): string => $prefix.$id)
                ->all();
        } catch (Throwable $exception) {
            $this->error('Portal-Liste nicht aus der lokalen Zentrale lesbar: '.$exception->getMessage());

            return self::EXIT_USAGE;
        }

        if ($all === []) {
            $this->error('Lokal ist kein Portal eingetragen — erst die Datenbanken holen (php artisan sun:db:pull).');

            return self::EXIT_USAGE;
        }

        $databases = $wanted === [] ? $all : $this->selectTenantDatabases($all, $wanted, $prefix);

        if ($databases === []) {
            $this->error('Keines der mit --tenant genannten Portale ist lokal eingetragen — Abbruch.');

            return self::EXIT_USAGE;
        }

        $started = microtime(true);
        $failed = $this->pullStorage($remote, $databases, '', $prefix, $dryRun);

        $this->line(sprintf('Dauer: %s', $this->humanTime(microtime(true) - $started)));
        $this->line('Protokoll: '.$this->logPath());

        return $failed ? self::EXIT_FAILED : self::SUCCESS;
    }

    /**
     * Holt `storage/tenant<id>/app/public` je Portal per rsync (#6).
     * Laeuft nach den Importen, braucht sie aber nicht: mit --storage-only
     * ist dies der einzige Schritt.
     *
     * @param  array<int, string>  $databases
     * @return bool true, wenn mindestens ein Sync fehlgeschlagen ist
     */
    private function pullStorage(
        RemoteMariaDb $remote,
        array $databases,
        string $central,
        string $prefix,
        bool $dryRun = false,
    ): bool {
        $sync = TenantStorageSync::fromConfig(
            $remote,
            $this->option('storage-path'),
            $this->option('max-size'),
        );
        $failed = false;
        $rows = [];
        $synced = [];
        $skipped = 0;
        $totalFiles = 0;
        $totalBytes = 0;
        $totalDeleted = 0;

        $limits = $sync->limitsLabel();

        $this->components->info(sprintf(
            '%sDateien je Portal holen%s …',
            $dryRun ? 'Probelauf: ' : '',
            $limits === '' ? '' : ' ('.$limits.')',
        ));

        if ($limits !== '') {
            $this->protocol('Storage-Begrenzung: '.$limits);
        }

        if ($sync->maxSize() !== null) {
            $this->components->warn(
                'Mit --max-size bleiben zu grosse Dateien draussen — bereits lokal vorhandene '
                .'werden dabei NICHT geloescht (rsync behandelt sie wie Ausschluesse, '
                .'--delete-excluded wird bewusst nicht gesetzt).'
            );
        }

        foreach ($databases as $database) {
            // Das zentrale storage/app/public ist auf Live nahezu leer
            // (8 KB, #2-Befund) und haelt keine Portal-Bilder — spiegeln
            // wuerde nur lokale Entwicklungsdateien loeschen.
            if ($central !== '' && $database === $central) {
                continue;
            }

            $tenantId = substr($database, strlen($prefix));

            if (! $dryRun && ! $this->confirmStorageVolume($sync, $tenantId)) {
                $skipped++;
                $rows[] = [$tenantId, 'uebersprungen', '—', '—', '—'];
                $this->protocol("Dateien {$database}: wegen Volumen uebersprungen");

                continue;
            }

            try {
                $result = $sync->pull($tenantId, $dryRun);
            } catch (Throwable $exception) {
                $result = ['ok' => false, 'missing' => false, 'target' => '', 'error' => $exception->getMessage(), 'files' => 0, 'bytes' => 0, 'deleted' => 0, 'summary' => ''];
            }

            if ($result['ok']) {
                $synced[] = $tenantId;
                $totalFiles += (int) $result['files'];
                $totalBytes += (int) $result['bytes'];
                $totalDeleted += (int) $result['deleted'];
                $rows[] = [
                    $tenantId,
                    ($result['missing'] ?? false) ? 'nicht vorhanden' : 'ok',
                    (string) $result['files'],
                    $this->humanBytes((int) $result['bytes']),
                    (string) $result['deleted'],
                ];

                $this->protocol(sprintf(
                    '%sDateien %s → %s: %s',
                    $dryRun ? 'Probelauf ' : '',
                    $database,
                    $result['target'],
                    $result['summary'],
                ));

                continue;
            }

            $failed = true;
            $rows[] = [$tenantId, 'FEHLER', '—', '—', '—'];
            $this->error("rsync fuer {$database} fehlgeschlagen: {$result['error']}");
            $this->protocol("FEHLER rsync {$database}: {$result['error']}");
        }

        if ($rows !== []) {
            $this->table(
                ['Portal', 'Stand', 'Dateien', 'Volumen', 'Lokal geloescht'],
                $rows,
            );
        }

        $line = sprintf(
            '%sDateien gesamt: %d Portal(e), %d Datei(en), %s uebertragen, %d lokal geloescht%s.',
            $dryRun ? 'Probelauf, nichts geholt — ' : '',
            count($rows),
            $totalFiles,
            $this->humanBytes($totalBytes),
            $totalDeleted,
            $skipped > 0 ? sprintf(', %d Portal(e) wegen Volumen uebersprungen', $skipped) : '',
        );

        $this->line($line);
        $this->protocol($line);

        if (! $dryRun && $synced !== []) {
            $this->ensureStorageLinks($synced);
        }

        return $failed;
    }

    /**
     * Vorwarnung statt stumpf loszulaufen (#9): liegt ein Portal ueber
     * `db-pull.storage_warn_gigabytes`, wird erst ein Probelauf gemacht und
     * das Ergebnis zur Rueckfrage gestellt. Der Probelauf kostet nur die
     * Dateiliste, nicht die Daten — ein echter Lauf ueber 127 GB kostet
     * Stunden und blockiert die Produktions-HDDs.
     *
     * `--force` (und ein Schwellwert von 0) ueberspringt die Rueckfrage.
     */
    private function confirmStorageVolume(TenantStorageSync $sync, string $tenantId): bool
    {
        $warnBytes = (int) round(((float) config('db-pull.storage_warn_gigabytes', 0)) * 1024 ** 3);

        if ($warnBytes <= 0 || $this->option('force')) {
            return true;
        }

        try {
            $probe = $sync->pull($tenantId, dryRun: true);
        } catch (Throwable $exception) {
            // Scheitert schon der Probelauf, soll der echte Lauf die
            // Fehlermeldung liefern — hier nicht vorgreifen.
            return true;
        }

        if (! $probe['ok'] || (int) $probe['bytes'] <= $warnBytes) {
            return true;
        }

        $this->components->warn(sprintf(
            'Portal %s wuerde %s in %d Datei(en) holen (Schwellwert %s). Das dauert und belastet die Live-HDDs. '
            .'Enger fassen mit --storage-path=media und/oder --max-size=200K.',
            $tenantId,
            $this->humanBytes((int) $probe['bytes']),
            (int) $probe['files'],
            $this->humanBytes($warnBytes),
        ));

        $this->protocol(sprintf(
            'Vorwarnung %s: %s in %d Datei(en) ueber Schwellwert %s',
            $tenantId,
            $this->humanBytes((int) $probe['bytes']),
            (int) $probe['files'],
            $this->humanBytes($warnBytes),
        ));

        return $this->confirm("Portal {$tenantId} trotzdem holen?", false);
    }

    /**
     * Ohne Symlink zeigen die Firmenprofile lokal keine Bilder: die Zentrale
     * braucht public/storage, jedes Portal public/storage-<id>. Beides legt
     * `tenants:storage-link` an (und laesst vorhandene Links in Ruhe) —
     * aufgerufen wird es nur, wenn wirklich ein Link fehlt.
     *
     * @param  array<int, string>  $tenantIds
     */
    private function ensureStorageLinks(array $tenantIds): void
    {
        $missing = array_values(array_filter(
            $tenantIds,
            static fn (string $id): bool => ! is_link(public_path('storage-'.$id)),
        ));

        $centralMissing = ! is_link(public_path('storage')) && ! is_dir(public_path('storage'));

        if ($missing === [] && ! $centralMissing) {
            return;
        }

        $this->components->info(sprintf(
            'Symlinks nachziehen (%d Portal(e)%s) …',
            count($missing),
            $centralMissing ? ' und die Zentrale' : '',
        ));

        // Ohne --tenant, weil die Option dort auf der Spalte `uuid` sucht,
        // waehrend hier der Tenant-Schluessel aus dem Datenbanknamen kommt.
        $this->callSafely('tenants:storage-link');

        if ($centralMissing && ! is_link(public_path('storage'))) {
            $this->callSafely('storage:link');
        }
    }

    /**
     * @return array<int, string>
     */
    private function requestedTables(): array
    {
        return $this->commaOption('tables');
    }

    /**
     * Eine Komma-Liste aus einer Option, leere Felder entfernt.
     *
     * @return array<int, string>
     */
    private function commaOption(string $name): array
    {
        $option = (string) ($this->option($name) ?? '');

        if (trim($option) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $option))));
    }

    /**
     * Ein Lauf gleichzeitig: ein zweiter wuerde auf denselben HDDs der
     * Produktion mitlesen und lokal in dieselben Datenbanken schreiben.
     */
    private function acquireLock(): ?string
    {
        $path = $this->lockPath();
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            return "Lock-Datei {$path} ist nicht beschreibbar.";
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            $startedAt = $this->lockStartedAt($path);
            $minutes = $startedAt === null
                ? null
                : (int) CarbonImmutable::now()->diffInMinutes($startedAt, absolute: true);
            $stale = (int) config('db-pull.lock_stale_after_minutes');

            // Ein Lauf, der haengt oder dessen Lock nach einem harten
            // Abbruch stehengeblieben ist, darf einen neuen Lauf nicht fuer
            // immer blockieren.
            if ($minutes === null || $minutes < $stale) {
                fclose($handle);

                return sprintf(
                    'Ein Sync laeuft bereits (seit %s).',
                    $startedAt === null ? 'unbekannt' : $startedAt->format('H:i'),
                );
            }

            $this->warn(sprintf(
                'Verwaistes Lock von %s (%d Minuten alt, Grenze %d) — wird uebernommen.',
                $startedAt->format('H:i'),
                $minutes,
                $stale,
            ));
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, CarbonImmutable::now()->toIso8601String());
        fflush($handle);

        $this->lockHandle = $handle;

        return null;
    }

    /** Startzeit des Laufs, der das Lock haelt — fuer Meldung und Alter. */
    private function lockStartedAt(string $path): ?CarbonImmutable
    {
        $content = trim((string) @file_get_contents($path));

        if ($content === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($content);
        } catch (Throwable) {
            return null;
        }
    }

    private function lockPath(): string
    {
        return storage_path('app/db-pull.lock');
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
    }

    private function logPath(): string
    {
        return storage_path('logs/db-pull-'.$this->startedAt().'.log');
    }

    private function startedAt(): string
    {
        return $this->startedAt ??= CarbonImmutable::now()->format('Ymd-His');
    }

    private function openLog(): void
    {
        $handle = fopen($this->logPath(), 'a');
        $this->logHandle = $handle === false ? null : $handle;
    }

    private function closeLog(): void
    {
        if ($this->logHandle !== null) {
            fclose($this->logHandle);
            $this->logHandle = null;
        }
    }

    private function protocol(string $line): void
    {
        if ($this->logHandle === null) {
            return;
        }

        fwrite($this->logHandle, CarbonImmutable::now()->format('Y-m-d H:i:s').' '.$line."\n");
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return sprintf('%.1f GB', $bytes / 1024 ** 3);
        }

        if ($bytes >= 1024 ** 2) {
            return sprintf('%.0f MB', $bytes / 1024 ** 2);
        }

        return sprintf('%.0f KB', $bytes / 1024);
    }
}
