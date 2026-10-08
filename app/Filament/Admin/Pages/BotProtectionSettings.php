<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\TurnstileMode;
use App\Turnstile\Models\TenantTurnstileSetting;
use App\Turnstile\Support\BotProtectionAccess;
use App\Turnstile\Support\SiteverifyProbe;
use App\Turnstile\Support\TurnstileAdminPortal;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Einstellungen › Bot-Schutz (#9, docs/turnstile.md §4): alle Werte aus
 * `tenant_turnstile_settings` des oben gewaehlten Portals.
 *
 * Eine Seite mit Formular und keine Resource: es gibt genau eine Zeile je
 * Portal, eine Liste mit Anlegen und Loeschen waere also nur eine Huelle um ein
 * Formular. Fehlt die Zeile noch, legt das Speichern sie mit den Vorgaben aus
 * {@see TenantTurnstileSetting::defaultActions()} an.
 *
 * Der Secret wird nie zurueck in das Formular gefuellt und nie angezeigt — das
 * Feld ist immer leer, daneben steht "gesetzt" oder "nicht gesetzt". Ein leeres
 * Feld beim Speichern behaelt den bisherigen Wert; entfernt wird er nur ueber
 * den eigenen Haken. Das Model haelt `secret_key` in `$hidden` und als
 * `encrypted`, er steht damit in keiner Ausgabe und in keiner Sicherung im
 * Klartext.
 *
 * Aenderungen greifen sofort: die Werte stehen in der Datenbank, der Resolver
 * cacht nur innerhalb einer Anfrage und wird hier zusaetzlich geleert
 * ({@see TurnstileConfigResolver::flush()}). Kein Deployment, kein Cache-Clear.
 *
 * @property-read Schema $form
 */
class BotProtectionSettings extends Page
{
    protected string $view = 'filament.admin.pages.bot-protection-settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'bot-protection';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bot-Schutz');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Bot-Schutz');
    }

    public function getSubheading(): ?string
    {
        return __('Cloudflare Turnstile je Portal. Änderungen wirken sofort.');
    }

    /**
     * Nur Betreiber: Administratoren mit dem Recht "update settings". Die
     * Ratgeber-Rollen (owner/editor) haben dieses Recht nicht und kommen schon
     * nicht in das Admin-Panel (App\Models\User::canAccessPanel()).
     */
    public static function canAccess(): bool
    {
        return BotProtectionAccess::allowed();
    }

    public function mount(): void
    {
        $this->fillFromTenant(TurnstileAdminPortal::currentId());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    // __('Portal') fände auf einem Dateisystem ohne Gross-/Kleinschreibung
                    // die Gruppe lang/de/portal.php und gäbe ein Array zurück.
                    ->label('Portal')
                    ->options(fn (): array => TurnstileAdminPortal::options())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (mixed $state): void {
                        $this->fillFromTenant($state === null ? null : (int) $state);
                    })
                    ->helperText(__('Die Einstellungen gelten nur für dieses Portal.')),

                Section::make(__('Schalter'))
                    ->description(__('Ohne diesen Schalter prüft keines der Formulare dieses Portals — unabhängig von den Aktionen weiter unten.'))
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label(__('Bot-Schutz für dieses Portal aktiv'))
                            ->helperText(__('Aus: alle Prüfungen dieses Portals werden übersprungen (skipped).'))
                            ->columnSpanFull(),
                        Select::make('fail_mode')
                            ->label(__('Verhalten bei Störungen'))
                            ->options([
                                ResolvedTurnstileConfig::FAIL_MODE_OPEN => __('offen — Anfrage läuft weiter, wenn Cloudflare nicht antwortet'),
                                ResolvedTurnstileConfig::FAIL_MODE_CLOSED => __('geschlossen — Anfrage wird abgewiesen, wenn Cloudflare nicht antwortet'),
                            ])
                            ->placeholder(__('Vorgabe aus der Serverkonfiguration (:mode)', ['mode' => (string) config('turnstile.fail_mode')]))
                            ->helperText(__('Leer: es gilt die Serverkonfiguration. "Geschlossen" legt bei einem Ausfall bei Cloudflare alle Formulare dieses Portals still.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Cloudflare-Widget'))
                    ->description(__('Regulär bleibt beides leer: dann gilt das Widget der Hostname-Gruppe aus der Serverkonfiguration. Eigene Schlüssel gelten nur als Paar — fehlt einer, greift für beide die Vorgabe.'))
                    ->schema([
                        TextInput::make('site_key')
                            ->label(__('Sitekey'))
                            ->maxLength(255)
                            ->placeholder($this->groupSiteKeyHint())
                            ->helperText(__('Öffentlich, steht im HTML der Seite.')),
                        TextInput::make('secret_key')
                            ->label(__('Secret'))
                            ->password()
                            ->revealable(false)
                            ->autocomplete(false)
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn (mixed $state): ?string => filled($state) ? trim((string) $state) : null)
                            ->helperText(fn (): string => __('Aktuell: :state. Leer lassen behält den bisherigen Wert; der Wert wird nie angezeigt.', [
                                'state' => $this->secretState(),
                            ])),
                        Checkbox::make('secret_clear')
                            ->label(__('Hinterlegtes Secret entfernen'))
                            ->helperText(__('Danach gilt wieder das Widget aus der Serverkonfiguration.'))
                            ->visible(fn (): bool => $this->setting()?->hasOwnSecret() === true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Geschützte Formulare'))
                    ->description(__('Je Aktion: ob geprüft wird, wie das Widget erscheint und welche Obergrenzen gelten.'))
                    ->schema($this->actionFieldsets()),

                Section::make(__('Eigene Sperrliste'))
                    ->description(__('Mail-Domains, die dieses Portal zusätzlich zur netzweiten Wegwerf-Sperrliste abweist.'))
                    ->schema([
                        TagsInput::make('blocklist_domains')
                            ->label(__('Gesperrte Mail-Domains'))
                            ->placeholder(__('z. B. mailinator.com'))
                            ->helperText(__('Eine Domain je Eintrag, ohne "@" und ohne "www.".'))
                            // Filament wertet Closures selbst aus; eine Regel-Closure muss
                            // deshalb von einer Closure zurueckgegeben werden.
                            ->nestedRecursiveRules([fn (): Closure => $this->domainRule()])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data')
            ->disabled(fn (): bool => $this->tenant() === null);
    }

    /**
     * @return array<Fieldset>
     */
    protected function actionFieldsets(): array
    {
        $fieldsets = [];

        foreach (TurnstileAction::cases() as $action) {
            $fieldsets[] = Fieldset::make($action->label())
                ->schema([
                    Toggle::make("actions.{$action->value}.enabled")
                        ->label(__('Prüfen'))
                        ->helperText(__('Aus: dieses Formular bleibt ungeschützt.')),
                    Select::make("actions.{$action->value}.mode")
                        ->label(__('Darstellung'))
                        ->options(TurnstileMode::options())
                        ->required()
                        ->helperText(__('Vorgabe dieser Aktion: :mode', ['mode' => $action->defaultMode()->label()])),
                    TextInput::make("limits.{$action->value}.per_ip_per_hour")
                        ->label(__('Versuche je IP und Stunde'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->placeholder(__('Vorgabe'))
                        ->helperText(__('Leer: netzweite Vorgabe.')),
                    TextInput::make("limits.{$action->value}.per_email_per_day")
                        ->label(__('Versuche je E-Mail und Tag'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->placeholder(__('Vorgabe'))
                        ->helperText(__('Leer: netzweite Vorgabe.')),
                ])
                ->columns(2);
        }

        return $fieldsets;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(__('Verbindung testen'))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->disabled(fn (): bool => $this->tenant() === null)
                ->action(fn () => $this->testConnection()),
        ];
    }

    /**
     * Schickt einen erfundenen Token an Siteverify. Erwartet wird
     * `success=false` mit `invalid-input-response`: dann ist Cloudflare
     * erreichbar und das Secret gilt. Geprueft wird der Schluessel, der fuer
     * dieses Portal wirksam waere — ein gerade eingetragener, sonst der
     * gespeicherte, sonst der der Hostname-Gruppe.
     */
    public function testConnection(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return;
        }

        [$secret, $herkunft] = $this->secretUnderTest($tenant);

        $result = SiteverifyProbe::check($secret);

        $notification = Notification::make()
            ->title($result->isOk() ? __('Verbindung in Ordnung') : __('Verbindung nicht in Ordnung'))
            ->body($result->message.' '.__('Geprüft: :herkunft.', ['herkunft' => $herkunft]));

        $result->isOk() ? $notification->success()->send() : $notification->danger()->persistent()->send();
    }

    public function save(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            Notification::make()->title(__('Bitte oben ein Portal auswählen.'))->warning()->send();

            return;
        }

        $data = $this->form->getState();
        $setting = $this->setting() ?? new TenantTurnstileSetting(['tenant_id' => $tenant->getKey()]);

        $setting->fill([
            'tenant_id' => $tenant->getKey(),
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'site_key' => filled($data['site_key'] ?? null) ? trim((string) $data['site_key']) : null,
            'fail_mode' => filled($data['fail_mode'] ?? null) ? (string) $data['fail_mode'] : null,
            'actions_json' => $this->actionsPayload($data),
            'blocklist_domains_json' => $this->blocklistPayload($data),
            'rate_limits_json' => $this->limitsPayload($data),
            'updated_by' => auth()->id(),
        ]);

        // Leeres Feld heisst "nicht anfassen"; entfernt wird nur ueber den Haken.
        if (filled($data['secret_key'] ?? null)) {
            $setting->secret_key = trim((string) $data['secret_key']);
        } elseif (($data['secret_clear'] ?? false) === true) {
            $setting->secret_key = null;
        }

        $setting->save();

        // Ohne Deployment wirksam: der Resolver cacht nur je Anfrage.
        TurnstileConfigResolver::flush();

        $this->fillFromTenant((int) $tenant->getKey());

        Notification::make()
            ->title(__('Bot-Schutz für :portal gespeichert.', ['portal' => $tenant->name]))
            ->success()
            ->send();
    }

    public function tenant(): ?Tenant
    {
        $id = $this->data['tenant_id'] ?? null;

        return filled($id) ? Tenant::query()->find((int) $id) : null;
    }

    /**
     * Werte aus der Serverkonfiguration, die fuer dieses Portal gelten —
     * schreibgeschuetzt, damit neben dem Formular steht, worauf ein leeres Feld
     * zurueckfaellt. Die Widget-Gruppe wird bewusst aus der Portal-Domain
     * bestimmt und nicht aus dem Host dieser Admin-Seite.
     *
     * @return list<array{label: string, value: string}>
     */
    public function configRows(): array
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return [];
        }

        $group = TurnstileConfigResolver::groupForHost((string) $tenant->domain);
        $keys = TurnstileConfigResolver::groupKeys($group);
        $gruppen = TurnstileConfigResolver::groups();
        $name = (string) ($gruppen[$group]['name'] ?? $group);

        return [
            [
                'label' => __('Modul'),
                'value' => config('turnstile.enabled') ? __('an') : __('AUS — kein Portal prüft'),
            ],
            [
                'label' => __('Widget-Gruppe dieser Domain'),
                'value' => "{$group} · {$name}",
            ],
            [
                'label' => __('Schlüsselpaar der Gruppe'),
                'value' => $keys['complete']
                    ? __('vollständig in der Serverkonfiguration')
                    : __('unvollständig — es gilt die .env-Vorgabe'),
            ],
            [
                'label' => __('Verhalten bei Störungen (Vorgabe)'),
                'value' => (string) config('turnstile.fail_mode'),
            ],
            [
                'label' => __('Hostname- und Action-Prüfung'),
                'value' => (config('turnstile.verify_hostname') ? __('Hostname an') : __('Hostname AUS'))
                    .' · '.(config('turnstile.verify_action') ? __('Action an') : __('Action AUS')),
            ],
            [
                'label' => __('Verifikations-Log'),
                'value' => config('turnstile.log.enabled')
                    ? __(':days Tage Aufbewahrung', ['days' => (int) config('turnstile.log.retention_days')])
                    : __('aus'),
            ],
        ];
    }

    private function setting(): ?TenantTurnstileSetting
    {
        $tenant = $this->tenant();

        return $tenant === null
            ? null
            : TenantTurnstileSetting::query()->where('tenant_id', $tenant->getKey())->first();
    }

    private function fillFromTenant(?int $tenantId): void
    {
        TurnstileAdminPortal::select($tenantId);

        $tenantId = TurnstileAdminPortal::currentId();

        if ($tenantId === null) {
            $this->form->fill(['tenant_id' => null]);

            return;
        }

        $setting = TenantTurnstileSetting::query()->where('tenant_id', $tenantId)->first();
        $actions = [];
        $limits = [];

        foreach (TurnstileAction::cases() as $action) {
            $gespeichert = $setting?->actionSettings($action) ?? ['enabled' => null, 'mode' => null];
            $vorgabe = TenantTurnstileSetting::defaultActions()[$action->value];

            $actions[$action->value] = [
                'enabled' => $gespeichert['enabled'] ?? $vorgabe['enabled'],
                'mode' => ($gespeichert['mode'] ?? $action->defaultMode())->value,
            ];

            $limits[$action->value] = $setting?->rateLimitsFor($action)
                ?? ['per_ip_per_hour' => null, 'per_email_per_day' => null];
        }

        $this->form->fill([
            'tenant_id' => $tenantId,
            'is_enabled' => $setting?->is_enabled ?? true,
            'site_key' => $setting?->site_key,
            // Der Secret wird nie zurueckgefuellt.
            'secret_key' => null,
            'secret_clear' => false,
            'fail_mode' => $setting?->fail_mode,
            'actions' => $actions,
            'limits' => $limits,
            'blocklist_domains' => $setting?->blocklistDomains() ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{enabled: bool, mode: string}>
     */
    private function actionsPayload(array $data): array
    {
        $payload = [];

        foreach (TurnstileAction::cases() as $action) {
            $row = $data['actions'][$action->value] ?? [];
            $row = is_array($row) ? $row : [];

            $payload[$action->value] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                // Ein unbekannter Wert faellt auf managed, also auf die
                // strengste Variante — ein Tippfehler darf kein Formular oeffnen.
                'mode' => TurnstileMode::resolve(is_string($row['mode'] ?? null) ? $row['mode'] : null)->value,
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, int>>|null
     */
    private function limitsPayload(array $data): ?array
    {
        $payload = [];

        foreach (TurnstileAction::cases() as $action) {
            $row = $data['limits'][$action->value] ?? [];
            $row = is_array($row) ? $row : [];

            $werte = [];

            foreach (['per_ip_per_hour', 'per_email_per_day'] as $key) {
                if (filled($row[$key] ?? null) && (int) $row[$key] > 0) {
                    $werte[$key] = (int) $row[$key];
                }
            }

            if ($werte !== []) {
                $payload[$action->value] = $werte;
            }
        }

        // Kein Eintrag heisst "ueberall die netzweite Vorgabe" — dann lieber
        // null als ein leeres Objekt in der Spalte.
        return $payload === [] ? null : $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    private function blocklistPayload(array $data): ?array
    {
        $domains = [];

        foreach ((array) ($data['blocklist_domains'] ?? []) as $domain) {
            $domain = TenantTurnstileSetting::normalizeDomain(is_scalar($domain) ? (string) $domain : '');

            if ($domain !== '' && ! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains === [] ? null : $domains;
    }

    /**
     * @return array{0: string, 1: string} Secret und woher er stammt
     */
    private function secretUnderTest(Tenant $tenant): array
    {
        $eingetragen = $this->data['secret_key'] ?? null;

        if (filled($eingetragen)) {
            return [trim((string) $eingetragen), __('der gerade eingetragene Schlüssel (noch nicht gespeichert)')];
        }

        $setting = $this->setting();

        if ($setting?->hasOwnSecret() === true) {
            return [(string) $setting->secret_key, __('der für dieses Portal gespeicherte Schlüssel')];
        }

        $group = TurnstileConfigResolver::groupForHost((string) $tenant->domain);

        return [
            TurnstileConfigResolver::groupKeys($group)['secret_key'],
            __('der Schlüssel der Widget-Gruppe :group aus der Serverkonfiguration', ['group' => $group]),
        ];
    }

    private function secretState(): string
    {
        return $this->setting()?->secretState() ?? __('nicht gesetzt');
    }

    private function groupSiteKeyHint(): string
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return '';
        }

        $group = TurnstileConfigResolver::groupForHost((string) $tenant->domain);

        return __('Vorgabe: Gruppe :group', ['group' => $group]);
    }

    private function domainRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $domain = TenantTurnstileSetting::normalizeDomain((string) $value);

            if (preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
                $fail(__('Keine gültige Domain, z. B. mailinator.com.'));
            }
        };
    }
}
