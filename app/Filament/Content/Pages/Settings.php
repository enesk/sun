<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Models\TenantContentSetting;
use App\Content\Services\SearchConsoleProperty;
use App\Guide\Services\ContentTenantContext;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Einstellungen eines Portals (#20), design/content-dashboard.md, §7.
 *
 * Seit dem Rueckbau der alten Pipeline (#23) nur noch die Felder aus
 * `tenant_content_settings`, die Frontend und Ratgebersystem lesen:
 * Schwerpunktregion, Autor/Organisation (PortalProfileService) und die
 * Search-Console-Property (Leistungsdaten). Tageslauf, Budget und Prompts
 * des Ratgebersystems stehen unter "Einstellungen" (#16).
 * Die Zeile liegt in der Tenant-Datenbank: gespeichert wird immer fuer das
 * oben gewaehlte Portal. Steht die Auswahl auf "Alle Portale", gibt es
 * nichts zu speichern — ein Formular, das 24 Portale gleichzeitig
 * ueberschreibt, waere ein Unfall mit Ansage.
 *
 * Zugangsdaten stehen hier bewusst nicht: sie kommen aus .env
 * (config/content.php). Der Budget-Abschnitt zeigt die geltenden Werte samt
 * ihrer Herkunft und ist gesperrt — ein Feld, das aussieht wie aenderbar und
 * beim Speichern nichts tut, ist schlimmer als ein gesperrtes Feld.
 *
 * Nur fuer die Rolle `owner`.
 */
class Settings extends ContentPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'alt/einstellungen';

    // Bis #16 die einzige Pflege von Autor, Kategorie-Zuordnung und Search
    // Console; erreichbar ueber "Heute" bzw. den Tageslauf-Monitor (#23).
    protected static bool $isLegacyPipelinePage = true;

    protected static string $followUpTicket = '#20';

    protected string $view = 'filament.content.pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $gscOverview = null;

    public static function getNavigationLabel(): string
    {
        return __('Einstellungen');
    }

    public function getTitle(): string
    {
        return __('Einstellungen');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    public function mount(): void
    {
        $settings = $this->settings();

        $this->form->fill($settings === null ? [] : [
            ...$settings->only([
                'preferred_states_json',
                'author_name',
                'author_bio',
                'gsc_property',
            ]),
            'organization_same_as_json' => $this->linesFrom($settings->organization_same_as_json),
            'author_same_as_json' => $this->linesFrom($settings->author_same_as_json),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Region'))
                    ->schema([
                        Select::make('preferred_states_json')
                            ->label(__('Bevorzugte Bundesländer'))
                            ->multiple()
                            ->options($this->stateOptions())
                            ->native(false)
                            ->helperText(__('Schwerpunktregion des Portals. Leer bedeutet: bundesweit.')),
                    ]),

                Section::make(__('Autor und Organisation'))
                    ->description(__('Erscheint in der Autorenbox, im Autorenprofil und im JSON-LD der Artikel.'))
                    ->schema([
                        TextInput::make('author_name')
                            ->label(__('Autorname'))
                            ->maxLength(255),
                        Textarea::make('author_bio')
                            ->label(__('Kurzbiografie'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('author_same_as_json')
                            ->label(__('sameAs des Autors'))
                            ->rows(3)
                            ->helperText(__('Eine Adresse je Zeile.')),
                        Textarea::make('organization_same_as_json')
                            ->label(__('sameAs der Organisation'))
                            ->rows(3)
                            ->helperText(__('Eine Adresse je Zeile.')),
                    ])
                    ->columns(2),

                // Messung steht bewusst als letzte Sektion (#116): die Angabe
                // wird einmal gesetzt und danach selten angefasst, sie darf
                // die taeglich benutzten Abschnitte nicht nach unten druecken.
                Section::make(__('Messung (Search Console)'))
                    ->description(__('Woher dieses Portal seine Klick- und Impressionsdaten bezieht. Ohne Property bleiben Performance-Ansicht und Content-Lücken-Analyse leer.'))
                    ->schema([
                        View::make('content.partials.gsc-state')
                            ->viewData(['page' => $this])
                            ->columnSpanFull(),
                        TextInput::make('gsc_property')
                            ->label(__('Search-Console-Property'))
                            ->maxLength(255)
                            ->placeholder('sc-domain:beispiel.de')
                            // Live beim Verlassen des Feldes: der Fehler soll
                            // vor dem Speichern stehen, nicht danach.
                            ->live(onBlur: true)
                            ->rule(static function (): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail): void {
                                    $message = app(SearchConsoleProperty::class)->validate(is_string($value) ? $value : null);

                                    if ($message !== null) {
                                        $fail($message);
                                    }
                                };
                            })
                            ->helperText(__('Domain-Property bevorzugt: sc-domain:beispiel.de. URL-Präfix-Property: https://beispiel.de/ mit abschließendem Schrägstrich.'))
                            ->columnSpanFull(),
                        View::make('content.partials.gsc-service-account')
                            ->viewData(['page' => $this])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data')
            ->disabled(fn (): bool => $this->settings() === null);
    }

    public function save(): void
    {
        $settings = $this->settings();

        if ($settings === null) {
            Notification::make()
                ->title(__('Bitte oben ein Portal auswählen.'))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $tenant = app(ContentTenantContext::class)->selected();

        try {
            $tenant?->run(function () use ($data): void {
                $settings = TenantContentSetting::current();
                $property = trim((string) ($data['gsc_property'] ?? '')) ?: null;

                $settings->forceFill([
                    'preferred_states_json' => array_values((array) ($data['preferred_states_json'] ?? [])),
                    'author_name' => $data['author_name'] ?: null,
                    'author_bio' => $data['author_bio'] ?: null,
                    'author_same_as_json' => $this->linesTo($data['author_same_as_json'] ?? null),
                    'organization_same_as_json' => $this->linesTo($data['organization_same_as_json'] ?? null),
                    'gsc_property' => $property,
                    // Ein neuer Wert erbt den Befund des alten nicht: sonst
                    // stuende "Verbunden" an einer Property, die nie geprueft
                    // wurde (#116).
                    'gsc_check_status' => $property === $settings->gsc_property ? $settings->gsc_check_status : null,
                    'gsc_checked_at' => $property === $settings->gsc_property ? $settings->gsc_checked_at : null,
                    'gsc_check_detail' => $property === $settings->gsc_property ? $settings->gsc_check_detail : null,
                ])->save();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title(__('Einstellungen für :portal gespeichert.', ['portal' => $tenant?->name]))
            ->success()
            ->send();
    }

    /**
     * Zustand der Search-Console-Property dieses Portals (#116).
     *
     * Gelesen wird nur, was gespeichert ist — der Aufruf bei Google steckt in
     * checkGscAccess(). Eine Panel-Seite, die bei jedem Aufbau bei Google
     * nachfragt, waere langsam und wuerde das Kontingent verbrauchen.
     *
     * @return array{key: string, label: string, text: string, class: string, checked_at: ?CarbonImmutable, detail: ?string}
     */
    public function gscState(): array
    {
        $service = app(SearchConsoleProperty::class);
        $tenant = app(ContentTenantContext::class)->selected();

        if ($tenant === null) {
            return $service->state(null, null, null);
        }

        // Der Wert im Formular gilt vor dem gespeicherten: sonst zeigt die
        // Zeile "Verbunden", waehrend im Feld schon etwas anderes steht.
        $settings = $this->settings();
        $typed = trim((string) ($this->data['gsc_property'] ?? ''));
        $stored = trim((string) ($settings?->gsc_property ?? ''));

        if ($typed !== $stored) {
            return $service->state($typed === '' ? null : $typed, null, null);
        }

        return $service->state(
            $stored === '' ? null : $stored,
            $settings?->gsc_check_status,
            $settings?->gsc_checked_at === null ? null : CarbonImmutable::parse($settings->gsc_checked_at),
            $settings?->gsc_check_detail,
        );
    }

    /**
     * Warnung ohne Blockade: Property und Portal-Domain passen nicht
     * zusammen. Eine uebergeordnete Domain-Property ist erlaubt.
     */
    public function gscDomainWarning(): ?string
    {
        $tenant = app(ContentTenantContext::class)->selected();

        return app(SearchConsoleProperty::class)->domainWarning(
            $this->data['gsc_property'] ?? null,
            $tenant?->domain === null ? null : (string) $tenant->domain,
        );
    }

    /**
     * Formfehler des aktuell eingetragenen Wertes. Filament prueft erst beim
     * Speichern und beim Verlassen des Feldes — ein schon gespeicherter
     * unbrauchbarer Wert (etwa aus der Zeit vor #116) bliebe sonst stumm.
     */
    public function gscPropertyError(): ?string
    {
        return app(SearchConsoleProperty::class)->validate($this->data['gsc_property'] ?? null);
    }

    public function gscServiceAccountEmail(): ?string
    {
        return app(SearchConsoleProperty::class)->serviceAccountEmail();
    }

    /**
     * Warum "Zugriff pruefen" gesperrt ist — als Hilfetext, nicht nur als
     * Tooltip. Null heisst: die Schaltflaeche ist benutzbar.
     */
    public function gscCheckBlockedReason(): ?string
    {
        if (! $this->hasPortal()) {
            return __('Bitte oben ein Portal auswählen.');
        }

        if (trim((string) ($this->data['gsc_property'] ?? '')) === '') {
            return __('Erst eine Property eintragen und speichern, dann lässt sich der Zugriff prüfen.');
        }

        if (trim((string) ($this->data['gsc_property'] ?? '')) !== trim((string) ($this->settings()?->gsc_property ?? ''))) {
            return __('Erst speichern: geprüft wird der gespeicherte Wert.');
        }

        if (! app(SearchConsoleProperty::class)->hasServiceAccount()) {
            return __('Ohne GOOGLE_SERVICE_ACCOUNT_JSON kann nichts geprüft werden.');
        }

        return null;
    }

    /**
     * Zugriffstest fuer das gewaehlte Portal: derselbe Weg, den
     * content:metrics:preflight je Portal geht.
     */
    public function checkGscAccess(): void
    {
        $blocked = $this->gscCheckBlockedReason();

        if ($blocked !== null) {
            Notification::make()->title($blocked)->warning()->send();

            return;
        }

        $tenant = app(ContentTenantContext::class)->selected();

        try {
            $state = app(SearchConsoleProperty::class)->check($tenant);
        } catch (Throwable $exception) {
            // Klartext von Google statt eines generischen "Fehler": nur der
            // sagt, ob die API im Cloud-Projekt aus ist oder der Schluessel
            // abgelehnt wurde.
            Notification::make()->title(__('Prüfung fehlgeschlagen'))->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($state['label'])
            ->body($state['text'])
            ->status($state['key'] === SearchConsoleProperty::STATE_CONNECTED ? 'success' : 'warning')
            ->send();
    }

    /**
     * Sammelansicht aller Portale, schlechtester Zustand zuerst (#116, §3).
     *
     * @return array<int, array{tenant: \App\Models\Tenant, property: ?string, state: array<string, mixed>}>
     */
    public function getGscOverviewRows(): array
    {
        // Einmal je Anfrage: die Uebersicht oeffnet je Portal die
        // Tenant-Datenbank, und Livewire rendert bei jeder Eingabe neu.
        return $this->gscOverview ??= app(SearchConsoleProperty::class)->overview();
    }

    /**
     * Budgetwerte aus der Serverkonfiguration. Sie stehen hier als Anzeige,
     * nicht als Formular — geaendert werden sie in .env.
     *
     * Der BudgetGuard behandelt eine 0 als "Pruefung aus"
     * (BudgetGuard::assertBelow kehrt bei $limit <= 0 ohne Pruefung zurueck).
     * Die Zeile darf deshalb bei 0 keinen Geldbetrag zeigen: "0,00 $" liest
     * sich als "es wird nichts ausgegeben", gemeint ist das Gegenteil.
     *
     * @return array<int, array{label: string, value: ?string, unlimited: bool, source: string}>
     */
    public function getBudgetRows(): array
    {
        $money = static function (string $key, float $default): array {
            $amount = (float) config("content.budget.{$key}", $default);

            return [
                'value' => $amount > 0.0 ? number_format($amount, 2, ',', '.').' $' : null,
                'unlimited' => $amount <= 0.0,
            ];
        };

        return [
            ['label' => __('Tagesbudget'), 'source' => 'CONTENT_BUDGET_DAILY_USD'] + $money('daily_usd', 0.0),
            ['label' => __('Monatsbudget'), 'source' => 'CONTENT_BUDGET_MONTHLY_USD'] + $money('monthly_usd', 0.0),
            ['label' => __('Portalgrenze je Tag'), 'source' => 'CONTENT_BUDGET_DAILY_USD_PER_TENANT'] + $money('daily_usd_per_tenant', 0.0),
            ['label' => __('Artikelgrenze'), 'source' => 'CONTENT_BUDGET_MAX_USD_PER_ARTICLE'] + $money('max_usd_per_article', 0.0),
            [
                'label' => __('Artikel je Portal und Tag (Vorgabe)'),
                'value' => (string) config('content.targets.articles_per_tenant_per_day', 2),
                'unlimited' => false,
                'source' => 'CONTENT_ARTICLES_PER_TENANT_PER_DAY',
            ],
            [
                'label' => __('Auto-Freigabe ab Score (Vorgabe)'),
                'value' => (string) config('content.quality.auto_approve_score', 85),
                'unlimited' => false,
                'source' => 'CONTENT_AUTO_APPROVE_SCORE',
            ],
        ];
    }

    /**
     * Umgebungsvariablen der abgeschalteten Budgetgrenzen, fuer den Warnkasten
     * ueber der Tabelle. Leer heisst: alle Grenzen stehen, kein Kasten.
     *
     * @return array<int, string>
     */
    public function getDisabledBudgetSources(): array
    {
        $sources = [];

        foreach ($this->getBudgetRows() as $row) {
            if ($row['unlimited']) {
                $sources[] = $row['source'];
            }
        }

        return $sources;
    }

    /**
     * Einstellungszeile des gewaehlten Portals, oder null bei "Alle Portale".
     */
    private function settings(): ?TenantContentSetting
    {
        $tenant = app(ContentTenantContext::class)->selected();

        if ($tenant === null) {
            return null;
        }

        try {
            return $tenant->run(fn (): TenantContentSetting => TenantContentSetting::current());
        } catch (Throwable) {
            return null;
        }
    }

    public function hasPortal(): bool
    {
        return $this->settings() !== null;
    }

    /**
     * @return array<string, string>
     */
    private function stateOptions(): array
    {
        $options = [];

        foreach ((array) config('content.regions.states', []) as $code => $state) {
            $options[(string) $code] = (string) ($state['name'] ?? $code);
        }

        return $options;
    }

    /**
     * @param  array<int, string>|null  $value
     */
    private function linesFrom(?array $value): string
    {
        return implode("\n", array_filter(array_map('strval', $value ?? [])));
    }

    /**
     * @return array<int, string>
     */
    private function linesTo(?string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value) ?: [])));
    }
}
