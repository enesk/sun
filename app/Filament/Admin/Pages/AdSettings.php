<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Ads\Models\AdSetting;
use App\Ads\Support\AdCache;
use App\Ads\Support\AdsAdminAccess;
use App\Ads\Support\AdsAdminPortal;
use App\Ads\Support\AdSensePublisherId;
use App\Ads\Support\AdsTxtBuilder;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Einstellungen › Werbung (Ticket #10, docs/ad-slots.md 4.2).
 *
 * Alle Werte aus `ad_settings` des oben gewaehlten Portals. Eine Seite mit
 * Formular und keine Resource — es gibt genau eine Zeile je Portal; dasselbe
 * Muster wie beim Bot-Schutz ({@see BotProtectionSettings}). Fehlt die Zeile,
 * legt das Speichern sie mit {@see AdSetting::defaults()} an.
 *
 * Drei Werte, drei Wirkungen:
 *
 * * **ads_enabled** ist der Hauptschalter. Aus heisst: der Resolver spielt in
 *   diesem Portal nichts aus, egal welche Belegungen netzweit aktiv sind, und
 *   `/ads.txt` verliert seine Google-Zeile. Fehlt die Zeile ganz, gilt "aus".
 * * **adsense_publisher_id** ist `data-ad-client` jedes AdSense-Blocks dieses
 *   Portals. Leer heisst: es gilt die Netz-Vorgabe aus
 *   `config('ad-slots.adsense.publisher_id')`. Ohne beides wird kein
 *   AdSense-Werbemittel ausgespielt — der Anker bleibt leer.
 * * **ads_txt_extra** sind die Zusatzzeilen der `ads.txt` dieser Domain
 *   (fremde Vermarkter, Weiterverkaeufer). Die Google-Zeile steht dort nicht,
 *   die baut {@see AdsTxtBuilder} aus der Publisher-ID.
 *
 * Aenderungen wirken sofort: das Speichern loest
 * {@see \App\Ads\Observers\AdCacheInvalidator} aus, der die aufgeloesten
 * Karten **und** die gecachte `ads.txt` dieses Portals vergisst. Kein
 * Deployment, kein `cache:clear`.
 *
 * **adsense_auto_ads** schaltet die AdSense Auto Ads dieses Portals (#18).
 * Auto Ads sind kein Werbeplatz: das Skript platziert selbst, ausserhalb des
 * Katalogs (docs/ad-slots.md 1). Ausgespielt werden sie von
 * {@see \App\View\Components\Sun\AutoAds} — nur mit Hauptschalter,
 * wirksamer Publisher-ID und Einwilligung, und nicht auf den Ratgeber-Routen
 * aus `config('ad-slots.adsense.auto_ads.blocked_routes')`.
 *
 * @property-read Schema $form
 */
class AdSettings extends Page
{
    protected string $view = 'filament.admin.pages.ad-settings';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'werbung';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Werbung');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Werbung');
    }

    public function getSubheading(): ?string
    {
        return __('Freigabe, AdSense-Publisher-ID, Auto Ads und ads.txt je Portal. Änderungen wirken sofort.');
    }

    public static function canAccess(): bool
    {
        return AdsAdminAccess::allowed();
    }

    public function mount(): void
    {
        $this->fillFromTenant(AdsAdminPortal::currentId());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    // __('Portal') faende auf einem Dateisystem ohne
                    // Gross-/Kleinschreibung die Gruppe lang/de/portal.php.
                    ->label('Portal')
                    ->options(fn (): array => AdsAdminPortal::options())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (mixed $state): void {
                        $this->fillFromTenant($state === null ? null : (int) $state);
                    })
                    ->helperText(__('Die Einstellungen gelten nur für dieses Portal.')),

                Section::make(__('Hauptschalter'))
                    ->description(__('Ohne diesen Schalter bleibt in diesem Portal jeder Werbeplatz leer — unabhängig von den Placements.'))
                    ->schema([
                        Toggle::make('ads_enabled')
                            ->label(__('Werbung in diesem Portal ausspielen'))
                            ->helperText(__('Aus: alle Anzeigen verschwinden sofort, und /ads.txt verliert die Google-Zeile. Die Placements bleiben bestehen.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('AdSense'))
                    ->description(__('Die Publisher-ID geht als data-ad-client in jeden Anzeigenblock dieses Portals.'))
                    ->schema([
                        TextInput::make('adsense_publisher_id')
                            ->label(__('Publisher-ID'))
                            ->placeholder($this->fallbackPublisherHint())
                            ->maxLength(32)
                            ->rule('regex:'.AdSensePublisherId::PATTERN)
                            ->validationMessages([
                                'regex' => __('Format: pub- gefolgt von 16 Ziffern, z. B. pub-1234567890123456 (ca- davor ist erlaubt).'),
                            ])
                            ->helperText(__('Leer = Vorgabe des Netzes. Ohne beides wird in diesem Portal kein AdSense ausgespielt.'))
                            ->columnSpanFull(),

                        Toggle::make('adsense_auto_ads')
                            ->label(__('Auto Ads ausspielen'))
                            ->helperText(__('Google platziert zusätzlich selbst, außerhalb der Werbeplätze. Braucht den Hauptschalter und eine Publisher-ID. Auf den Ratgeber-Seiten bleiben Auto Ads aus (CLS).'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('ads.txt'))
                    ->description(__('Zusatzzeilen für die ads.txt dieser Domain — eine Zeile je Vermarkter. Die Google-Zeile entsteht automatisch aus der Publisher-ID.'))
                    ->schema([
                        Textarea::make('ads_txt_extra')
                            ->label(__('Zusätzliche Zeilen'))
                            ->rows(6)
                            ->maxLength(20000)
                            ->helperText(__('Leerzeilen und Zeilen mit # werden nicht ausgeliefert. Doppelte Zeilen fallen weg.'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data')
            ->disabled(fn (): bool => $this->tenant() === null);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('adsTxtOeffnen')
                ->label(__('ads.txt dieser Domain öffnen'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => $this->adsTxtUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->adsTxtUrl() !== null),
        ];
    }

    public function save(): void
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            Notification::make()->title(__('Bitte oben ein Portal auswählen.'))->warning()->send();

            return;
        }

        $data = $this->form->getState();

        $setting = $this->setting() ?? new AdSetting(['tenant_id' => $tenant->getKey()] + AdSetting::defaults());

        $setting->fill([
            'tenant_id' => $tenant->getKey(),
            'ads_enabled' => (bool) ($data['ads_enabled'] ?? false),
            'adsense_publisher_id' => AdSensePublisherId::normalize($data['adsense_publisher_id'] ?? null),
            'adsense_auto_ads' => (bool) ($data['adsense_auto_ads'] ?? false),
            'ads_txt_extra' => filled($data['ads_txt_extra'] ?? null) ? trim((string) $data['ads_txt_extra']) : null,
            'updated_by' => auth()->id(),
        ]);

        $setting->save();

        // Der Observer leert die Karten dieses Portals schon auf `saved`; der
        // Aufruf hier steht fuer den Fall, dass nichts zu speichern war
        // (`save()` ohne Aenderung feuert kein Ereignis) — die Erwartung
        // "Speichern heisst frischer Stand" soll immer gelten.
        AdCache::forgetTenant($tenant);

        $this->fillFromTenant((int) $tenant->getKey());

        Notification::make()
            ->title(__('Werbe-Einstellungen für :portal gespeichert.', ['portal' => $tenant->name]))
            ->success()
            ->send();
    }

    public function tenant(): ?Tenant
    {
        $id = $this->data['tenant_id'] ?? null;

        return filled($id) ? Tenant::query()->find((int) $id) : null;
    }

    /**
     * Was gerade gelten wuerde — damit neben dem Formular steht, worauf ein
     * leeres Feld zurueckfaellt und was unter der Domain ausgeliefert wird.
     *
     * @return list<array{label: string, value: string}>
     */
    public function stateRows(): array
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return [];
        }

        $setting = $this->setting();
        $publisher = $setting?->adsensePublisherId();

        return [
            [
                'label' => __('Freigabe dieses Portals'),
                'value' => $setting?->ads_enabled === true ? __('an') : __('AUS — kein Werbeplatz rendert'),
            ],
            [
                'label' => __('Wirksame Publisher-ID'),
                'value' => $publisher === null
                    ? __('keine — AdSense wird nicht ausgespielt')
                    : $publisher.' · '.($setting?->hasOwnPublisherId() === true ? __('eigene') : __('Vorgabe des Netzes')),
            ],
            [
                'label' => __('Auto Ads'),
                'value' => $setting?->adsense_auto_ads !== true
                    ? __('aus')
                    : ($setting?->ads_enabled === true && $publisher !== null
                        ? __('an — Google platziert zusätzlich selbst')
                        : __('eingeschaltet, aber wirkungslos (Hauptschalter oder Publisher-ID fehlt)')),
            ],
            [
                'label' => __('Netz-Vorgabe'),
                'value' => trim((string) config('ad-slots.adsense.publisher_id')) !== ''
                    ? (string) config('ad-slots.adsense.publisher_id')
                    : __('nicht gesetzt (ADSENSE_PUBLISHER_ID)'),
            ],
            [
                'label' => __('Zwischenspeicher'),
                'value' => __(':seconds Sekunden je Seitentyp und für ads.txt', ['seconds' => AdCache::ttl()]),
            ],
        ];
    }

    /**
     * Die `ads.txt`, wie die Domain sie gerade ausliefert — ungecacht aus dem
     * gespeicherten Stand. Leer heisst: die Adresse antwortet mit 404.
     */
    public function adsTxtPreview(): string
    {
        $tenant = $this->tenant();

        return $tenant === null
            ? ''
            : app(AdsTxtBuilder::class)->build($tenant->getKey());
    }

    public function adsTxtUrl(): ?string
    {
        $domain = trim((string) $this->tenant()?->domain);

        return $domain === '' ? null : "https://{$domain}/ads.txt";
    }

    private function setting(): ?AdSetting
    {
        $tenant = $this->tenant();

        return $tenant === null
            ? null
            : AdSetting::query()->forTenant($tenant)->first();
    }

    private function fillFromTenant(?int $tenantId): void
    {
        AdsAdminPortal::select($tenantId);

        $tenantId = AdsAdminPortal::currentId();

        if ($tenantId === null) {
            $this->form->fill(['tenant_id' => null]);

            return;
        }

        $setting = AdSetting::query()->forTenant($tenantId)->first();

        $this->form->fill([
            'tenant_id' => $tenantId,
            'ads_enabled' => $setting?->ads_enabled ?? false,
            'adsense_publisher_id' => $setting?->adsense_publisher_id,
            'adsense_auto_ads' => $setting?->adsense_auto_ads ?? false,
            'ads_txt_extra' => $setting?->ads_txt_extra,
        ]);
    }

    private function fallbackPublisherHint(): string
    {
        $fallback = trim((string) config('ad-slots.adsense.publisher_id'));

        return $fallback === ''
            ? __('keine Netz-Vorgabe hinterlegt')
            : __('Vorgabe des Netzes: :id', ['id' => $fallback]);
    }
}
