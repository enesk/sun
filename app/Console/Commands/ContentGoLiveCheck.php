<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Models\Central\ContentAlert;
use App\Content\Providers\AdSenseClient;
use App\Content\Providers\IndexNowClient;
use App\Content\Services\TenantRollout;
use App\Models\Portal\AdSlot;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vorabpruefung des Go-Live (#26).
 *
 * Der Befehl haakt die maschinell pruefbaren Punkte der Checkliste
 * docs/content-golive.md ab: Konfiguration, Zugaenge, Budget, Queues,
 * Alarmempfaenger, Sicherung und je Portal die Redaktionseinstellungen.
 * Was nur ein Mensch sehen kann — Stichprobe der Artikel, Sichtung durch
 * Enes, Eintrag des Service Accounts in der Search Console — bleibt in der
 * Checkliste stehen.
 *
 *   php artisan content:golive:check
 *   php artisan content:golive:check --only-active
 */
class ContentGoLiveCheck extends Command
{
    private const OK = 'ok';

    private const WARN = 'warnung';

    private const FAIL = 'fehler';

    protected $signature = 'content:golive:check
        {--only-active : Nur freigeschaltete Portale pruefen}
        {--date= : Stichtag fuer die Sicherung (Y-m-d), Vorgabe: heute}';

    protected $description = 'Prueft die maschinell pruefbaren Punkte der Go-Live-Checkliste der Ratgeber-Pipeline';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(TenantRollout $rollout, IndexNowClient $indexNow, AdSenseClient $adSense): int
    {
        $this->central($rollout, $adSense);
        $this->portals($rollout, $indexNow);

        $this->table(['Zustand', 'Prüfpunkt', 'Befund'], array_map(
            static fn (array $row): array => [
                match ($row[0]) {
                    self::OK => '✓',
                    self::WARN => '!',
                    default => '✗',
                },
                $row[1],
                $row[2],
            ],
            $this->rows,
        ));

        $failed = count(array_filter($this->rows, static fn (array $row): bool => $row[0] === self::FAIL));
        $warned = count(array_filter($this->rows, static fn (array $row): bool => $row[0] === self::WARN));

        $this->info(sprintf('%d Prüfpunkte, %d Fehler, %d Warnungen.', count($this->rows), $failed, $warned));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Alles, was nur einmal existiert: Schalter, Zugaenge, Budget, Queues,
     * Empfaenger, Sicherung.
     */
    private function central(TenantRollout $rollout, AdSenseClient $adSense): void
    {
        $this->check(
            'Pipeline eingeschaltet',
            (bool) config('content.enabled'),
            'content.enabled ist true',
            'content.enabled ist false — CONTENT_PIPELINE_ENABLED setzen',
        );

        $this->check(
            'Zeitzone des Scheduler',
            config('content.pipeline.timezone') !== null,
            (string) config('content.pipeline.timezone'),
            'content.pipeline.timezone fehlt',
        );

        $this->check(
            'Anthropic-Zugang',
            trim((string) config('content.providers.anthropic.api_key')) !== '',
            'Schlüssel gesetzt',
            'ANTHROPIC_API_KEY fehlt',
        );

        $this->check(
            'Voyage-Zugang (Embeddings)',
            trim((string) config('content.providers.voyage.api_key')) !== '',
            'Schlüssel gesetzt',
            'VOYAGE_API_KEY fehlt',
            self::WARN,
        );

        $this->serviceAccount();
        $this->adSense($adSense);
        $this->connectors();
        $this->budget();
        $this->queues();

        $recipients = ContentAlert::ownerRecipients();

        $this->check(
            'Alarm- und Berichtsempfänger',
            $recipients !== [],
            implode(', ', $recipients),
            'Kein aktiver Administrator mit E-Mail-Adresse (php artisan app:create-admin-user)',
        );

        $this->check(
            'APP_KEY gesetzt (IndexNow-Schlüssel)',
            trim((string) config('app.key')) !== '',
            'vorhanden',
            'Ohne APP_KEY lässt sich kein IndexNow-Schlüssel ableiten',
        );

        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();

        $directory = ContentGoLiveBackup::directory($date);
        $files = is_dir($directory) ? glob($directory.'/*.ndjson') ?: [] : [];

        $this->check(
            'Sicherung der Artikeltabellen',
            $files !== [],
            count($files).' Datei(en) in '.$directory,
            "Keine Sicherung für {$date} — php artisan content:golive:backup",
        );

        $active = $rollout->activeTenants()->count();

        $this->check(
            'Freigeschaltete Portale',
            $active > 0,
            $active.' von '.Tenant::count(),
            'Kein Portal freigeschaltet — php artisan content:rollout --activate=<portal>',
            self::WARN,
        );

        $this->deployTarget();
    }

    /**
     * Ertragsdaten aus AdSense (#23). Der Schalter aus ist der dokumentierte
     * Ausfallweg: `pageviews` und `adsense_revenue_usd` bleiben dann null.
     * Deshalb WARN und nie FAIL — nur eine halb eingerichtete Anbindung
     * (Schalter an, aber Kennung oder Schluessel fehlt) ist ein Fehler.
     */
    private function adSense(AdSenseClient $adSense): void
    {
        $label = 'AdSense-Ertragsdaten';

        if (! (bool) config('content.adsense.enabled', false)) {
            $this->row(self::WARN, $label, 'abgeschaltet — pageviews und adsense_revenue_usd bleiben null (CONTENT_ADSENSE_ENABLED)');

            return;
        }

        $account = $adSense->account();
        $missing = [];

        if ($account === null) {
            $missing[] = 'ADSENSE_ACCOUNT_ID (Konto-Kennung)';
        }

        if (! $adSense->hasReadableCredentials()) {
            $missing[] = 'lesbare Schlüsseldatei (ADSENSE_SERVICE_ACCOUNT_JSON, ersatzweise GOOGLE_SERVICE_ACCOUNT_JSON)';
        }

        if ($missing !== []) {
            $this->row(self::FAIL, $label, 'eingeschaltet, aber es fehlt: '.implode(' und ', $missing));

            return;
        }

        $this->row(self::OK, $label, 'eingeschaltet, Konto '.$account);
    }

    /**
     * deploy.php traegt die Platzhalter des Starterkits, solange niemand ein
     * Ziel eingetragen hat. Ohne diese Zeile sieht die Checkliste sauber aus,
     * obwohl es gar kein Staging-Ziel gibt. Nur WARN — der Deploy-Weg ist
     * keine Vorbedingung der Pipeline selbst.
     */
    private function deployTarget(): void
    {
        $label = 'Deploy-Ziel (deploy.php)';
        $file = base_path('deploy.php');

        if (! is_file($file)) {
            $this->row(self::WARN, $label, 'deploy.php fehlt');

            return;
        }

        $contents = (string) file_get_contents($file);

        $placeholders = [
            'host' => '1.2.3.4',
            'domain' => 'yourdomain.com',
            'repository' => 'git@github.com:username/saasykit.git',
        ];

        $unset = [];

        foreach ($placeholders as $variable => $placeholder) {
            if (preg_match('/\$'.$variable.'\s*=\s*([\'"])(.*?)\1\s*;/', $contents, $match) !== 1) {
                continue;
            }

            if (trim($match[2]) === $placeholder) {
                $unset[] = '$'.$variable.' = \''.$placeholder.'\'';
            }
        }

        $this->check(
            $label,
            $unset === [],
            'Host, Domain und Repository sind eingetragen',
            'Noch Starterkit-Platzhalter: '.implode(', ', $unset).' — es gibt kein Deploy-Ziel',
            self::WARN,
        );
    }

    /**
     * Der Service Account fuer die Search Console. Er darf nicht unter
     * public/ liegen — sonst waere die Schluesseldatei aus dem Netz
     * abrufbar (siehe docs/content-security-review.md). Geprueft wird
     * ausserdem, ob der laufende Benutzer die Datei wirklich lesen darf
     * (0600 mit fremdem Eigentuemer ist der haeufige Fall) und ob sie ein
     * Dienstkontoschluessel mit client_email und private_key ist.
     */
    private function serviceAccount(): void
    {
        $path = (string) config('content.providers.search_console.credentials_path');

        if (trim($path) === '') {
            $this->row(self::WARN, 'Search-Console-Dienstkonto', 'GOOGLE_SERVICE_ACCOUNT_JSON nicht gesetzt');

            return;
        }

        $absolute = str_starts_with($path, '/') ? $path : base_path($path);

        if (! is_file($absolute)) {
            $this->row(self::FAIL, 'Search-Console-Dienstkonto', "Datei fehlt: {$absolute}");

            return;
        }

        if (str_starts_with($absolute, public_path())) {
            $this->row(self::FAIL, 'Search-Console-Dienstkonto', 'Die Schlüsseldatei liegt unter public/ und wäre öffentlich abrufbar');

            return;
        }

        if (! is_readable($absolute)) {
            $this->row(self::FAIL, 'Search-Console-Dienstkonto', sprintf(
                'Nicht lesbar: %s (Eigentümer %s, Modus %s) — %s muss die Datei lesen können',
                $absolute,
                $this->fileOwner($absolute),
                $this->fileMode($absolute),
                $this->processUser(),
            ));

            return;
        }

        $raw = @file_get_contents($absolute);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data)) {
            $this->row(self::FAIL, 'Search-Console-Dienstkonto', "Kein lesbares JSON: {$absolute}");

            return;
        }

        $missing = [];

        foreach (['client_email', 'private_key'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            $this->row(self::FAIL, 'Search-Console-Dienstkonto', sprintf(
                'Kein Dienstkontoschlüssel, es fehlt: %s (%s)',
                implode(', ', $missing),
                $absolute,
            ));

            return;
        }

        $this->row(self::OK, 'Search-Console-Dienstkonto', sprintf(
            'lesbar, außerhalb von public/, %s',
            (string) $data['client_email'],
        ));
    }

    /**
     * Eigentuemer und Gruppe der Datei im Klartext, damit die Meldung
     * ohne zweiten Blick auf den Server verstaendlich ist.
     */
    private function fileOwner(string $path): string
    {
        $uid = @fileowner($path);
        $gid = @filegroup($path);

        $user = is_int($uid) ? $this->userName($uid) : 'unbekannt';
        $group = is_int($gid) && function_exists('posix_getgrgid')
            ? ((posix_getgrgid($gid)['name'] ?? null) ?: (string) $gid)
            : (is_int($gid) ? (string) $gid : 'unbekannt');

        return "{$user}:{$group}";
    }

    private function fileMode(string $path): string
    {
        $perms = @fileperms($path);

        return is_int($perms) ? substr(sprintf('%o', $perms), -4) : 'unbekannt';
    }

    /**
     * Der Benutzer, unter dem dieser Prozess laeuft — also derjenige,
     * der die Schluesseldatei lesen koennen muss.
     */
    private function processUser(): string
    {
        if (function_exists('posix_geteuid')) {
            return 'der Prozessbenutzer '.$this->userName(posix_geteuid());
        }

        $name = get_current_user();

        return $name === '' ? 'der PHP-Prozess' : "der Prozessbenutzer {$name}";
    }

    private function userName(int $uid): string
    {
        if (function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid($uid);

            if (is_array($entry) && ($entry['name'] ?? '') !== '') {
                return (string) $entry['name'];
            }
        }

        return (string) $uid;
    }

    /**
     * Zugaenge der eingeschalteten Connectoren aus der Registry
     * content.sources.catalog — dieselbe Quelle wie der Quellen-Monitor.
     */
    private function connectors(): void
    {
        /** @var array<string, array{label: string, config_key: string, credentials: array<string, string>}> $catalog */
        $catalog = (array) config('content.sources.catalog', []);

        foreach ($catalog as $entry) {
            if (! config($entry['config_key'])) {
                continue;
            }

            $missing = [];

            foreach ($entry['credentials'] as $env => $configKey) {
                if (trim((string) config($configKey)) === '') {
                    $missing[] = $env;
                }
            }

            $this->check(
                'Quelle: '.$entry['label'],
                $missing === [],
                'eingeschaltet, Zugang vollständig',
                'eingeschaltet, aber es fehlt: '.implode(', ', $missing),
            );
        }
    }

    private function budget(): void
    {
        $daily = (float) config('content.budget.daily_usd', 0);

        $this->check(
            'Tagesbudget freigegeben',
            $daily > 0,
            number_format($daily, 2).' USD/Tag, '.number_format((float) config('content.budget.monthly_usd', 0), 2).' USD/Monat',
            'content.budget.daily_usd ist 0 — die Pipeline pausiert sofort',
        );

        $perTenant = (float) config('content.budget.daily_usd_per_tenant', 0);
        $tenants = max(1, Tenant::count());

        $this->check(
            'Tagesbudget deckt die Portale',
            $daily >= $perTenant * $tenants * 0.5,
            sprintf('%s USD je Portal × %d Portale gegen %s USD Tagesbudget', number_format($perTenant, 2), $tenants, number_format($daily, 2)),
            sprintf('Das Tagesbudget (%s USD) trägt %d Portale à %s USD nicht', number_format($daily, 2), $tenants, number_format($perTenant, 2)),
            self::WARN,
        );

        $shares = array_sum(array_map('floatval', (array) config('content.budget.provider_share', [])));

        $this->check(
            'Provider-Anteile',
            $shares <= 1.0001,
            'Summe '.number_format($shares, 2),
            'Die Summe der Provider-Anteile ist '.number_format($shares, 2).' und damit größer als 1.0',
        );
    }

    private function queues(): void
    {
        $this->check(
            'Queue-Verbindung',
            config('queue.default') !== 'sync',
            (string) config('queue.default'),
            'queue.default ist sync — die Pipeline liefe im Web-Request',
        );

        $configured = [];

        foreach ((array) config('horizon.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $configured[] = $queue;
            }
        }

        foreach ((array) config('content.pipeline.queues', []) as $name => $queue) {
            $this->check(
                "Queue {$queue}",
                in_array($queue, $configured, true),
                'in einem Horizon-Supervisor',
                "Kein Horizon-Supervisor bedient {$queue} ({$name})",
            );
        }
    }

    /**
     * Je Portal: die Angaben, ohne die ein Artikel weder erzeugt noch
     * sauber ausgeliefert werden kann.
     */
    private function portals(TenantRollout $rollout, IndexNowClient $indexNow): void
    {
        foreach ($rollout->status() as $row) {
            /** @var Tenant $tenant */
            $tenant = $row['tenant'];

            if ($this->option('only-active') && ! $row['active']) {
                continue;
            }

            $label = (string) $tenant->name;
            $prefix = $row['active'] ? $label : $label.' (inaktiv)';

            $domain = trim((string) $tenant->domain);
            $domainProblem = $this->domainProblem($domain);

            $this->check(
                "{$prefix}: Domain",
                $domainProblem === null,
                $domain,
                $domainProblem ?? '',
            );

            $this->check(
                "{$prefix}: Search-Console-Property",
                trim((string) ($row['gsc_property'] ?? '')) !== '',
                (string) $row['gsc_property'],
                'gsc_property ist leer — Metriken und Content-Lücken bleiben aus',
                $row['active'] ? self::FAIL : self::WARN,
            );

            $this->check(
                "{$prefix}: IndexNow-Schlüsseldatei",
                $indexNow->keyLocation($tenant) !== null,
                (string) $indexNow->keyLocation($tenant),
                'Kein Schlüsselort ableitbar (Domain oder APP_KEY fehlt)',
                self::WARN,
            );

            $threshold = (int) $row['threshold'];
            $wanted = $row['ymyl'] ? 90 : 85;

            $this->check(
                "{$prefix}: Auto-Live-Schwelle",
                $threshold >= $wanted,
                $threshold.' (Vorgabe Go-Live: '.$wanted.')',
                sprintf('Schwelle %d liegt unter der Go-Live-Vorgabe %d%s', $threshold, $wanted, $row['ymyl'] ? ' für YMYL-Portale' : ''),
                self::WARN,
            );

            $this->check(
                "{$prefix}: Artikel je Tag",
                $row['articles_per_day'] > 0,
                (string) $row['articles_per_day'],
                'articles_per_day ist 0 — das Portal erzeugt nichts',
                $row['active'] ? self::FAIL : self::WARN,
            );

            if ($row['active']) {
                $this->autoAds($tenant, $prefix);
            }
        }
    }

    /**
     * Anzeigenplatz auto_ads eines freigeschalteten Portals (#100, #112).
     *
     * Auto Ads platzieren sich selbst und blenden ohne Kontoeinstellung einen
     * Anker-Banner ein, der den body paddet und den CLS bis auf 1,0 treibt.
     * Die Auslieferungsregel haelt ihn von den Ratgeber-Seiten fern, auf allen
     * uebrigen Seiten bleibt er. Ob die Anker-Anzeigen im AdSense-Konto
     * abgeschaltet sind, liegt ausserhalb des Repositories und ist von hier aus
     * nicht einsehbar — deshalb nur WARN und nie FAIL.
     */
    private function autoAds(Tenant $tenant, string $prefix): void
    {
        $label = "{$prefix}: Anzeigenplatz auto_ads";

        try {
            /** @var int $active */
            $active = $tenant->run(static fn (): int => AdSlot::query()
                ->active()
                ->forPosition('auto_ads')
                ->count());
        } catch (Throwable $exception) {
            $this->row(self::WARN, $label, 'Anzeigenplätze nicht lesbar: '.$exception->getMessage());

            return;
        }

        if ($active === 0) {
            $this->row(self::OK, $label, 'kein aktiver Platz');

            return;
        }

        $this->row(self::WARN, $label, sprintf(
            '%d aktiver Platz — nur freigeben, wenn im AdSense-Konto unter Auto Ads → Anzeigenformate die Anker-Anzeigen abgeschaltet sind und die Abschaltung protokolliert ist (Datum, Konto, wer). Sonst schiebt der Anker-Banner das Layout um mehrere hundert Pixel (CLS bis 1,0). Von hier aus nicht prüfbar.',
            $active,
        ));
    }

    /**
     * Warum eine Portaldomain unbrauchbar ist — oder null, wenn sie taugt.
     *
     * Eine leere Domain war bisher der einzige geprueffte Fall. Aus
     * Schreibfehlern wie 'zahnarzt.test#' oder 'schlusseldienst' baut
     * IndexNowClient stillschweigend fehlerhafte Adressen (#106); deshalb
     * pruefen wir die Schreibweise mit, statt nur auf Leere.
     */
    private function domainProblem(string $domain): ?string
    {
        if ($domain === '') {
            return 'Ohne Domain gibt es weder Vorschau noch IndexNow';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $domain) === 1) {
            return "'{$domain}' enthält ein Protokoll — erwartet wird nur der Hostname";
        }

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain) !== 1) {
            return "'{$domain}' ist kein gültiger Hostname — IndexNow und Vorschau bauen daraus fehlerhafte Adressen";
        }

        return null;
    }

    private function check(string $label, bool $passed, string $okMessage, string $failMessage, string $levelOnFail = self::FAIL): void
    {
        $this->row($passed ? self::OK : $levelOnFail, $label, $passed ? $okMessage : $failMessage);
    }

    private function row(string $level, string $label, string $message): void
    {
        $this->rows[] = [$level, $label, $message];
    }
}
