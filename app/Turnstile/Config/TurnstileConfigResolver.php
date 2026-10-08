<?php

declare(strict_types=1);

namespace App\Turnstile\Config;

use App\Models\Tenant;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use App\Turnstile\Exceptions\TurnstileNotConfiguredException;
use App\Turnstile\Models\TenantTurnstileSetting;

/**
 * Loest die wirksame Turnstile-Konfiguration auf (#3, docs/turnstile.md §4).
 *
 * Rangfolge streng von oben, `null` faellt eine Stufe weiter:
 *
 *   1. tenant_turnstile_settings des aktuellen Portals
 *   2. config/turnstile.php
 *   3. Vorgabe im Code (TurnstileAction::defaultMode(), fail_mode open)
 *
 * Ohne Tenant-Kontext (zentrale Domain, Artisan, Filament-Admin) gilt
 * ausschliesslich config/turnstile.php.
 *
 * Gecacht wird je Request in einer statischen Map (Schluessel: Tenant + Aktion).
 * Ein Request betrifft genau ein Portal; nach einem Wechsel des Tenants oder
 * einer Aenderung im Admin raeumt {@see self::flush()} auf.
 */
class TurnstileConfigResolver
{
    /**
     * Cloudflare-Testschluessel. In Produktion gelten sie als "nicht
     * konfiguriert" — sie bestehen auf jeder Domain und schuetzen nichts.
     *
     * @var list<string>
     */
    public const TEST_SITE_KEYS = [
        '1x00000000000000000000AA',
        '2x00000000000000000000AB',
        '1x00000000000000000000BB',
        '2x00000000000000000000BB',
        '3x00000000000000000000FF',
    ];

    /**
     * @var list<string>
     */
    public const TEST_SECRET_KEYS = [
        '1x0000000000000000000000000000000AA',
        '2x0000000000000000000000000000000AA',
        '3x0000000000000000000000000000000AA',
    ];

    /** Gruppe hat ein vollstaendiges, echtes Schluesselpaar. */
    public const KEYS_OK = 'ok';

    /** Gruppe laeuft auf einem Cloudflare-Testschluessel. */
    public const KEYS_TEST = 'test';

    /** Gruppe hat kein vollstaendiges Schluesselpaar. */
    public const KEYS_MISSING = 'missing';

    /** @var array<string, ResolvedTurnstileConfig> */
    private static array $cache = [];

    /** @var array<int, TenantTurnstileSetting|null> */
    private static array $settings = [];

    /**
     * @throws TurnstileNotConfiguredException wenn in Produktion ein echter
     *                                         Secret fehlt und die Aktion aktiv ist
     */
    public static function for(TurnstileAction $action): ResolvedTurnstileConfig
    {
        $tenant = self::currentTenant();
        $tenantId = $tenant !== null ? (int) $tenant->getKey() : null;
        // Der Hostname gehoert in den Schluessel: er entscheidet die
        // Widget-Gruppe, und in Tests wechselt er innerhalb eines Prozesses.
        $cacheKey = ($tenantId ?? 0).':'.$action->value.':'.(self::currentHost() ?? '-');

        return self::$cache[$cacheKey] ??= self::resolve($action, $tenant, $tenantId);
    }

    /** Nach einer Aenderung im Admin (#9) oder einem Tenantwechsel in Tests. */
    public static function flush(): void
    {
        self::$cache = [];
        self::$settings = [];
    }

