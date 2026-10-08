<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Admin\Pages\BotProtectionSettings;
use App\Filament\Admin\Resources\TurnstileVerifications\TurnstileVerificationResource;
use App\Filament\Admin\Widgets\BotProtectionStatsWidget;
use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use App\Turnstile\Models\TenantTurnstileSetting;
use App\Turnstile\Models\TurnstileVerification;
use App\Turnstile\Rules\TurnstileRule;
use App\Turnstile\Support\SiteverifyProbe;
use App\Turnstile\Support\TurnstileStats;
use App\Turnstile\View\Components\Turnstile as TurnstileWidget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Maschinenanteil der Abnahme aus #20: aendert die Bot-Schutz-Einstellungen
 * eines Portals wie ein Mensch im Panel und prueft, dass sie sofort wirken —
 * ohne Deployment, ohne Cache-Clear, ohne Browser.
 *
 *   php artisan turnstile:acceptance
 *   php artisan turnstile:acceptance --portal=sanitaerfinden.com --siteverify
 *   php artisan turnstile:acceptance --json
 *
 * Geprueft werden die Punkte 1-7 der Pruefliste von #20 soweit sie ohne Auge
 * pruefbar sind. Was ein Mensch sehen muss (Kasten da / weg, Tabelle und
 * Kennzahlen auf dem Bildschirm), steht am Ende als Handarbeit und ist in
 * docs/qa/turnstile-abnahme.md ausgeschrieben.
 *
 * Der Lauf hinterlaesst keinen geaenderten Zustand: die Zeile in
 * tenant_turnstile_settings wird am Ende auf ihre Ausgangswerte zurueckgesetzt
 * (eine eigens angelegte Zeile wieder geloescht), die Probezeile im Log laeuft
 * in einer Transaktion und wird zurueckgerollt. Ein Secret wird nie ausgegeben.
 *
 * Rueckgabe 0 nur, wenn kein Punkt FEHLER meldet. Lokal ist Punkt 2 gelb: dort
 * gelten die Cloudflare-Testschluessel (#14 liefert die echten).
 */
class TurnstileAcceptance extends Command
{
    private const OK = 'ok';

    private const FEHLER = 'FEHLER';

    private const OFFEN = 'offen';

    /** Die Aktion, an der geprueft wird: auf jedem Portal geschuetzt (#7). */
    private const AKTION = TurnstileAction::CompanyListing;

    protected $signature = 'turnstile:acceptance
        {--portal=sanitaerfinden.com : Domain des Portals aus der Pruefliste von #20}
        {--siteverify : Secret des Portals gegen Cloudflare pruefen (Punkt 2)}
        {--force : Rueckfrage in Produktion ueberspringen}
        {--json : Ergebnis als JSON statt als Tabelle}';

    protected $description = 'Maschinenanteil der Bot-Schutz-Abnahme eines Portals (#20)';

    /** @var list<array{punkt: string, titel: string, zustand: string, befund: string}> */
    private array $zeilen = [];

    public function handle(): int
    {
        $portal = trim((string) $this->option('portal'));
        $tenant = Tenant::query()->where('domain', $portal)->first();

        if (! $tenant instanceof Tenant) {
            $this->components->error("Kein Portal mit der Domain [{$portal}].");
            $this->line('Vorhanden: '.Tenant::query()->whereNotNull('domain')->pluck('domain')->implode(', '));

            return self::FAILURE;
        }

        // Der Lauf schaltet den Schutz dieses Portals fuer wenige Sekunden aus
        // und auf invisible. Auf einem Portal mit echtem Verkehr ist das eine
        // Entscheidung, keine Nebensache.
        if (app()->environment('production') && ! $this->option('force')
            && ! $this->confirm("Bot-Schutz von [{$portal}] waehrend des Laufs kurz umschalten?", false)) {
            $this->components->warn('Abgebrochen. Nichts geaendert.');

            return self::FAILURE;
        }

        $this->punkt1($tenant);
        $this->punkt2($tenant);

        $setting = TenantTurnstileSetting::query()->where('tenant_id', $tenant->getKey())->first();
        $neuAngelegt = $setting === null;

        if ($setting === null) {
            // Genau das macht das Speichern im Panel, wenn noch keine Zeile da
            // ist (docs/turnstile.md §12).
            $setting = TenantTurnstileSetting::query()->create([
                'tenant_id' => $tenant->getKey(),
                'is_enabled' => true,
                'actions_json' => TenantTurnstileSetting::defaultActions(),
            ]);
        }

        $sicherung = [
            'is_enabled' => $setting->is_enabled,
            'actions_json' => $setting->actions_json,
            'secret_key' => $setting->secret_key,
        ];

        try {
            $this->punkt3($tenant, $setting);
            $this->punkt4($tenant, $setting);
            $this->punkt5($tenant, $setting);
            $this->punkt6($tenant, $setting);
            $this->punkt7();
        } finally {
            if ($neuAngelegt) {
                $setting->delete();
            } else {
                $setting->forceFill($sicherung)->save();
            }

            TurnstileConfigResolver::flush();
        }

        return $this->ausgabe($tenant);
    }

    /** Punkt 1: die drei Stellen im Panel sind da und das Portal ist waehlbar. */
    private function punkt1(Tenant $tenant): void
    {
        $fehlend = array_keys(array_filter([
            'Einstellungen › Bot-Schutz' => ! class_exists(BotProtectionSettings::class),
            'Sicherheitspruefungen' => ! class_exists(TurnstileVerificationResource::class),
            'Kennzahlen-Widget' => ! class_exists(BotProtectionStatsWidget::class),
        ]));

        $this->merke(
            '1',
            'Panel-Stellen und Portalauswahl',
            $fehlend === [] ? self::OK : self::FEHLER,
            $fehlend === []
                ? "drei Stellen vorhanden, Portal [{$tenant->domain}] = Tenant {$tenant->getKey()} ({$tenant->name})"
                : 'fehlt: '.implode(', ', $fehlend),
        );
    }

    /**
     * Punkt 2: derselbe Schluessel, den "Verbindung testen" im Panel prueft —
     * eigener Schluessel des Portals, sonst der der Widget-Gruppe zur Domain.
     */
    private function punkt2(Tenant $tenant): void
    {
        $setting = TenantTurnstileSetting::query()->where('tenant_id', $tenant->getKey())->first();
        $gruppe = TurnstileConfigResolver::groupForHost((string) $tenant->domain);
        $herkunft = $setting?->hasOwnSecret() === true
            ? 'eigener Schluessel des Portals'
            : "Widget-Gruppe {$gruppe}";

        $secret = $setting?->hasOwnSecret() === true
            ? (string) $setting->secret_key
            : TurnstileConfigResolver::groupKeys($gruppe)['secret_key'];

        if (! $this->option('siteverify')) {
            $zustand = match (true) {
                $secret === '' => self::FEHLER,
                in_array($secret, TurnstileConfigResolver::TEST_SECRET_KEYS, true) => self::OFFEN,
                default => self::OK,
            };

            $befund = match ($zustand) {
                self::FEHLER => "kein Secret ({$herkunft})",
                self::OFFEN => "Cloudflare-Testschluessel ({$herkunft}) — echte Schluessel kommen aus #14",
                default => "Secret hinterlegt ({$herkunft}), ungeprueft — mit --siteverify pruefen",
            };

            $this->merke('2', 'Verbindung testen (Secret)', $zustand, $befund);

            return;
        }

        $ergebnis = SiteverifyProbe::check($secret);

        $this->merke(
            '2',
            'Verbindung testen (Secret)',
            match ($ergebnis->state) {
                SiteverifyProbe::STATE_OK => self::OK,
                SiteverifyProbe::STATE_TEST_KEY => self::OFFEN,
                default => self::FEHLER,
            },
            "{$ergebnis->label} ({$herkunft})",
        );
    }

    /**
     * Punkt 3: Schalter aus heisst kein Widget am Formular und keine Pruefung —
     * sofort, allein durch die Zeile in der Datenbank.
     */
    private function punkt3(Tenant $tenant, TenantTurnstileSetting $setting): void
    {
        $this->schalte($setting, ['is_enabled' => false]);
        $aus = $this->widget($tenant);

        $this->schalte($setting, ['is_enabled' => true]);
        $an = $this->widget($tenant);

        $erwartet = $aus['rendert'] === false && $aus['enabled'] === false
            && $an['rendert'] === true && $an['enabled'] === true;

        $this->merke(
            '3',
            'Schalter "Bot-Schutz aktiv" aus / an',
            $erwartet ? self::OK : self::FEHLER,
            sprintf(
                'aus: Widget %s, Pruefung %s · an: Widget %s, Pruefung %s',
                $aus['rendert'] ? 'da' : 'weg',
                $aus['enabled'] ? 'aktiv' : 'aus',
                $an['rendert'] ? 'da' : 'weg',
                $an['enabled'] ? 'aktiv' : 'aus',
            ),
        );
    }

    /** Punkt 4: invisible nimmt den Kasten weg, laesst die Pruefung aber stehen. */
    private function punkt4(Tenant $tenant, TenantTurnstileSetting $setting): void
    {
        $managed = $this->mitModus($tenant, $setting, TurnstileMode::Managed);
        $invisible = $this->mitModus($tenant, $setting, TurnstileMode::Invisible);

        $erwartet = $managed['sichtbar'] === true
            && $invisible['sichtbar'] === false
            && $invisible['enabled'] === true
            && $invisible['appearance'] === 'interaction-only';

        $this->merke(
            '4',
            'Darstellung managed → invisible',
            $erwartet ? self::OK : self::FEHLER,
            sprintf(
                'managed: Kasten %s · invisible: Kasten %s, appearance %s, Pruefung %s',
                $managed['sichtbar'] ? 'sichtbar' : 'unsichtbar',
                $invisible['sichtbar'] ? 'sichtbar' : 'unsichtbar',
                $invisible['appearance'],
                $invisible['enabled'] ? 'laeuft weiter' : 'AUS',
            ),
        );
    }

    /**
     * Punkt 5: jeder Versuch schreibt genau eine Zeile in das Log des Portals.
     * Geprueft mit einem fehlenden Token — das faellt auch bei fail_mode=open
     * durch (docs/turnstile.md §5). Die Zeile wird zurueckgerollt.
     */
    private function punkt5(Tenant $tenant, TenantTurnstileSetting $setting): void
    {
        $this->schalte($setting, ['is_enabled' => true, 'actions_json' => $this->actions(TurnstileMode::Managed)]);

        try {
            $befund = $tenant->run(function (): array {
                $verbindung = DB::connection('tenant');
                $verbindung->beginTransaction();

                try {
                    $vorher = TurnstileVerification::query()->count();

                    $validator = Validator::make(
                        [TurnstileWidget::FIELD => ''],
                        [TurnstileWidget::FIELD => [new TurnstileRule(self::AKTION)]],
                    );
                    $abgewiesen = $validator->fails();

                    $zeile = TurnstileVerification::query()->orderByDesc('id')->first();

                    return [
                        'abgewiesen' => $abgewiesen,
                        'neu' => TurnstileVerification::query()->count() - $vorher,
                        'outcome' => $zeile?->outcome->value,
                        'action' => $zeile?->action->value,
                    ];
                } finally {
                    $verbindung->rollBack();
                }
            });
        } catch (Throwable $exception) {
            $this->merke('5', 'Logzeile je Versuch', self::FEHLER, 'Log nicht lesbar: '.$exception->getMessage());

            return;
        }

        $erwartet = $befund['abgewiesen'] === true
            && $befund['neu'] === 1
            && $befund['outcome'] === 'failed'
            && $befund['action'] === self::AKTION->value;

        $this->merke(
            '5',
            'Logzeile je Versuch',
            $erwartet ? self::OK : self::FEHLER,
            sprintf(
                'Versuch ohne Token: %s, neue Zeilen %d, Ergebnis %s, Formular %s',
                $befund['abgewiesen'] ? 'abgewiesen' : 'DURCHGELASSEN',
                $befund['neu'],
                $befund['outcome'] ?? '–',
                $befund['action'] ?? '–',
            ),
        );
    }

    /**
     * Punkt 6: leer speichern behaelt den Wert, und der Wert steht nirgends im
     * Klartext — nicht in der Datenbank, nicht in toArray(), nicht im Logfile.
     *
     * Geprueft wird mit einem Wegwerf-Secret, nicht mit dem echten: der Wert
     * wird am Ende des Laufs ueberschrieben (Sicherung in handle()).
     */
    private function punkt6(Tenant $tenant, TenantTurnstileSetting $setting): void
    {
        $maengel = [];

        // Liegt schon ein eigener Schluessel in der Zeile, wird er nicht
        // angefasst: ein abgebrochener Lauf darf ein Portal nicht ohne Secret
        // zuruecklassen. Nur ohne eigenen Schluessel wird einer eingetragen.
        $eigener = (string) $setting->secret_key;
        $probe = $eigener !== '' ? $eigener : 'probe-'.bin2hex(random_bytes(16));

        if ($eigener === '') {
            $setting->forceFill(['secret_key' => $probe])->save();
        }

        // Leeres Feld heisst "nicht anfassen": das Panel fuellt den Secret nie
        // ueber fill(), sondern nur im eigenen Zweig (BotProtectionSettings::save).
        $frisch = $setting->fresh();

        if (! $frisch instanceof TenantTurnstileSetting) {
            $this->merke('6', 'Secret bleibt gesetzt und geheim', self::FEHLER, 'Zeile nach dem Speichern nicht lesbar');

            return;
        }

        $frisch->fill([
            'is_enabled' => $frisch->is_enabled,
            'site_key' => $frisch->site_key,
            'fail_mode' => $frisch->fail_mode,
            'actions_json' => $frisch->actions_json,
        ]);

        if ((string) $frisch->secret_key !== $probe || $frisch->isDirty('secret_key')) {
            $maengel[] = 'leer speichern verliert den Secret';
        }

        if ($frisch->secretState() !== __('gesetzt')) {
            $maengel[] = 'Anzeige bleibt nicht auf "gesetzt"';
        }

        if (! in_array('secret_key', $frisch->getHidden(), true)) {
            $maengel[] = 'secret_key nicht in $hidden';
        }

        if (($frisch->getCasts()['secret_key'] ?? null) !== 'encrypted') {
            $maengel[] = 'secret_key nicht verschluesselt gespeichert';
        }

        if (str_contains((string) json_encode($frisch->toArray()), $probe)) {
            $maengel[] = 'Secret steht in toArray()';
        }

        if (str_contains((string) $frisch->getRawOriginal('secret_key'), $probe)) {
            $maengel[] = 'Secret steht im Klartext in der Datenbank';
        }

        // Ein Widget bauen und die Konfiguration aufloesen: nichts davon darf
        // den Secret in eine Logdatei schreiben.
        $this->widget($tenant);

        foreach ($this->logdateien() as $datei) {
            if (str_contains((string) file_get_contents($datei), $probe)) {
                $maengel[] = 'Secret steht in '.basename($datei);
            }
        }

        $this->merke(
            '6',
            'Secret bleibt gesetzt und geheim',
            $maengel === [] ? self::OK : self::FEHLER,
            $maengel === []
                ? sprintf(
                    '%s: Anzeige "%s" bleibt, kein Klartext in Datenbank, toArray() und %d Logdatei(en)',
                    $eigener !== '' ? 'Schluessel des Portals' : 'Wegwerf-Secret',
                    $frisch->secretState(),
                    count($this->logdateien()),
                )
                : implode('; ', $maengel),
        );
    }

    /** Punkt 7: die Zahlen, die das Kennzahlen-Widget zeigt. */
    private function punkt7(): void
    {
        TurnstileStats::flush();

        try {
            $stats = TurnstileStats::network();
        } catch (Throwable $exception) {
            $this->merke('7', 'Kennzahlen 24 h', self::FEHLER, $exception->getMessage());

            return;
        }

        $fenster = $stats['windows']['24h'] ?? [];

        $this->merke(
            '7',
            'Kennzahlen 24 h',
            self::OK,
            sprintf(
                '%d Portale gelesen, 24 h: %s%s',
                $stats['portals'],
                collect($fenster)->map(fn (int $wert, string $name): string => "{$name} {$wert}")->implode(', ') ?: '–',
                $stats['unreadable'] === [] ? '' : ' · nicht lesbar: '.implode(', ', $stats['unreadable']),
            ),
        );
    }

    /**
     * Schreibt die Zeile wie das Panel und raeumt den Resolver-Cache — genau
     * das ist der Nachweis "ohne Deployment, ohne Cache-Clear".
     *
     * @param  array<string, mixed>  $werte
     */
    private function schalte(TenantTurnstileSetting $setting, array $werte): void
    {
        $setting->forceFill($werte)->save();
        TurnstileConfigResolver::flush();
    }

    /**
     * Zustand des Widgets am Formular dieses Portals.
     *
     * @return array{rendert: bool, enabled: bool, sichtbar: bool, appearance: string}
     */
    private function widget(Tenant $tenant): array
    {
        /** @var array{rendert: bool, enabled: bool, sichtbar: bool, appearance: string} $zustand */
        $zustand = $tenant->run(function (): array {
            $component = new TurnstileWidget(self::AKTION->value);
            $config = $component->config;

            return [
                'rendert' => $component->shouldRender(),
                'enabled' => $config instanceof ResolvedTurnstileConfig && $config->enabled,
                'sichtbar' => $component->isVisible(),
                'appearance' => $component->appearance(),
            ];
        });

        return $zustand;
    }

    /**
     * @return array{rendert: bool, enabled: bool, sichtbar: bool, appearance: string}
     */
    private function mitModus(Tenant $tenant, TenantTurnstileSetting $setting, TurnstileMode $modus): array
    {
        $this->schalte($setting, ['is_enabled' => true, 'actions_json' => $this->actions($modus)]);

        return $this->widget($tenant);
    }

    /**
     * @return array<string, array{enabled: bool, mode: string}>
     */
    private function actions(TurnstileMode $modus): array
    {
        $actions = TenantTurnstileSetting::defaultActions();
        $actions[self::AKTION->value] = ['enabled' => true, 'mode' => $modus->value];

        return $actions;
    }

    /** @return list<string> */
    private function logdateien(): array
    {
        return array_values(array_filter((array) glob(storage_path('logs/*.log')), 'is_file'));
    }

    private function merke(string $punkt, string $titel, string $zustand, string $befund): void
    {
        $this->zeilen[] = ['punkt' => $punkt, 'titel' => $titel, 'zustand' => $zustand, 'befund' => $befund];
    }

    private function ausgabe(Tenant $tenant): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'portal' => $tenant->domain,
                'pruefungen' => $this->zeilen,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->abschluss();
        }

        $this->line("Portal: {$tenant->domain} (Tenant {$tenant->getKey()}), Aktion: ".self::AKTION->value);
        $this->table(
            ['#', 'Pruefpunkt', 'Zustand', 'Befund'],
            array_map(fn (array $z): array => [$z['punkt'], $z['titel'], $z['zustand'], $z['befund']], $this->zeilen),
        );

        $this->line('Handarbeit im Browser (docs/qa/turnstile-abnahme.md):');
        $this->line('  · Punkt 3/4: Kasten am Eintragsformular wirklich da / weg / unsichtbar');
        $this->line('  · Punkt 5: /admin/sicherheitspruefungen zeigt die Zeile samt Filtern');
        $this->line('  · Punkt 7: Kennzahlen-Kacheln auf /admin');

        return $this->abschluss();
    }

    private function abschluss(): int
    {
        foreach ($this->zeilen as $zeile) {
            if ($zeile['zustand'] === self::FEHLER) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
