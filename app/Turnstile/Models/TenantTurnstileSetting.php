<?php

declare(strict_types=1);

namespace App\Turnstile\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Turnstile-Einstellungen eines Portals (#3). Genau eine Zeile je Tenant,
 * central gespeichert. Gelesen wird ausschliesslich ueber
 * App\Turnstile\Config\TurnstileConfigResolver, geschrieben im Admin (#9).
 *
 * Der Secret liegt verschluesselt (`encrypted` cast) und darf nie geloggt oder
 * in einer Filament-Tabelle gezeigt werden — dafuer gibt es
 * {@see self::hasOwnSecret()} mit der Anzeige "gesetzt / nicht gesetzt".
 *
 * @property int $tenant_id
 * @property bool $is_enabled
 * @property string|null $site_key
 * @property string|null $secret_key
 * @property string|null $fail_mode
 * @property array<string, array<string, mixed>>|null $actions_json
 * @property list<string>|null $blocklist_domains_json
 * @property array<string, array<string, mixed>>|null $rate_limits_json
 * @property int|null $updated_by
 */
class TenantTurnstileSetting extends Model
{
    use CentralConnection;

    protected $table = 'tenant_turnstile_settings';

    protected $fillable = [
        'tenant_id',
        'is_enabled',
        'site_key',
        'secret_key',
        'fail_mode',
        'actions_json',
        'blocklist_domains_json',
        'rate_limits_json',
        'updated_by',
    ];

    /**
     * Nie in Logs, Telescope-Eintraegen oder JSON-Ausgaben.
     *
     * @var list<string>
     */
    protected $hidden = [
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'secret_key' => 'encrypted',
            'actions_json' => 'array',
            'blocklist_domains_json' => 'array',
            'rate_limits_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Vorgabe fuer eine neue Zeile (#3, auch vom Seeder benutzt): Konto und
     * Firmeneintragung geschuetzt, Anfrage und Kontakt zunaechst offen.
     *
     * @return array<string, array{enabled: bool, mode: string}>
     */
    public static function defaultActions(): array
    {
        $defaults = [];

        foreach (TurnstileAction::cases() as $action) {
            $defaults[$action->value] = [
                'enabled' => in_array($action, [TurnstileAction::Registration, TurnstileAction::CompanyListing], true),
                'mode' => $action->defaultMode()->value,
            ];
        }

        return $defaults;
    }

    /** Hat das Portal ein eigenes Widget, oder gilt die .env-Vorgabe? */
    public function hasOwnSecret(): bool
    {
        return trim((string) $this->secret_key) !== '';
    }

    /** Anzeige fuer Filament (#9) — nie der Wert selbst. */
    public function secretState(): string
    {
        return $this->hasOwnSecret() ? __('gesetzt') : __('nicht gesetzt');
    }

    /**
     * Mail-Domains, die dieses Portal zusaetzlich zur netzweiten
     * Wegwerf-Sperrliste abweist (#8 liest das, #9 pflegt es). Immer
     * kleingeschrieben und ohne `www.`, damit der Vergleich in #8 ohne weitere
     * Normalisierung auskommt.
     *
     * @return list<string>
     */
    public function blocklistDomains(): array
    {
        $domains = [];

        foreach ((array) ($this->blocklist_domains_json ?? []) as $domain) {
            $domain = self::normalizeDomain(is_scalar($domain) ? (string) $domain : '');

            if ($domain !== '' && ! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Obergrenzen dieser Aktion. `null` heisst "nicht gesetzt" und faellt in #8
     * auf die netzweite Vorgabe zurueck — wie ueberall in diesem Modul gibt es
     * keinen Zustand "halb konfiguriert".
     *
     * @return array{per_ip_per_hour: int|null, per_email_per_day: int|null}
     */
    public function rateLimitsFor(TurnstileAction $action): array
    {
        $row = $this->rate_limits_json[$action->value] ?? [];

        if (! is_array($row)) {
            $row = [];
        }

        return [
            'per_ip_per_hour' => self::positiveIntOrNull($row['per_ip_per_hour'] ?? null),
            'per_email_per_day' => self::positiveIntOrNull($row['per_email_per_day'] ?? null),
        ];
    }

    /** Ohne Schema, ohne Pfad, ohne `www.` — wie im Ratgebermodul. */
    public static function normalizeDomain(string $domain): string
    {
        $domain = mb_strtolower(trim($domain), 'UTF-8');
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        return (string) preg_replace('/^www\./', '', rtrim($domain, '.'));
    }

    /** 0 und Unsinn gelten als "nicht gesetzt", nicht als "alles gesperrt". */
    private static function positiveIntOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    /**
     * Rohwerte einer Aktion, ohne Rangfolge. `null` heisst "nicht gesetzt" und
     * faellt im Resolver eine Stufe weiter.
     *
     * @return array{enabled: bool|null, mode: TurnstileMode|null}
     */
    public function actionSettings(TurnstileAction $action): array
    {
        $row = $this->actions_json[$action->value] ?? [];

        if (! is_array($row)) {
            $row = [];
        }

        $enabled = $row['enabled'] ?? null;
        $mode = is_string($row['mode'] ?? null) && $row['mode'] !== ''
            ? TurnstileMode::resolve($row['mode'])
            : null;

        return [
            'enabled' => $enabled === null ? null : (bool) $enabled,
            'mode' => $mode,
        ];
    }
}