    private static function resolve(TurnstileAction $action, ?Tenant $tenant, ?int $tenantId): ResolvedTurnstileConfig
    {
        $setting = $tenant !== null ? self::settingFor($tenant) : null;

        $moduleEnabled = (bool) config('turnstile.enabled', true);
        $portalEnabled = $setting === null ? true : $setting->is_enabled;

        $actionConfig = config("turnstile.actions.{$action->value}", []);
        $actionConfig = is_array($actionConfig) ? $actionConfig : [];

        $tenantAction = $setting?->actionSettings($action) ?? ['enabled' => null, 'mode' => null];

        $actionEnabled = $tenantAction['enabled']
            ?? (bool) ($actionConfig['enabled'] ?? true);

        $mode = $tenantAction['mode']
            ?? (isset($actionConfig['mode']) && is_string($actionConfig['mode']) && $actionConfig['mode'] !== ''
                ? TurnstileMode::resolve($actionConfig['mode'])
                : $action->defaultMode());

        $enabled = $moduleEnabled && $portalEnabled && $actionEnabled;

        $failMode = self::normaliseFailMode(
            $setting?->fail_mode ?: config('turnstile.fail_mode')
        );

        // Welches Widget gilt hier? Erst die Gruppe zum Hostnamen der Anfrage
        // (#14), dann die .env-Vorgabe. Beides kann ein Portal mit eigenen
        // Schluesseln in seiner Zeile ueberschreiben.
        $group = self::groupForHost(self::currentHost());
        $groupKeys = self::groupKeys($group);

        $siteKey = self::firstFilled($setting?->site_key, $groupKeys['site_key']);
        $secretKey = self::firstFilled($setting?->secret_key, $groupKeys['secret_key']);

        // Portal mit eigenem Widget: nie einen halben Satz mischen. Hat es nur
        // einen der beiden Werte, gilt die Gruppe fuer beide.
        if ($setting !== null && (trim((string) $setting->site_key) === '' || ! $setting->hasOwnSecret())) {
            $siteKey = self::firstFilled($groupKeys['site_key']);
            $secretKey = self::firstFilled($groupKeys['secret_key']);
        }

        $usesTestKeys = in_array($secretKey, self::TEST_SECRET_KEYS, true)
            || in_array($siteKey, self::TEST_SITE_KEYS, true);

        if ($enabled && app()->environment('production')) {
            // Eigene Schluessel in der Zeile schlagen die Gruppe; fehlt der
            // Gruppe ihr Paar, darf NICHT auf Gruppe A zurueckgefallen werden:
            // deren Widget kennt diesen Hostnamen nicht, das Token wuerde erst
            // beim Hostname-Check scheitern und die Ursache waere verdeckt.
            if ($setting?->hasOwnSecret() !== true && $groupKeys['complete'] === false) {
                throw TurnstileNotConfiguredException::forAction(
                    $action,
                    $tenantId,
                    "Widget-Gruppe {$group} hat kein Schluesselpaar in der .env"
                );
            }

            if ($secretKey === '' || $siteKey === '') {
                throw TurnstileNotConfiguredException::forAction($action, $tenantId, 'Sitekey oder Secret fehlt');
            }

            if ($usesTestKeys) {
                throw TurnstileNotConfiguredException::forAction($action, $tenantId, 'es ist noch ein Cloudflare-Testschluessel hinterlegt');
            }
        }

        // local/staging: lieber mit Testschluesseln laufen als das Formular
        // blockieren — die Pruefung besteht dann immer.
        if ($siteKey === '' || $secretKey === '') {
            $siteKey = self::TEST_SITE_KEYS[0];
            $secretKey = self::TEST_SECRET_KEYS[0];
            $usesTestKeys = true;
        }

        return new ResolvedTurnstileConfig(
            action: $action,
            tenantId: $tenantId,
            enabled: $enabled,
            mode: $mode,
            siteKey: $siteKey,
            secretKey: $secretKey,
            failMode: $failMode,
            usesTestKeys: $usesTestKeys,
            widgetGroup: $group,
        );
    }

    /**
     * Gruppe zu einem Hostnamen (#14, docs/turnstile.md Abschnitt 8). Wie bei
     * Cloudflare deckt ein Eintrag seine Subdomains mit ab, deshalb wird der
     * Name von links Label fuer Label gekuerzt: `apotheke.firmenfreund.de`
     * findet `firmenfreund.de` (Gruppe B). Steht nichts in einer Liste, gilt die
     * Vorgabegruppe.
     */
    public static function groupForHost(?string $host): string
    {
        $default = self::defaultGroup();
        $host = mb_strtolower(trim((string) $host), 'UTF-8');

        if ($host === '') {
            return $default;
        }

        $labels = explode('.', $host);

        while (count($labels) > 1) {
            $kandidat = implode('.', $labels);

            foreach (self::groups() as $gruppe => $daten) {
                $hostnames = is_array($daten['hostnames'] ?? null) ? $daten['hostnames'] : [];

                foreach ($hostnames as $eintrag) {
                    if (mb_strtolower(trim((string) $eintrag), 'UTF-8') === $kandidat) {
                        return (string) $gruppe;
                    }
                }
            }

            array_shift($labels);
        }

        return $default;
    }

