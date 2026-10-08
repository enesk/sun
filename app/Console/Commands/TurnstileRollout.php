<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use App\Turnstile\Models\TenantTurnstileSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Rollout-Schalter des Bot-Schutzes (#13, docs/turnstile-golive.md §3-§5).
 *
 * Der gestaffelte Rollout laeuft ueber `tenant_turnstile_settings.is_enabled`,
 * nicht ueber `config('turnstile.enabled')` und nicht ueber die
 * Widget-Gruppen: ein Widget darf Hostnames tragen, bei denen der Schutz noch
 * aus ist (docs/turnstile.md §9). Im Admin ist das je Portal einzeln zu
 * klicken (#9) — fuer 23 Portale in drei Wellen braucht der Betrieb einen
 * Griff, der alle auf einmal stellt und den Zustand belegt.
 *
 *   php artisan turnstile:rollout                      # nur anzeigen
 *   php artisan turnstile:rollout --wave=1             # Welle 1 scharfschalten
 *   php artisan turnstile:rollout --activate=fahrschulefinder.de
 *   php artisan turnstile:rollout --deactivate=29
 *   php artisan turnstile:rollout --deactivate-all --force
 *   php artisan turnstile:rollout --wave=1 --mode=non_interactive
 *
 * `--wave=N` meint die N-te Widget-Gruppe aus `config('turnstile.groups')`
 * (1 = A, 2 = B, 3 = C). Welches Portal zu welcher Gruppe gehoert, entscheidet
 * wie im Resolver der Hostname — `apotheke.firmenfreund.de` findet so die
 * Gruppe seines Elterneintrags, ohne in der Liste zu stehen.
 *
 * `--mode` setzt die Darstellung beider geschuetzten Aktionen der betroffenen
 * Portale (Registrierung, Firmeneintragung). Das ist der Weg aus den
 * technischen Hinweisen des Tickets: bei zu vielen blockierten echten Nutzern
 * erst `non_interactive`, bevor ein Portal ganz herausgenommen wird.
 *
 * Geschrieben wird nur `is_enabled` und — mit `--mode` — der Modus. Schluessel,
 * Fail-Mode, Sperrlisten und Grenzen bleiben unangetastet; die gehoeren ins
 * Panel (#9) beziehungsweise in die `.env`.
 */
class TurnstileRollout extends Command
{
    /** Aktionen, die der Rollout stellt. Anfrage und Kontakt kommen spaeter (Welle 4). */
    private const ROLLOUT_ACTIONS = [
        TurnstileAction::Registration,
        TurnstileAction::CompanyListing,
    ];

    protected $signature = 'turnstile:rollout
        {--wave= : Welle (1|2|3) = N-te Widget-Gruppe aus config(turnstile.groups) scharfschalten}
        {--activate=* : Portal scharfschalten (Domain, ID oder UUID)}
        {--deactivate=* : Portal herausnehmen (Domain, ID oder UUID)}
        {--activate-all : Alle Portale scharfschalten}
        {--deactivate-all : Alle Portale herausnehmen}
        {--mode= : Darstellung der geschuetzten Aktionen der betroffenen Portale setzen}
        {--force : Ohne Rueckfrage (Deploy-Skripte)}';

    protected $description = 'Zeigt und setzt den Rollout-Schalter des Bot-Schutzes je Portal (#13)';

    public function handle(): int
    {
        if ($this->option('activate-all') && $this->option('deactivate-all')) {
            $this->error('--activate-all und --deactivate-all schliessen sich aus.');

            return self::FAILURE;
        }

        $mode = $this->mode();

        if ($mode === false) {
            $this->error('--mode muss einer von diesen Werten sein: '.implode(', ', TurnstileMode::values()));

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->get()
            ->sortBy(fn (Tenant $tenant): string => mb_strtolower((string) ($tenant->domain ?? $tenant->name)))
            ->values();

        // Erst alle Angaben aufloesen: eine unbekannte Domain aendert nichts.
        $activate = $this->resolve((array) $this->option('activate'));
        $deactivate = $this->resolve((array) $this->option('deactivate'));

        if ($activate === null || $deactivate === null) {
            return self::FAILURE;
        }

        $wave = $this->waveGroup();

        if ($wave === false) {
            return self::FAILURE;
        }

        if ($wave !== null) {
            /** @var Tenant $tenant */
            foreach ($tenants as $tenant) {
                if (TurnstileConfigResolver::groupForHost($tenant->domain) === $wave) {
                    $activate[] = (int) $tenant->getKey();
                }
            }

            if ($activate === []) {
                $this->error("Kein Portal liegt in der Widget-Gruppe {$wave}.");

                return self::FAILURE;
            }
        }

        if ($this->option('activate-all')) {
            $activate = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->all();
        }

        if ($this->option('deactivate-all')) {
            $deactivate = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->all();
        }

        if ($mode !== null && $activate === [] && $deactivate === []) {
            $this->error('--mode wirkt nur zusammen mit --wave, --activate oder --deactivate.');

            return self::FAILURE;
        }

        // Scharfschalten ohne echte Widget-Schluessel laeuft in Produktion in
        // die TurnstileNotConfiguredException und nimmt jedem Portal der
        // Gruppe das Formular (#14). Herausnehmen bleibt immer moeglich.
        if ($activate !== [] && ! $this->schluesselTragen($activate, $tenants)) {
            return self::FAILURE;
        }

        if (($this->option('activate-all') || $this->option('deactivate-all')) && ! $this->confirmed($tenants->count())) {
            return self::FAILURE;
        }

        $rows = [];
        $changed = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $id = (int) $tenant->getKey();
            $setting = $this->settingFor($id);
            $before = $this->state($setting);
            $touched = in_array($id, $activate, true) || in_array($id, $deactivate, true);

            if (in_array($id, $activate, true)) {
                $setting->is_enabled = true;
            }

            // Herausnehmen schlaegt Scharfschalten: wer beides angibt, meint
            // die Notbremse.
            if (in_array($id, $deactivate, true)) {
                $setting->is_enabled = false;
            }

            if ($mode !== null && $touched) {
                $setting->actions_json = $this->withMode($setting, $mode);
            }

            // Ein Lauf ohne Schalter zeigt nur an und legt nichts an: eine
            // fehlende Zeile bleibt fehlend, bis sie wirklich gestellt wird.
            if ($touched && $setting->isDirty()) {
                $setting->save();
            }

            $after = $this->state($setting);
            $isChanged = $before !== $after;
            $changed += (int) $isChanged;

            $rows[] = [
                ($isChanged ? '* ' : '  ').($tenant->domain ?? $tenant->name),
                TurnstileConfigResolver::groupForHost($tenant->domain),
                $after['enabled'] ? 'ja' : 'nein',
                $after['registration'],
                $after['company_listing'],
                $after['fail_mode'],
                $setting->hasOwnSecret() ? 'eigenes Widget' : 'Gruppe',
            ];
        }

        $this->table(
            ['Domain', 'Gruppe', 'Aktiv', 'Registrierung', 'Firmeneintragung', 'Fail-Mode', 'Schluessel'],
            $rows,
        );

        if ($changed > 0) {
            $this->line(trans_choice('{1} Ein Portal geändert.|[2,*] :count Portale geändert.', $changed, ['count' => $changed]));
            $this->line('Der Resolver cacht je Request, eine Aenderung wirkt also ab der naechsten Anfrage.');
        }

        if ($changed === 0 && ($activate !== [] || $deactivate !== [])) {
            $this->line('Nichts geändert — die Portale standen schon so.');
        }

        return self::SUCCESS;
    }

    /** Gruppenbuchstabe zur Welle, `false` bei einer unbekannten Angabe. */
    private function waveGroup(): string|false|null
    {
        $wave = $this->option('wave');

        if ($wave === null || $wave === '') {
            return null;
        }

        $groups = array_keys(TurnstileConfigResolver::groups());

        if (! ctype_digit((string) $wave) || (int) $wave < 1 || (int) $wave > count($groups)) {
            $this->error('--wave muss eine ganze Zahl von 1 bis '.count($groups).' sein (Widget-Gruppen aus config/turnstile.php).');

            return false;
        }

        return (string) $groups[(int) $wave - 1];
    }

    /**
     * Prueft, ob jedes scharfzuschaltende Portal ein benutzbares
     * Schluesselpaar hat — eigenes Widget in der Tenant-Zeile oder ein echtes
     * Paar seiner Widget-Gruppe (#14, docs/turnstile.md §8).
     *
     * In Produktion ist ein fehlendes oder ein Testschluesselpaar ein Abbruch;
     * lokal und auf staging bleibt es eine Warnung, dort sind die
     * Testschluessel der Normalfall.
     *
     * @param  list<int>  $activate
     * @param  Collection<int, Tenant>  $tenants
     */
    private function schluesselTragen(array $activate, Collection $tenants): bool
    {
        /** @var array<string, list<string>> $fehlend */
        $fehlend = [];
        /** @var array<string, list<string>> $testkeys */
        $testkeys = [];

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            if (! in_array((int) $tenant->getKey(), $activate, true)) {
                continue;
            }

            // Ein Portal mit eigenem Widget haengt nicht an der Gruppe.
            if ($this->settingFor((int) $tenant->getKey())->hasOwnSecret()) {
                continue;
            }

            $gruppe = TurnstileConfigResolver::groupForHost($tenant->domain);
            $zustand = TurnstileConfigResolver::groupKeyState($gruppe);

            if ($zustand === TurnstileConfigResolver::KEYS_OK) {
                continue;
            }

            $domain = (string) ($tenant->domain ?? $tenant->name);

            if ($zustand === TurnstileConfigResolver::KEYS_MISSING) {
                $fehlend[$gruppe][] = $domain;

                continue;
            }

            $testkeys[$gruppe][] = $domain;
        }

        if ($fehlend === [] && $testkeys === []) {
            return true;
        }

        $sperre = app()->isProduction();

        foreach ($fehlend as $gruppe => $domains) {
            $this->meldung($sperre, "Gruppe {$gruppe} hat kein Schluesselpaar (TURNSTILE_SITE_KEY/_SECRET_KEY der Gruppe fehlt) — betrifft ".count($domains).' Portale: '.implode(', ', $domains));
        }

        foreach ($testkeys as $gruppe => $domains) {
            $this->meldung($sperre, "Gruppe {$gruppe} laeuft auf Cloudflare-Testschluesseln, die auf jeder Domain bestehen — betrifft ".count($domains).' Portale: '.implode(', ', $domains));
        }

        if (! $sperre) {
            return true;
        }

        $this->line('Erst die Widget-Schluessel eintragen (scripts/turnstile-schluessel-eintragen.sh), dann "php artisan turnstile:keys:check --siteverify" gruen sehen — danach die Welle stellen (#14).');

        return false;
    }

    private function meldung(bool $sperre, string $text): void
    {
        if ($sperre) {
            $this->error($text);

            return;
        }

        $this->warn($text);
    }

    /** `null` = nicht angegeben, `false` = unbekannter Wert. */
    private function mode(): TurnstileMode|false|null
    {
        $mode = $this->option('mode');

        if ($mode === null || $mode === '') {
            return null;
        }

        return TurnstileMode::tryFrom((string) $mode) ?? false;
    }

    private function confirmed(int $count): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $question = $this->option('activate-all')
            ? trans_choice('{1} Ein Portal scharfschalten?|[2,*] :count Portale scharfschalten?', $count, ['count' => $count])
            : trans_choice('{1} Ein Portal herausnehmen?|[2,*] :count Portale herausnehmen?', $count, ['count' => $count]);

        // Vorgabe nein; ohne Terminal (--no-interaction) gilt die Vorgabe.
        $answer = mb_strtolower(trim((string) $this->ask($question.' (ja/nein)', 'nein')));

        if (in_array($answer, ['ja', 'j'], true)) {
            return true;
        }

        $this->line('Abgebrochen, nichts geändert.');

        return false;
    }

    /**
     * Zeile des Portals; fehlt sie, wird sie mit den Vorgaben aus #3 angelegt.
     * Ohne Zeile gilt im Resolver "geschuetzt" — der Rollout braucht sie also,
     * um ein Portal ueberhaupt herausnehmen zu koennen.
     */
    private function settingFor(int $tenantId): TenantTurnstileSetting
    {
        $setting = TenantTurnstileSetting::query()->where('tenant_id', $tenantId)->first();

        if ($setting !== null) {
            return $setting;
        }

        return new TenantTurnstileSetting([
            'tenant_id' => $tenantId,
            'is_enabled' => true,
            'fail_mode' => ResolvedTurnstileConfig::FAIL_MODE_OPEN,
            'actions_json' => TenantTurnstileSetting::defaultActions(),
        ]);
    }

    /**
     * Modus der geschuetzten Aktionen austauschen, alles andere so lassen.
     *
     * @return array<string, array<string, mixed>>
     */
    private function withMode(TenantTurnstileSetting $setting, TurnstileMode $mode): array
    {
        $actions = $setting->actions_json ?? TenantTurnstileSetting::defaultActions();

        foreach (self::ROLLOUT_ACTIONS as $action) {
            $row = is_array($actions[$action->value] ?? null) ? $actions[$action->value] : [];
            $row['mode'] = $mode->value;
            $actions[$action->value] = $row;
        }

        return $actions;
    }

    /**
     * @return array{enabled: bool, registration: string, company_listing: string, fail_mode: string}
     */
    private function state(TenantTurnstileSetting $setting): array
    {
        $state = ['enabled' => (bool) $setting->is_enabled];

        foreach (self::ROLLOUT_ACTIONS as $action) {
            $row = $setting->actionSettings($action);
            $state[$action->value] = $row['enabled'] === false
                ? 'aus'
                : ($row['mode'] ?? $action->defaultMode())->value;
        }

        $state['fail_mode'] = $setting->fail_mode ?: (string) config('turnstile.fail_mode');

        return $state;
    }

    /**
     * @param  array<int, string>  $needles
     * @return list<int>|null null bei unbekannter Angabe
     */
    private function resolve(array $needles): ?array
    {
        $ids = [];

        foreach ($needles as $needle) {
            $needle = trim((string) $needle);

            $tenant = ctype_digit($needle)
                ? Tenant::query()->find((int) $needle)
                : Tenant::query()->where('domain', $needle)->orWhere('uuid', $needle)->first();

            if ($tenant === null) {
                $this->error("Kein Portal mit der Angabe {$needle} gefunden.");

                return null;
            }

            $ids[] = (int) $tenant->getKey();
        }

        return $ids;
    }
}
