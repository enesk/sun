<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Enums\TrustLevel;
use App\Guide\Filament\Concerns\HasSettingsTabs;
use App\Guide\Filament\Resources\PromptTemplateResource;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\Category;
use App\Guide\Models\Source;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Services\ContentTenantContext;
use App\Guide\Services\RunOverviewService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\GuidePageCache;
use App\Guide\Support\Usd;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Einstellungen › Portal (#16, design/guide-dashboard.md §9): alle Felder aus
 * tenant_guide_settings des oben gewaehlten Portals — Freischaltung,
 * Laufzeitfenster, Tagesbudget, Freigabeschwelle, YMYL, Autor, Branche,
 * CTA-Zuordnung je Kategorie, Quellen-Whitelist und -Blacklist. Jeder
 * Whitelist-Eintrag nennt, wann die Domain zuletzt als Quelle verwendet
 * wurde (guide_sources, #33).
 *
 * Wertebereiche: Schwelle 50–100 (gespeichert wird der eingetragene Wert,
 * wirksam ist TenantGuideSetting::effectiveThreshold()), Budget >= 0,01
 * (0 schaltete die Grenze ab), Fensterbeginn vor Fensterende.
 * Leeres Budget/Fenster = Vorgabe aus config/guide.php (wird angezeigt).
 * Nur fuer Inhaber.
 *
 * @property-read Schema $form
 */
class TenantGuideSettings extends Page
{
    use HasSettingsTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'einstellungen';

    protected string $view = 'content.guide.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, string>|null */
    protected ?array $sourcesLastUsed = null;

    public static function getNavigationLabel(): string
    {
        return __('Einstellungen');
    }

    /**
     * "Einstellungen" bleibt auch unter den Reitern Tageslauf und Prompts markiert.
     *
     * @return array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), DailyRunSettings::getRouteName(), PromptTemplateResource::getRouteBaseName().'.*'];
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Einstellungen');
    }

    public function getSubheading(): ?string
    {
        return $this->tenant() !== null
            ? __('Portal: :name', ['name' => $this->tenant()->name])
            : null;
    }

    public function mount(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            $this->form->fill();

            return;
        }

        $setting = $tenant->run(fn (): TenantGuideSetting => TenantGuideSetting::current());

        $this->form->fill([
            ...$setting->only([
                'is_active', 'is_ymyl', 'auto_publish_threshold', 'daily_budget_usd',
                'run_window_start', 'run_window_end', 'author_name', 'author_bio', 'branch', 'branch_plural',
            ]),
            'category_cta_mapping_json' => collect((array) ($setting->category_cta_mapping_json ?? []))
                ->map(fn (mixed $entry, string|int $slug): array => [
                    'category' => (string) $slug,
                    'label' => is_array($entry) ? ($entry['label'] ?? null) : null,
                    'url' => is_array($entry) ? ($entry['url'] ?? null) : (is_string($entry) ? $entry : null),
                ])
                ->values()
                ->all(),
            'source_whitelist_json' => array_values(array_filter((array) ($setting->source_whitelist_json ?? []), 'is_array')),
            'source_blacklist_json' => collect((array) ($setting->source_blacklist_json ?? []))
                ->map(fn (mixed $entry): array => is_array($entry) ? $entry : ['domain' => (string) $entry, 'reason' => null])
                ->values()
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Tageslauf'))
                    ->description(__('Ob und wann dieses Portal im Tageslauf mitläuft.'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('Portal ist für den Tageslauf freigeschaltet'))
                            ->helperText(__('Aus: kein neuer Lauf für dieses Portal. Veröffentlichte Artikel bleiben online.'))
                            ->columnSpanFull(),
                        TimePicker::make('run_window_start')
                            ->label(__('Laufzeitfenster ab'))
                            ->seconds(false)
                            ->placeholder((string) config('guide.run_window_start'))
                            ->helperText(__('Leer: Vorgabe :time aus der Serverkonfiguration.', ['time' => config('guide.run_window_start')]))
                            ->rule(fn (Get $get): Closure => self::windowRule($get)),
                        TimePicker::make('run_window_end')
                            ->label(__('Laufzeitfenster bis'))
                            ->seconds(false)
                            ->placeholder((string) config('guide.run_window_end'))
                            ->helperText(__('Leer: Vorgabe :time aus der Serverkonfiguration.', ['time' => config('guide.run_window_end')]))
                            ->rule(fn (Get $get): Closure => self::windowRule($get)),
                    ])
                    ->columns(2),

                Section::make(__('Budget und Freigabe'))
                    ->schema([
                        // Ursache vor Wirkung: YMYL hebt die wirksame Schwelle (§11.1).
                        Toggle::make('is_ymyl')
                            ->label(__('YMYL-Portal (Gesundheit, Geld, Recht)'))
                            ->helperText(__('Pflicht-Hinweis im Artikel und strengere Prüfung.'))
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('daily_budget_usd')
                            ->label(__('Tagesbudget dieses Portals (USD)'))
                            ->numeric()
                            // 0 hiesse im BudgetGuard "keine Grenze" (#38 G5).
                            ->minValue(0.01)
                            ->step(0.01)
                            ->validationMessages(['min' => __('0 würde die Grenze abschalten. Zum Anhalten den Schalter ‚Portal ist für den Tageslauf freigeschaltet‘ ausschalten.')])
                            ->placeholder(number_format((float) config('guide.budget.daily_usd_per_tenant'), 2, ',', '.'))
                            ->helperText(fn (): string => __('Leer: Vorgabe :default. Ist das Budget erreicht, startet heute kein weiterer Lauf für dieses Portal. Heute verbraucht: :spent.', [
                                'default' => Usd::format((float) config('guide.budget.daily_usd_per_tenant')),
                                'spent' => Usd::format($this->spentToday()),
                            ])),
                        TextInput::make('auto_publish_threshold')
                            ->label(__('Schwelle für die automatische Freigabe'))
                            ->integer()
                            ->required()
                            ->minValue(50)
                            ->maxValue(100)
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): Htmlable => self::thresholdHint($get('auto_publish_threshold'), (bool) $get('is_ymyl'))),
                    ])
                    ->columns(2),

                Section::make(__('Autor und Branche'))
                    ->schema([
                        TextInput::make('author_name')->label(__('Autor'))->maxLength(255),
                        TextInput::make('branch')->label(__('Branche'))->maxLength(120)->helperText(__('z. B. Sanitär, setzt {branche} in Themenvorlagen ein.')),
                        TextInput::make('branch_plural')->label(__('Betriebe (Mehrzahl)'))->maxLength(120)->helperText(__('z. B. Sanitärbetriebe, für Verweise auf die Firmensuche.')),
                        Textarea::make('author_bio')->label(__('Autorenbeschreibung'))->rows(3)->maxLength(2000)->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make(__('Verweise je Kategorie (CTA)'))
                    ->description(__('Wohin der Hinweis „… in Ihrer Nähe finden“ unter Artikeln einer Kategorie führt. Ohne Eintrag: Firmensuche.'))
                    ->schema([
                        Repeater::make('category_cta_mapping_json')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('category')
                                    ->label(__('Kategorie'))
                                    ->options(fn (): array => $this->categoryOptions())
                                    ->required()
                                    ->distinct(),
                                TextInput::make('label')->label(__('Beschriftung'))->maxLength(120),
                                TextInput::make('url')
                                    ->label(__('Ziel'))
                                    ->required()
                                    ->maxLength(500)
                                    ->regex('#^(/(?![/\\])|https?://)#')
                                    ->validationMessages(['regex' => __('Pfad mit / oder vollständige Adresse mit https://.')])
                                    ->placeholder('/kategorien/badsanierung'),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('Zuordnung hinzufügen'))
                            ->defaultItems(0),
                    ]),

                Section::make(__('Quellen'))
                    ->description(__('Vertrauenswürdige Domains heben die Bewertung einer Quelle; gesperrte Domains werden in der Recherche nie verwendet.'))
                    ->schema([
                        Repeater::make('source_whitelist_json')
                            ->label(__('Whitelist'))
                            ->schema([
                                TextInput::make('domain')->label(__('Domain'))->required()->maxLength(253)->rule(self::domainRule())->distinct(),
                                TextInput::make('publisher')->label(__('Herausgeber'))->maxLength(255),
                                TextInput::make('type')->label(__('Art'))->maxLength(60),
                                Select::make('trust_level')
                                    ->label(__('Gewicht'))
                                    ->options(collect(TrustLevel::options())->except(TrustLevel::OTHER->value)->all())
                                    ->required(),
                            ])
                            ->columns(4)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $this->whitelistItemLabel($state['domain'] ?? null))
                            ->addActionLabel(__('Domain hinzufügen'))
                            ->defaultItems(0),
                        Repeater::make('source_blacklist_json')
                            ->label(__('Gesperrte Domains'))
                            ->schema([
                                TextInput::make('domain')->label(__('Domain'))->required()->maxLength(253)->rule(self::domainRule())->distinct(),
                                TextInput::make('reason')->label(__('Grund'))->maxLength(255),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['domain'] ?? null)
                            ->addActionLabel(__('Domain sperren'))
                            ->defaultItems(0),
                    ]),
            ])
            ->statePath('data')
            ->disabled(fn (): bool => $this->tenant() === null);
    }

    public function save(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            Notification::make()->title(__('Bitte oben ein Portal auswählen.'))->warning()->send();

            return;
        }

        $data = $this->form->getState();

        try {
            $tenant->run(function () use ($data): void {
                TenantGuideSetting::current()->forceFill([
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'is_ymyl' => (bool) ($data['is_ymyl'] ?? false),
                    'auto_publish_threshold' => (int) $data['auto_publish_threshold'],
                    'daily_budget_usd' => filled($data['daily_budget_usd'] ?? null) ? round((float) $data['daily_budget_usd'], 2) : null,
                    'run_window_start' => filled($data['run_window_start'] ?? null) ? $data['run_window_start'] : null,
                    'run_window_end' => filled($data['run_window_end'] ?? null) ? $data['run_window_end'] : null,
                    'author_name' => filled($data['author_name'] ?? null) ? trim((string) $data['author_name']) : null,
                    'author_bio' => filled($data['author_bio'] ?? null) ? trim((string) $data['author_bio']) : null,
                    'branch' => filled($data['branch'] ?? null) ? trim((string) $data['branch']) : null,
                    'branch_plural' => filled($data['branch_plural'] ?? null) ? trim((string) $data['branch_plural']) : null,
                    'category_cta_mapping_json' => collect((array) ($data['category_cta_mapping_json'] ?? []))
                        ->filter(fn (array $row): bool => filled($row['category'] ?? null) && filled($row['url'] ?? null))
                        ->mapWithKeys(fn (array $row): array => [(string) $row['category'] => array_filter([
                            'url' => trim((string) $row['url']),
                            'label' => filled($row['label'] ?? null) ? trim((string) $row['label']) : null,
                        ])])
                        ->all(),
                    'source_whitelist_json' => collect((array) ($data['source_whitelist_json'] ?? []))
                        ->map(fn (array $row): array => array_filter([
                            'domain' => self::normalizeDomain((string) $row['domain']),
                            'publisher' => filled($row['publisher'] ?? null) ? trim((string) $row['publisher']) : null,
                            'type' => filled($row['type'] ?? null) ? trim((string) $row['type']) : null,
                            'trust_level' => (string) $row['trust_level'],
                        ]))
                        ->values()
                        ->all(),
                    'source_blacklist_json' => collect((array) ($data['source_blacklist_json'] ?? []))
                        ->map(fn (array $row): array => array_filter([
                            'domain' => self::normalizeDomain((string) $row['domain']),
                            'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : null,
                        ]))
                        ->values()
                        ->all(),
                ])->save();

                // CTA-Ziele und Autor stehen auf den Ratgeber-Seiten.
                GuidePageCache::flush();
            });
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        app(TopicDirectory::class)->forget();
        app(RunOverviewService::class)->forget();

        Notification::make()
            ->title(__('Einstellungen für :portal gespeichert.', ['portal' => $tenant->name]))
            ->success()
            ->send();
    }

    public function tenant(): ?Tenant
    {
        return app(ContentTenantContext::class)->selected();
    }

    /**
     * Werte aus der Serverkonfiguration (§9.2), schreibgeschuetzt.
     *
     * @return list<array{label: string, value: string}>
     */
    public function configValues(): array
    {
        $budget = (array) config('guide.budget', []);

        return [
            ['label' => __('Tagesbudget gesamt'), 'value' => Usd::format((float) ($budget['daily_usd_total'] ?? 0)).' · '.__('heute :spent', ['spent' => Usd::format(app(BudgetGuard::class)->spentToday())])],
            ['label' => __('Vorgabe je Portal'), 'value' => Usd::format((float) ($budget['daily_usd_per_tenant'] ?? 0)).' '.__('— gilt, wenn im Portal kein Wert eingetragen ist')],
            ['label' => __('Höchstens je Lauf'), 'value' => Usd::format((float) ($budget['max_usd_per_run'] ?? 0))],
            ['label' => __('Warnschwelle'), 'value' => (int) round((float) ($budget['warn_threshold'] ?? 0) * 100).' %'],
            ['label' => __('Neuanlagen je Portal und Tag'), 'value' => (string) (int) config('guide.schedule.max_creates_per_tenant_per_day')],
            ['label' => __('Prüfabstand (Vorgabe)'), 'value' => trans_choice('{1} täglich|[2,*] alle :count Tage', (int) config('guide.schedule.probe_interval_days'), ['count' => (int) config('guide.schedule.probe_interval_days')])],
            ['label' => __('Laufzeitfenster (Vorgabe)'), 'value' => config('guide.run_window_start').' – '.config('guide.run_window_end')],
        ];
    }

    /**
     * Hilfetext der Schwelle (§11.1): Zeile 1 immer, Zeile 2 nur, wenn die
     * wirksame Schwelle vom Eintrag abweicht oder 100 ist. Der Container der
     * Zeile 2 steht immer im DOM (aria-live), damit das Umschalten von YMYL
     * angesagt wird. Hinweis, kein Fehler.
     */
    public static function thresholdHint(mixed $entered, bool $isYmyl): Htmlable
    {
        $value = is_numeric($entered) ? (int) $entered : null;
        $notice = null;

        if ($value !== null) {
            $effective = TenantGuideSetting::effectiveThresholdFor($value, $isYmyl);

            $notice = match (true) {
                ! TenantGuideSetting::allowsAutoPublish($effective) => __('100 = jede Fassung geht in die Prüfung.'),
                $effective !== $value => __('Wirksam: :threshold (YMYL-Untergrenze)', ['threshold' => $effective]),
                default => null,
            };
        }

        return new HtmlString(view('content.guide.partials.threshold-hint', ['notice' => $notice])->render());
    }

    /**
     * Kopf eines Whitelist-Eintrags: Domain · zuletzt verwendet (§9.4).
     */
    private function whitelistItemLabel(?string $domain): ?string
    {
        if (blank($domain)) {
            return null;
        }

        $lastUsed = $this->sourcesLastUsed()[self::normalizeDomain((string) $domain)] ?? null;

        return $domain.' · '.($lastUsed !== null
            ? __('zuletzt verwendet :date', ['date' => Carbon::parse($lastUsed)->timezone((string) config('guide.timezone'))->format('d.m.Y')])
            : __('noch nicht verwendet'));
    }

    /**
     * Juengste Verwendung je Domain in den Quellen des Portals
     * (guide_sources, abgerufen bzw. angelegt), einmal je Anfrage gelesen.
     *
     * @return array<string, string> Domain ohne www. => ISO-Zeitpunkt
     */
    private function sourcesLastUsed(): array
    {
        if ($this->sourcesLastUsed !== null) {
            return $this->sourcesLastUsed;
        }

        $tenant = $this->tenant();

        if ($tenant === null) {
            return $this->sourcesLastUsed = [];
        }

        try {
            $rows = $tenant->run(fn (): array => Source::query()
                ->selectRaw('url, max(coalesce(retrieved_at, created_at)) as last_used')
                ->groupBy('url')
                ->toBase()
                ->get()
                ->all());
        } catch (Throwable) {
            return $this->sourcesLastUsed = [];
        }

        $lastUsed = [];

        foreach ($rows as $row) {
            $host = parse_url((string) $row->url, PHP_URL_HOST);

            if (! is_string($host) || $row->last_used === null) {
                continue;
            }

            $domain = self::normalizeDomain($host);
            $at = Carbon::parse((string) $row->last_used)->toIso8601String();

            // Auch Subdomains zaehlen fuer die eingetragene Domain (SourceEvaluator).
            foreach ([$domain, ...self::parentDomains($domain)] as $candidate) {
                if (! isset($lastUsed[$candidate]) || $lastUsed[$candidate] < $at) {
                    $lastUsed[$candidate] = $at;
                }
            }
        }

        return $this->sourcesLastUsed = $lastUsed;
    }

    /**
     * foo.bar.kfw.de => [bar.kfw.de, kfw.de]
     *
     * @return list<string>
     */
    private static function parentDomains(string $domain): array
    {
        $parts = explode('.', $domain);
        $parents = [];

        for ($i = 1; $i < count($parts) - 1; $i++) {
            $parents[] = implode('.', array_slice($parts, $i));
        }

        return $parents;
    }

    private function spentToday(): float
    {
        $tenant = $this->tenant();

        return $tenant !== null ? app(BudgetGuard::class)->spentToday((int) $tenant->getKey()) : 0.0;
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return [];
        }

        try {
            return $tenant->run(fn (): array => Category::query()->ordered()->pluck('name', 'slug')->all());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Fensterbeginn vor Fensterende; leere Seite = Vorgabe aus der
     * Konfiguration. Haengt an beiden Feldern, damit auch ein allein
     * gesetzter Beginn geprueft wird.
     */
    private static function windowRule(Get $get): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $start = $get('run_window_start');
            $end = $get('run_window_end');
            $from = filled($start) ? Carbon::parse((string) $start)->format('H:i') : (string) config('guide.run_window_start');
            $until = filled($end) ? Carbon::parse((string) $end)->format('H:i') : (string) config('guide.run_window_end');

            if ($from >= $until) {
                $fail(__('Das Fenster muss vor seinem Ende beginnen (:from bis :until).', ['from' => $from, 'until' => $until]));
            }
        };
    }

    private static function domainRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $domain = self::normalizeDomain((string) $value);

            if (preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) !== 1) {
                $fail(__('Keine gültige Domain, z. B. kfw.de.'));
            }
        };
    }

    /**
     * Gleiche Normalisierung wie SourceEvaluator: ohne Schema, Pfad und www.
     */
    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);
        $domain = explode('/', $domain)[0];

        return (string) preg_replace('/^www\./', '', rtrim($domain, '.'));
    }
}