    /**
     * Schluesselpaar einer Gruppe. Unvollstaendig heisst: nicht benutzbar — dann
     * gilt die Vorgabegruppe (lokal die Testschluessel), in Produktion fliegt
     * stattdessen eine TurnstileNotConfiguredException.
     *
     * @return array{site_key: string, secret_key: string, complete: bool}
     */
    public static function groupKeys(string $group): array
    {
        $gruppen = self::groups();
        $eigen = is_array($gruppen[$group] ?? null) ? $gruppen[$group] : [];

        $siteKey = self::firstFilled($eigen['site_key'] ?? null);
        $secretKey = self::firstFilled($eigen['secret_key'] ?? null);

        if ($siteKey !== '' && $secretKey !== '') {
            return ['site_key' => $siteKey, 'secret_key' => $secretKey, 'complete' => true];
        }

        return [
            'site_key' => self::firstFilled(config('turnstile.site_key')),
            'secret_key' => self::firstFilled(config('turnstile.secret_key')),
            'complete' => false,
        ];
    }

    /**
     * Schluesselzustand einer Widget-Gruppe: `KEYS_OK`, `KEYS_TEST` (noch ein
     * Cloudflare-Testschluessel) oder `KEYS_MISSING` (Paar unvollstaendig).
     * Einzige Quelle fuer `turnstile:keys:check` und die Sperre in
     * `turnstile:rollout` (#14).
     */
    public static function groupKeyState(string $group): string
    {
        $gruppen = self::groups();
        $daten = is_array($gruppen[$group] ?? null) ? $gruppen[$group] : [];

        $siteKey = trim((string) ($daten['site_key'] ?? ''));
        $secretKey = trim((string) ($daten['secret_key'] ?? ''));

        if ($siteKey === '' || $secretKey === '') {
            return self::KEYS_MISSING;
        }

        if (in_array($siteKey, self::TEST_SITE_KEYS, true)
            || in_array($secretKey, self::TEST_SECRET_KEYS, true)) {
            return self::KEYS_TEST;
        }

        return self::KEYS_OK;
    }

    /** @return array<string, array<string, mixed>> */
    public static function groups(): array
    {
        $groups = config('turnstile.groups', []);

        return is_array($groups) ? $groups : [];
    }

    public static function defaultGroup(): string
    {
        $group = config('turnstile.default_group', 'A');

        return is_string($group) && $group !== '' ? $group : 'A';
    }

    /** Hostname der laufenden Anfrage; in der Konsole gibt es keinen. */
    private static function currentHost(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()?->getHost();
    }

    private static function settingFor(Tenant $tenant): ?TenantTurnstileSetting
    {
        $key = (int) $tenant->getKey();

        if (array_key_exists($key, self::$settings)) {
            return self::$settings[$key];
        }

        return self::$settings[$key] = TenantTurnstileSetting::query()
            ->where('tenant_id', $key)
            ->first();
    }

    private static function currentTenant(): ?Tenant
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /** Unbekannte Werte gelten als open — so steht es in config/turnstile.php. */
    private static function normaliseFailMode(mixed $value): string
    {
        return mb_strtolower(trim((string) $value)) === ResolvedTurnstileConfig::FAIL_MODE_CLOSED
            ? ResolvedTurnstileConfig::FAIL_MODE_CLOSED
            : ResolvedTurnstileConfig::FAIL_MODE_OPEN;
    }

    private static function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            $value = trim((string) (is_scalar($value) ? $value : ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
