<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Models\Central\ContentUser;
use App\Content\Models\TenantContentSetting;
use App\Content\Services\ContentTenantContext;
use App\Models\Portal\PostCategory;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Einstellungen eines Portals (#20), design/content-dashboard.md, §7.
 *
 * Alle Felder aus `tenant_content_settings`, also genau die Werte, die
 * Themenauswahl, Qualitaetsgate und Veroeffentlichung eines Portals steuern.
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

    protected static ?string $slug = 'einstellungen';

    protected static string $followUpTicket = '#20';

    protected string $view = 'filament.content.pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

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

        return $user instanceof ContentUser && $user->canManageSettings();
    }

    public function mount(): void
    {
        $settings = $this->settings();

        $this->form->fill($settings === null ? [] : [
            ...$settings->only([
                'is_active',
                'articles_per_day',
                'auto_publish_threshold',
                'is_ymyl',
                'tone',
                'allowed_region_scopes_json',
                'preferred_states_json',
                'publish_window_start',
                'publish_window_end',
                'author_name',
                'author_bio',
                'gsc_property',
            ]),
            'organization_same_as_json' => $this->linesFrom($settings->organization_same_as_json),
            'author_same_as_json' => $this->linesFrom($settings->author_same_as_json),
            'branch_keywords_json' => $this->jsonFrom($settings->branch_keywords_json),
            'category_mapping_json' => $this->mappingFrom($settings->category_mapping_json),
            'scoring_weights_json' => $settings->scoringWeights(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Produktion'))
                    ->description(__('Wie viel dieses Portal am Tag erzeugt und ab wann ohne Prüfung veröffentlicht wird.'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('Portal ist freigeschaltet'))
                            ->helperText(__('Aus: der Tageslauf überspringt dieses Portal vollständig. Der Rollout schaltet Portal für Portal frei.'))
                            ->columnSpanFull(),
                        TextInput::make('articles_per_day')
                            ->label(__('Artikel pro Tag'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(5)
                            ->helperText(__('1 bis 5. Grundlage der Kostenrechnung sind 2 Artikel je Portal und Tag.')),
                        TextInput::make('auto_publish_threshold')
                            ->label(__('Auto-Freigabe ab Score'))
                            ->numeric()
                            ->required()
                            ->minValue(50)
                            ->maxValue(100)
                            ->helperText(__('50 bis 100. Darunter geht der Artikel in die Prüfung.')),
                        Toggle::make('is_ymyl')
                            ->label(__('YMYL-Portal'))
                            ->helperText(__('Gesundheit, Recht, Finanzen: strengere Rubrik und Pflicht-Disclaimer.')),
                        Select::make('tone')
                            ->label(__('Tonfall'))
                            ->options([
                                'sachlich' => __('sachlich'),
                                'beratend' => __('beratend'),
                                'praktisch' => __('praktisch'),
                            ])
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('Veröffentlichungsfenster'))
                    ->description(__('Innerhalb dieser Zeiten erscheinen die Artikel gestaffelt.'))
                    ->schema([
                        TimePicker::make('publish_window_start')
                            ->label(__('Beginn'))
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('publish_window_end')
                            ->label(__('Ende'))
                            ->seconds(false)
                            ->required()
                            ->after('publish_window_start'),
                    ])
                    ->columns(2),

                Section::make(__('Regionen und Themen'))
                    ->schema([
                        Select::make('allowed_region_scopes_json')
                            ->label(__('Erlaubte Regionsebenen'))
                            ->multiple()
                            ->options([
                                'national' => __('Bundesweit'),
                                'state' => __('Bundesland'),
                                'city' => __('Stadt'),
                            ])
                            ->native(false),
                        Select::make('preferred_states_json')
                            ->label(__('Bevorzugte Bundesländer'))
                            ->multiple()
                            ->options($this->stateOptions())
                            ->native(false)
                            ->helperText(__('Leer bedeutet: alle Bundesländer sind zulässig.')),
                        Textarea::make('branch_keywords_json')
                            ->label(__('Branchen-Keywords'))
                            ->rows(10)
                            ->helperText(__('JSON, nach Unterthema gruppiert: {"heizung": ["wärmepumpe kosten"], …}'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Gewichte des Themen-Scorings'))
                    ->description(__('Wie stark jede Dimension in die Tagesauswahl eingeht. Verrechnet wird relativ zur Summe.'))
                    ->schema([
                        Fieldset::make(__('Dimensionen'))
                            ->schema($this->weightFields())
                            ->columns(5),
                    ]),

                Section::make(__('Autor und Organisation'))
                    ->description(__('Erscheint in der Autorenbox, im Autorenprofil und im JSON-LD der Artikel.'))
                    ->schema([
                        TextInput::make('author_name')
                            ->label(__('Autorname'))
                            ->maxLength(255),
                        TextInput::make('gsc_property')
                            ->label(__('Search-Console-Property'))
                            ->maxLength(255)
                            ->helperText(__('z. B. sc-domain:beispiel.de')),
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

                Section::make(__('Kategorie-Zuordnung'))
                    ->description(__('Welcher Ratgeber-Cluster in welche Beitragskategorie des Portals veröffentlicht wird. Der Schlüssel "default" gilt, wenn kein Cluster passt.'))
                    ->schema([
                        KeyValue::make('category_mapping_json')
                            ->label(__('Cluster → Kategorie'))
                            ->keyLabel(__('Cluster-Slug'))
                            ->valueLabel(__('Kategorie-ID'))
                            ->addActionLabel(__('Zuordnung hinzufügen'))
                            ->helperText($this->categoryHint()),
                    ]),
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
                $active = (bool) ($data['is_active'] ?? false);

                $settings->forceFill([
                    'is_active' => $active,
                    // Der Zeitpunkt der Freischaltung bleibt stehen, solange das
                    // Portal aktiv ist — jedes Speichern wuerde ihn sonst neu setzen.
                    'activated_at' => $active ? ($settings->activated_at ?? now()) : null,
                    'articles_per_day' => (int) $data['articles_per_day'],
                    'auto_publish_threshold' => (int) $data['auto_publish_threshold'],
                    'is_ymyl' => (bool) $data['is_ymyl'],
                    'tone' => (string) $data['tone'],
                    'allowed_region_scopes_json' => array_values((array) ($data['allowed_region_scopes_json'] ?? [])),
                    'preferred_states_json' => array_values((array) ($data['preferred_states_json'] ?? [])),
                    'branch_keywords_json' => $this->decodeJson($data['branch_keywords_json'] ?? null, 'branch_keywords_json'),
                    'publish_window_start' => $data['publish_window_start'],
                    'publish_window_end' => $data['publish_window_end'],
                    'author_name' => $data['author_name'] ?: null,
                    'author_bio' => $data['author_bio'] ?: null,
                    'author_same_as_json' => $this->linesTo($data['author_same_as_json'] ?? null),
                    'organization_same_as_json' => $this->linesTo($data['organization_same_as_json'] ?? null),
                    'category_mapping_json' => $this->mappingTo($data['category_mapping_json'] ?? []),
                    'scoring_weights_json' => array_map('intval', (array) ($data['scoring_weights_json'] ?? [])),
                    'gsc_property' => $data['gsc_property'] ?: null,
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
     * Budgetwerte aus der Serverkonfiguration. Sie stehen hier als Anzeige,
     * nicht als Formular — geaendert werden sie in .env.
     *
     * @return array<int, array{label: string, value: string, source: string}>
     */
    public function getBudgetRows(): array
    {
        return [
            [
                'label' => __('Tagesbudget'),
                'value' => number_format((float) config('content.budget.daily_usd', 0), 2, ',', '.').' $',
                'source' => 'CONTENT_BUDGET_DAILY_USD',
            ],
            [
                'label' => __('Monatsbudget'),
                'value' => number_format((float) config('content.budget.monthly_usd', 0), 2, ',', '.').' $',
                'source' => 'CONTENT_BUDGET_MONTHLY_USD',
            ],
            [
                'label' => __('Artikel je Portal und Tag (Vorgabe)'),
                'value' => (string) config('content.targets.articles_per_tenant_per_day', 2),
                'source' => 'CONTENT_ARTICLES_PER_TENANT_PER_DAY',
            ],
            [
                'label' => __('Auto-Freigabe ab Score (Vorgabe)'),
                'value' => (string) config('content.quality.auto_approve_score', 85),
                'source' => 'CONTENT_AUTO_APPROVE_SCORE',
            ],
        ];
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
     * @return array<int, TextInput>
     */
    private function weightFields(): array
    {
        $fields = [];

        foreach (TenantContentSetting::scoringDimensions() as $key => $label) {
            $fields[] = TextInput::make("scoring_weights_json.{$key}")
                ->label($label)
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(100)
                ->default(20);
        }

        return $fields;
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

    private function categoryHint(): string
    {
        $tenant = app(ContentTenantContext::class)->selected();

        if ($tenant === null) {
            return __('Bitte oben ein Portal auswählen.');
        }

        try {
            $categories = $tenant->run(fn (): array => PostCategory::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->map(fn (string $name, int $id): string => "{$id} = {$name}")
                ->values()
                ->all());
        } catch (Throwable) {
            $categories = [];
        }

        return $categories === []
            ? __('Für dieses Portal sind keine Beitragskategorien angelegt.')
            : __('Verfügbar: :list', ['list' => implode(' · ', $categories)]);
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

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function jsonFrom(?array $value): string
    {
        return $value === null || $value === []
            ? ''
            : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ValidationException wenn das Feld kein gueltiges JSON-Objekt ist
     */
    private function decodeJson(?string $value, string $field): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                "data.{$field}" => __('Das Feld enthält kein gültiges JSON-Objekt.'),
            ]);
        }

        return $decoded;
    }

    /**
     * KeyValue liefert und erwartet Zeichenketten; in der Datenbank stehen
     * Kategorie-IDs als Zahl.
     *
     * @param  array<string, mixed>|null  $value
     * @return array<string, string>
     */
    private function mappingFrom(?array $value): array
    {
        return array_map('strval', $value ?? []);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, int>
     */
    private function mappingTo(array $value): array
    {
        $mapping = [];

        foreach ($value as $key => $id) {
            $key = trim((string) $key);

            if ($key === '' || ! ctype_digit(trim((string) $id))) {
                continue;
            }

            $mapping[$key] = (int) $id;
        }

        return $mapping;
    }
}
