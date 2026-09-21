<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\TenantContentSetting;
use App\Content\Providers\SearchConsoleClient;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Search-Console-Property eines Portals: Form pruefen, Zugriff pruefen,
 * Zustand benennen (#116, Vorgabe zu #107).
 *
 * Die Klasse buendelt drei Dinge, die vorher nur auf der Kommandozeile
 * (`content:metrics:preflight`) oder gar nicht vorhanden waren:
 *
 * 1. die Formpruefung des Wertes, damit `sanitaer.test` gar nicht erst
 *    gespeichert wird — eine Testdomain laesst sich in der Search Console nie
 *    verifizieren, stellt den Go-Live-Check aber trotzdem auf Gruen;
 * 2. den Zugriffstest gegen sites.list plus eine Stichprobe, weil eine
 *    Property ohne Nutzereintrag des Dienstkontos nicht mit einem Fehler,
 *    sondern mit leeren Listen antwortet;
 * 3. die Uebersetzung des Befunds in genau sechs benannte Zustaende, die
 *    Panel und Konsole gleich lesen.
 */
final class SearchConsoleProperty
{
    public const STATE_MISSING = 'missing';

    public const STATE_UNCHECKED = 'unchecked';

    public const STATE_CONNECTED = 'connected';

    public const STATE_NO_DATA = 'no_data';

    public const STATE_NO_ACCESS = 'no_access';

    public const STATE_NO_SERVICE_ACCOUNT = 'no_service_account';

    /**
     * Endungen, die in der Search Console nie verifizierbar sind. Ein solcher
     * Wert ist kein Tippfehler, sondern eine Entwicklungsdomain — er blockiert
     * das Speichern.
     *
     * @var array<int, string>
     */
    private const RESERVED_SUFFIXES = ['.test', '.local', '.invalid', '.example', 'localhost'];

    /**
     * Reihenfolge fuer die Sammelansicht: schlechtester Zustand zuerst.
     *
     * @var array<string, int>
     */
    private const SEVERITY = [
        self::STATE_NO_ACCESS => 0,
        self::STATE_NO_SERVICE_ACCOUNT => 1,
        self::STATE_MISSING => 2,
        self::STATE_UNCHECKED => 3,
        self::STATE_NO_DATA => 4,
        self::STATE_CONNECTED => 5,
    ];

    public function __construct(private readonly SearchConsoleClient $client) {}

    /**
     * Formpruefung. Rueckgabe ist die Fehlermeldung im Klartext oder null,
     * wenn der Wert eine gueltige Property ist. Ein leerer Wert ist zulaessig:
     * ein Portal ohne Property liefert eben keine Metriken.
     */
    public function validate(?string $property): ?string
    {
        $value = trim((string) $property);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'sc-domain:')) {
            $host = substr($value, strlen('sc-domain:'));

            if (($reserved = $this->reservedSuffix($host)) !== null) {
                return $this->reservedMessage($reserved);
            }

            if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
                return __('Eine Domain-Property erwartet nur den Hostnamen, klein geschrieben: `sc-domain:beispiel.de` — ohne Schema, ohne Pfad, ohne Port.');
            }

            return null;
        }

        if (str_starts_with($value, 'https://')) {
            $host = (string) parse_url($value, PHP_URL_HOST);

            if (($reserved = $this->reservedSuffix($host)) !== null) {
                return $this->reservedMessage($reserved);
            }

            if ($host === '' || preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
                return __('Diese Adresse hat keinen brauchbaren Hostnamen. Erwartet wird `https://beispiel.de/`.');
            }

            if (! str_ends_with($value, '/')) {
                return __('URL-Präfix-Properties enden auf einem Schrägstrich: `https://beispiel.de/`.');
            }

            return null;
        }

        if (($reserved = $this->reservedSuffix($this->host($value))) !== null) {
            return $this->reservedMessage($reserved);
        }

        return __('Die Property braucht ein Präfix: `sc-domain:beispiel.de` für eine Domain-Property oder `https://beispiel.de/` für eine URL-Präfix-Property.');
    }

    /**
     * Warnung ohne Blockade: die Property gehoert nicht zur Domain des
     * Portals. Eine uebergeordnete Domain-Property ist ein legitimer
     * Sonderfall, deshalb nur ein Hinweis.
     */
    public function domainWarning(?string $property, ?string $portalDomain): ?string
    {
        $host = $this->host(trim((string) $property));
        $domain = mb_strtolower(trim((string) $portalDomain));

        if ($host === '' || $domain === '' || $this->validate($property) !== null) {
            return null;
        }

        $domain = (string) preg_replace('/^www\./', '', $domain);
        $host = (string) preg_replace('/^www\./', '', $host);

        if ($host === $domain || str_ends_with($domain, '.'.$host) || str_ends_with($host, '.'.$domain)) {
            return null;
        }

        return __('Diese Property gehört nicht zur Domain dieses Portals (:domain). Wenn das Absicht ist, kann der Wert so bleiben.', [
            'domain' => $portalDomain,
        ]);
    }

    /**
     * Zustand eines Portals aus den gespeicherten Werten — ohne Aufruf bei
     * Google, damit jede Panel-Seite ihn zeigen kann.
     *
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    public function state(?string $property, ?string $status, ?CarbonImmutable $checkedAt, ?string $detail = null): array
    {
        $key = $this->stateKey($property, $status);

        return $this->describe($key, $checkedAt, $detail);
    }

    /**
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    public function stateOf(Tenant $tenant): array
    {
        $values = $this->propertyValuesOf($tenant);

        return $this->state($values['property'], $values['status'], $values['checked_at'], $values['detail']);
    }

    /**
     * Zugriffstest fuer ein Portal — dieselbe Frage, die
     * `content:metrics:preflight` je Portal stellt: sieht das Dienstkonto die
     * Property, und liefert sie Zeilen? Ergebnis und Zeitpunkt landen in der
     * Tenant-Datenbank.
     *
     * Fehler des Aufrufs werden bewusst durchgereicht: der Klartext von
     * Google ist die einzige brauchbare Auskunft, ein generisches "Fehler"
     * waere wertlos.
     *
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    public function check(Tenant $tenant): array
    {
        $settings = $this->settingsOf($tenant);
        $property = trim((string) ($settings?->gsc_property ?? ''));

        if ($property === '') {
            return $this->describe(self::STATE_MISSING, null, null);
        }

        if (! $this->client->isConfigured()) {
            return $this->describe(self::STATE_NO_SERVICE_ACCOUNT, null, null);
        }

        $sites = $this->client->sites();

        if (! isset($sites[$property])) {
            return $this->store($tenant, self::STATE_NO_ACCESS, __('Nicht in der Liste der sichtbaren Properties.'));
        }

        $rows = $this->probe($property);

        if ($rows === []) {
            return $this->store($tenant, self::STATE_NO_DATA, (string) $sites[$property]);
        }

        $impressions = array_sum(array_map(static fn (array $row): int => (int) ($row['impressions'] ?? 0), $rows));

        return $this->store($tenant, self::STATE_CONNECTED, __(':count Impressionen in den Top-Seiten (:permission).', [
            'count' => $impressions,
            'permission' => $sites[$property],
        ]));
    }

    /**
     * Befund von aussen festhalten — der Preflight prueft alle Portale in
     * einem Durchgang mit einem einzigen sites.list-Aufruf und schreibt sein
     * Ergebnis hierher, damit das Panel denselben Stand zeigt wie die
     * Kommandozeile.
     */
    public function record(Tenant $tenant, string $key, ?string $detail = null): void
    {
        try {
            $this->store($tenant, $key, $detail);
        } catch (Throwable $exception) {
            Log::warning('Zugriffsstand der Property nicht schreibbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Zustand aller Portale fuer die Sammelansicht, schlechtester Zustand
     * zuerst. Sortiert wird stabil ueber SEVERITY, damit die offenen Portale
     * oben stehen und nicht zwischen zwanzig gruenen Zeilen verschwinden.
     *
     * @return array<int, array{tenant: Tenant, property: ?string, state: array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}}>
     */
    public function overview(): array
    {
        $rows = [];

        /** @var Tenant $tenant */
        foreach (Tenant::all() as $tenant) {
            $values = $this->propertyValuesOf($tenant);

            $rows[] = [
                'tenant' => $tenant,
                'property' => $values['property'],
                'state' => $this->state(
                    $values['property'],
                    $values['status'],
                    $values['checked_at'],
                    $values['detail'],
                ),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => (self::SEVERITY[$a['state']['key']] ?? 9) <=> (self::SEVERITY[$b['state']['key']] ?? 9)
            ?: strcmp((string) $a['tenant']->name, (string) $b['tenant']->name));

        return $rows;
    }

    /**
     * Adresse des Dienstkontos — der einzige Wert, den ein Mensch aus dem
     * Panel in die Google-Oberflaeche uebertragen muss.
     */
    public function serviceAccountEmail(): ?string
    {
        if (! $this->client->isConfigured()) {
            return null;
        }

        try {
            return $this->client->serviceAccountEmail();
        } catch (Throwable $exception) {
            Log::warning('Dienstkonto-Adresse nicht lesbar.', ['exception' => $exception->getMessage()]);

            return null;
        }
    }

    public function hasServiceAccount(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Stichprobe ueber dasselbe Fenster, das der Gap-Connector liest.
     *
     * @return array<int, array<string, mixed>>
     */
    private function probe(string $property): array
    {
        $lag = (int) config('content.providers.search_console.lag_days', 3);
        $lookback = (int) config('content.providers.search_console.lookback_days', 28);
        $end = CarbonImmutable::today()->subDays($lag);

        return $this->client->query($property, [
            'startDate' => $end->subDays($lookback)->toDateString(),
            'endDate' => $end->toDateString(),
            'dimensions' => ['page'],
            'rowLimit' => 5,
            'dataState' => (string) config('content.providers.search_console.data_state', 'final'),
            'type' => 'web',
        ]);
    }

    /**
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    private function store(Tenant $tenant, string $key, ?string $detail): array
    {
        $now = CarbonImmutable::now();

        $tenant->run(static function () use ($key, $detail, $now): void {
            TenantContentSetting::current()->forceFill([
                'gsc_check_status' => $key,
                'gsc_checked_at' => $now,
                'gsc_check_detail' => $detail === null ? null : mb_substr($detail, 0, 500),
            ])->save();
        });

        return $this->describe($key, $now, $detail);
    }

    private function stateKey(?string $property, ?string $status): string
    {
        if (trim((string) $property) === '') {
            return self::STATE_MISSING;
        }

        if (! $this->client->isConfigured()) {
            return self::STATE_NO_SERVICE_ACCOUNT;
        }

        return match ($status) {
            self::STATE_CONNECTED, self::STATE_NO_DATA, self::STATE_NO_ACCESS => $status,
            default => self::STATE_UNCHECKED,
        };
    }

    /**
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    private function describe(string $key, ?CarbonImmutable $checkedAt, ?string $detail): array
    {
        $stamp = $checkedAt?->timezone(config('app.timezone'))->format('d.m.Y, H:i');

        [$label, $text, $class] = match ($key) {
            self::STATE_CONNECTED => [
                __('Verbunden'),
                __('Zuletzt geprüft: :stamp. Leserecht bestätigt.', ['stamp' => $stamp]),
                'published',
            ],
            self::STATE_NO_DATA => [
                __('Ohne Daten'),
                __('Leserecht bestätigt, für die letzten 28 Tage liegen noch keine Zeilen vor.'),
                'review',
            ],
            self::STATE_NO_ACCESS => [
                __('Kein Zugriff'),
                __('Das Dienstkonto ist in dieser Property nicht als Nutzer eingetragen. Die API antwortet dann leer statt mit einem Fehler.'),
                'failed',
            ],
            self::STATE_NO_SERVICE_ACCOUNT => [
                __('Dienstkonto fehlt'),
                __('Ohne GOOGLE_SERVICE_ACCOUNT_JSON kann nichts geprüft werden.'),
                'failed',
            ],
            self::STATE_MISSING => [
                __('Nicht eingerichtet'),
                __('Dieses Portal liefert keine Metriken.'),
                'idea',
            ],
            default => [
                __('Ungeprüft'),
                __('Zuletzt geprüft: nie. Jetzt prüfen.'),
                'scheduled',
            ],
        };

        return [
            'key' => $key,
            'label' => $label,
            'text' => $text,
            'class' => $class,
            'checked_at' => $checkedAt,
            'detail' => $detail,
        ];
    }

    private function reservedSuffix(string $host): ?string
    {
        $host = mb_strtolower(rtrim($host, '.'));

        foreach (self::RESERVED_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return $suffix;
            }
        }

        return null;
    }

    private function reservedMessage(string $suffix): string
    {
        if ($suffix === 'localhost') {
            return __('localhost lässt sich in der Search Console nicht verifizieren. Hier gehört die öffentliche Produktionsdomain des Portals hin.');
        }

        return __(':suffix-Domains lassen sich in der Search Console nicht verifizieren. Hier gehört die öffentliche Produktionsdomain des Portals hin.', [
            'suffix' => $suffix,
        ]);
    }

    /**
     * Hostname einer Property, egal in welcher der beiden Formen.
     */
    private function host(string $property): string
    {
        if (str_starts_with($property, 'sc-domain:')) {
            return mb_strtolower(substr($property, strlen('sc-domain:')));
        }

        if (str_contains($property, '://')) {
            return mb_strtolower((string) parse_url($property, PHP_URL_HOST));
        }

        return mb_strtolower(explode('/', $property)[0]);
    }

    /**
     * Die Property-Felder, noch im Tenant-Kontext gelesen. Ausserhalb davon
     * braucht der Datums-Cast die Verbindung 'tenant', die es dann nicht mehr
     * gibt (content:rollout brach daran ab, #42).
     *
     * @return array{property: ?string, status: ?string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    private function propertyValuesOf(Tenant $tenant): array
    {
        $empty = ['property' => null, 'status' => null, 'checked_at' => null, 'detail' => null];

        try {
            return $tenant->run(static function () use ($empty): array {
                $settings = TenantContentSetting::query()->first();

                if ($settings === null) {
                    return $empty;
                }

                return [
                    'property' => $settings->gsc_property,
                    'status' => $settings->gsc_check_status,
                    'checked_at' => $settings->gsc_checked_at !== null ? CarbonImmutable::parse($settings->gsc_checked_at) : null,
                    'detail' => $settings->gsc_check_detail,
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Einstellungen des Portals nicht lesbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }

    private function settingsOf(Tenant $tenant): ?TenantContentSetting
    {
        try {
            return $tenant->run(static fn (): ?TenantContentSetting => TenantContentSetting::query()->first());
        } catch (Throwable $exception) {
            Log::warning('Einstellungen des Portals nicht lesbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
