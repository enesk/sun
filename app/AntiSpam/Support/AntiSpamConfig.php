<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\Models\Tenant;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Models\TenantTurnstileSetting;
use Throwable;

/**
 * Wirksame Einstellung des Pakets (#8): config/antispam.php, darueber die
 * Portal-Werte aus tenant_turnstile_settings.
 *
 * Rangfolge wie bei Turnstile (docs/turnstile.md §4):
 * Tenant > config/antispam.php > Vorgabe im Aufruf.
 *
 * Die Portal-Werte stehen NICHT in tenants.data, sondern in derselben
 * zentralen Tabelle wie die uebrigen Turnstile-Einstellungen — angelegt in #3,
 * ergaenzt um `blocklist_domains_json` und `rate_limits_json` fuer dieses
 * Ticket, gepflegt im Admin (#9). Eine Quelle, ein Formular, eine Abfrage
 * ueber alle Portale.
 *
 * Uebersetzt wird dabei von der Sprache des Admins in die dieses Pakets:
 *
 *   rate_limits_json[registration].per_ip_per_hour   -> limits.registration.ip.max
 *   rate_limits_json[registration].per_email_per_day -> limits.registration.email.max
 *   rate_limits_json[company_listing].per_ip_per_hour-> limits.company_listing.ip.max
 *   blocklist_domains_json                           -> disposable_domains
 *
 * Das Login-Limit hat keine Entsprechung im Admin: zu TurnstileAction gibt es
 * keine Aktion `login`. Es gilt deshalb netzweit aus config/antispam.php.
 *
 * `null` oder ein fehlender Schluessel heisst immer "Vorgabe", nie "aus" —
 * es gibt keinen Zustand "halb konfiguriert".
 *
 * Gemischt wird rekursiv, aber nur fuer assoziative Arrays. Eine Liste
 * (Domains, Feldnamen) wird NICHT ersetzt, sondern angehaengt — sonst muesste
 * ein Portal die ganze Sperrliste wiederholen, nur um eine Domain zu
 * ergaenzen. Herausnehmen geht ueber `antispam.allowed_domains`.
 *
 * Gecacht wird je Request und je Portal in einer statischen Map; sie faellt
 * beim Portalwechsel, siehe App\Turnstile\TurnstileServiceProvider::boot().
 */
class AntiSpamConfig
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /** Globaler Notschalter, Portal-Schalter eingerechnet. */
    public static function enabled(): bool
    {
        return (bool) self::get('enabled', true);
    }

    /**
     * Ein Wert in Punktschreibweise, z.B. `timing.min_seconds`.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = data_get(self::all(), $key);

        return $value === null ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = self::get($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    /**
     * @return list<string>
     */
    public static function list(string $key): array
    {
        $value = self::get($key, []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $entry): string => is_scalar($entry) ? trim((string) $entry) : '',
            $value,
        ), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Gesamte wirksame Konfiguration.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $tenant = self::currentTenant();
        $cacheKey = $tenant !== null ? (string) $tenant->getKey() : 'central';

        return self::$cache[$cacheKey] ??= self::merge(
            is_array(config('antispam')) ? config('antispam') : [],
            self::tenantOverrides($tenant),
        );
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Portal-Werte aus tenant_turnstile_settings, uebersetzt in die Form von
     * config/antispam.php. Ein nicht gesetzter Wert taucht gar nicht erst im
     * Ergebnis auf und faellt damit auf die Vorgabe zurueck.
     *
     * Faellt die Abfrage aus (Tabelle noch nicht migriert, zentrale Verbindung
     * weg), gilt die Vorgabe: die Schicht soll laufen, nicht blockieren.
     *
     * @return array<string, mixed>
     */
    private static function tenantOverrides(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        try {
            $setting = TenantTurnstileSetting::query()->where('tenant_id', (int) $tenant->getKey())->first();
        } catch (Throwable) {
            return [];
        }

        if ($setting === null) {
            return [];
        }

        $overrides = [];

        $domains = $setting->blocklistDomains();

        if ($domains !== []) {
            $overrides['disposable_domains'] = $domains;
        }

        $limits = [];

        $registration = $setting->rateLimitsFor(TurnstileAction::Registration);

        if ($registration['per_ip_per_hour'] !== null) {
            $limits['registration']['ip'] = ['max' => $registration['per_ip_per_hour'], 'minutes' => 60];
        }

        if ($registration['per_email_per_day'] !== null) {
            $limits['registration']['email'] = ['max' => $registration['per_email_per_day'], 'minutes' => 1440];
        }

        $listing = $setting->rateLimitsFor(TurnstileAction::CompanyListing);

        if ($listing['per_ip_per_hour'] !== null) {
            $limits['company_listing']['ip'] = ['max' => $listing['per_ip_per_hour'], 'minutes' => 60];
        }

        if ($limits !== []) {
            $overrides['limits'] = $limits;
        }

        return $overrides;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $vorhanden = $base[$key] ?? null;

            if (is_array($vorhanden) && is_array($value)) {
                $base[$key] = array_is_list($vorhanden) && array_is_list($value)
                    ? array_values(array_unique([...$vorhanden, ...$value]))
                    : self::merge($vorhanden, $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private static function currentTenant(): ?Tenant
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
