<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Ads\EditorToken;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Preview\EditorSlotList;
use App\Ads\Preview\PreviewUrlResolver;
use App\Ads\Support\AdsAdminAccess;
use App\Ads\Support\AdsAdminPortal;
use App\Ads\Support\AdSlotCatalog;
use App\Ads\Support\AdThemeContext;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

/**
 * Werbeplätze — der Editor (Ticket #7, docs/ad-slots.md 6).
 *
 * Links die **echte** Portalseite in einem iframe, rechts die Anker des
 * Seitentyps. Keine Attrappe: der iframe laedt die Seite des gewaehlten
 * Portals mit einer signierten Adresse ({@see EditorToken::url()}), und der
 * Editor-Modus dort zeigt alle Anker als Rahmen — auch die leeren.
 *
 * Vier Dinge, die hier bewusst so sind:
 *
 * * **Geraete-Umschaltung ist eine Breite, keine Klasse.** Mobil ist ein
 *   390 px breiter iframe, Desktop ein 1280 px breiter, per
 *   `transform: scale()` in den Platz skaliert. Eine per JS gesetzte
 *   "mobile"-Klasse wuerde eine Vorschau zeigen, die es im Browser nicht gibt
 *   (docs/ad-slots.md 6.2).
 * * **Die Beispielseite wird ermittelt, nicht geraten** —
 *   {@see PreviewUrlResolver}. Hat ein Portal keinen Ratgeber, sagt die
 *   Oberflaeche das; eine 404 im iframe sieht wie ein Fehler des Werkzeugs aus.
 *   Der Pfad bleibt ueberschreibbar.
 * * **Die Route ist waehlbar, wo ein Seitentyp mehrere hat** (Ticket #27).
 *   `guide_index` buendelt /ratgeber und die Kategorieseite; die Seitenspalte
 *   steht nur auf der Kategorieseite. Die Wahl steuert sowohl die
 *   Beispielseite als auch die Kennzeichnung in der Ankerliste — ein Anker,
 *   der auf der gezeigten Seite nicht gerendert wird, traegt dort „nicht auf
 *   dieser Seite".
 * * **Die Anker kommen aus dem Theme des Portals**, nicht aus dem ganzen
 *   Katalog (Ticket #14). 18 der 23 Portale laufen `default`; wer dort einen
 *   sun-v2-Anker belegen koennte, belegte eine Position, die stumm bleibt.
 * * **Kein eigener Token-Endpunkt.** Die signierte Adresse baut
 *   {@see EditorToken::url()} serverseitig bei jedem Laden der Vorschau;
 *   "Vorschau neu laden" ist eine Livewire-Aktion. Ein oeffentlicher Endpunkt,
 *   der Tokens ausgibt, waere eine zusaetzliche Angriffsflaeche ohne Gewinn.
 *
 * Die Gegenseite im Portal — Rahmen, `slots:ready`, `slot:clicked` und die
 * Pruefung des Tokens in {@see \App\Ads\Http\Middleware\AdEditorMode} —
 * gehoert zu Ticket #6. Der Token ist an Host und Pfad gebunden, nicht an eine
 * benannte Route: die Vorschau zeigt beliebige Portalseiten.
 *
 * @property-read Schema $form
 */
class AdPlacementEditor extends Page
{
    protected string $view = 'filament.admin.pages.ad-placement-editor';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?int $navigationSort = 89;

    protected static ?string $slug = 'werbeplaetze';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Anker, dessen Belegung gerade bearbeitet wird (Ticket #8). */
    public ?string $selectedSlotKey = null;

    /**
     * Anker, die die geladene Seite wirklich mitgebracht hat — gemeldet vom
     * Portal per `slots:ready`. Weicht die Liste vom Katalog ab, steckt der
     * Anker (noch) nicht in der View.
     *
     * @var list<string>
     */
    public array $reportedSlotKeys = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Werbeplätze');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Werbeplätze');
    }

    public function getSubheading(): ?string
    {
        return __('Echte Portalseite, alle Anker als Rahmen. Anker anklicken und belegen.');
    }

    public static function canAccess(): bool
    {
        return AdsAdminAccess::allowed();
    }

    public function mount(): void
    {
        $tenantId = AdsAdminPortal::currentId();

        $this->data = [
            'tenant_id' => $tenantId,
            'page_type' => AdPageType::SEARCH->value,
            'route' => AdPageType::SEARCH->defaultRoute(),
            'device' => AdDevice::MOBILE->value,
            'path' => null,
        ];

        $this->data['path'] = $this->ermittelterPfad();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
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
                        AdsAdminPortal::select($state === null ? null : (int) $state);
                        $this->data['path'] = $this->ermittelterPfad();
                        $this->vorschauWechseln();
                    })
                    ->helperText(fn (): string => $this->themeHinweis()),

                Select::make('page_type')
                    ->label(__('Seitentyp'))
                    ->options(AdPageType::options())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (): void {
                        // Die Routenwahl gehoert zum Seitentyp — eine
                        // mitgenommene Route des vorigen Typs waere ungueltig.
                        $this->data['route'] = $this->pageType()->defaultRoute();
                        $this->data['path'] = $this->ermittelterPfad();
                        $this->vorschauWechseln();
                    }),

                Select::make('route')
                    ->label(__('Seite'))
                    ->options(fn (): array => $this->routeOptions())
                    ->required()
                    ->live()
                    // Ein Seitentyp mit genau einer Route braucht keine Wahl.
                    ->visible(fn (): bool => $this->pageType()->hasMultipleRoutes())
                    ->afterStateUpdated(function (): void {
                        $this->data['path'] = $this->ermittelterPfad();
                        $this->vorschauWechseln();
                    })
                    ->helperText(__('Ein Seitentyp kann mehrere Seiten tragen. Die Wahl entscheidet, welche Anker hier wirklich gerendert werden.')),

                ToggleButtons::make('device')
                    ->label(__('Gerät'))
                    ->options([
                        AdDevice::MOBILE->value => __('Mobil 390 px'),
                        AdDevice::DESKTOP->value => __('Desktop 1280 px'),
                    ])
                    ->icons([
                        AdDevice::MOBILE->value => 'heroicon-o-device-phone-mobile',
                        AdDevice::DESKTOP->value => 'heroicon-o-computer-desktop',
                    ])
                    ->inline()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->vorschauWechseln()),

                TextInput::make('path')
                    ->label(__('Beispielseite'))
                    ->placeholder('/')
                    ->prefix(fn (): string => $this->tenant()?->domain === null ? '' : $this->tenant()->domain)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $this->vorschauWechseln())
                    ->helperText(__('Automatisch ermittelt; überschreibbar, wenn eine andere Seite geprüft werden soll.'))
                    ->suffixAction(
                        Action::make('pfadErmitteln')
                            ->label(__('Automatisch ermitteln'))
                            ->icon('heroicon-m-arrow-path')
                            ->action(function (): void {
                                $this->data['path'] = $this->ermittelterPfad();
                                $this->vorschauWechseln();
                            }),
                    ),
            ])
            ->statePath('data');
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('vorschauNeuLaden')
                ->label(__('Vorschau neu laden'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->vorschauWechseln()),
        ];
    }

    /** Ausgewaehlter Anker aus der Liste (Klick im Panel). */
    public function selectSlot(?string $slotKey): void
    {
        $this->selectedSlotKey = $slotKey !== null && AdSlotCatalog::exists($slotKey) ? $slotKey : null;
    }

    /**
     * Klick auf einen Rahmen im iframe. Die Brücke schickt `slot:clicked`
     * weiter; Zustand und Wirkung sind dieselben wie beim Klick in der Liste
     * (Akzeptanzkriterium 3).
     */
    #[On('ad-editor-slot-clicked')]
    public function slotAngeklickt(?string $key = null): void
    {
        $this->selectSlot($key);
    }

    /**
     * `slots:ready` aus dem Portal: welche Anker die Seite wirklich hat.
     *
     * @param  list<string>  $keys
     */
    #[On('ad-editor-slots-ready')]
    public function ankerGemeldet(array $keys = []): void
    {
        $this->reportedSlotKeys = array_values(array_filter(
            array_map('strval', $keys),
            fn (string $key): bool => $key !== '',
        ));
    }

    /**
     * Eine gespeicherte Belegung aus dem Konfigurationspanel (#8). Die
     * Ankerliste wird bei jedem Rendern frisch aufgeloest — dieser Listener
     * loest das Rendern aus. Den Rahmen im iframe zieht die Bruecke nach, die
     * dasselbe Ereignis am Fenster mithoert.
     */
    #[On('ad-editor-placement-updated')]
    public function belegungAktualisiert(): void
    {
        // Absichtlich leer: die Wirkung ist das Rendern.
    }

    public function tenant(): ?Tenant
    {
        $id = $this->data['tenant_id'] ?? null;

        return filled($id) ? Tenant::query()->find((int) $id) : null;
    }

    public function pageType(): AdPageType
    {
        return AdPageType::tryFrom((string) ($this->data['page_type'] ?? '')) ?? AdPageType::SEARCH;
    }

    /**
     * Route der gezeigten Seite. Eine Route, die nicht zum Seitentyp gehoert
     * (etwa nach einem Wechsel), faellt auf die Vorgaberoute zurueck.
     */
    public function route(): string
    {
        $route = (string) ($this->data['route'] ?? '');

        return $this->pageType()->hasRoute($route) ? $route : $this->pageType()->defaultRoute();
    }

    /**
     * Routen des Seitentyps, beschriftet aus dem Katalog.
     *
     * @return array<string, string>
     */
    public function routeOptions(): array
    {
        $options = [];

        foreach ($this->pageType()->routes() as $route) {
            $options[$route] = AdSlotCatalog::routeLabel($route);
        }

        return $options;
    }

    public function device(): AdDevice
    {
        return AdDevice::tryFrom((string) ($this->data['device'] ?? '')) ?? AdDevice::MOBILE;
    }

    /** Breite des iframes in px — die echte Breite, nicht eine Klasse. */
    public function previewWidth(): int
    {
        return $this->device() === AdDevice::DESKTOP ? 1280 : 390;
    }

    public function previewHeight(): int
    {
        return $this->device() === AdDevice::DESKTOP ? 900 : 844;
    }

    /**
     * Signierte Adresse der Vorschau — gebaut von {@see EditorToken::url()},
     * gebunden an Host und Pfad. Null heisst: kein Portal, keine Domain oder
     * keine Seite dieses Typs.
     */
    public function previewUrl(): ?string
    {
        $tenant = $this->tenant();
        $path = $this->pfad();

        if ($tenant === null || $path === null || trim((string) $tenant->domain) === '') {
            return null;
        }

        return EditorToken::url($tenant, $path, $this->pageType()->value);
    }

    /**
     * Erwartete Gegenseite der postMessage-Brücke. Dasselbe Schema wie in
     * {@see EditorToken::url()} — sonst verwirft die Bruecke jede Nachricht.
     */
    public function previewOrigin(): ?string
    {
        $domain = trim((string) $this->tenant()?->domain);

        return $domain === '' ? null : 'https://'.$domain;
    }

    /**
     * Warum die Vorschau nicht steht — als Satz, den die Oberflaeche zeigt.
     */
    public function previewHint(): ?string
    {
        if ($this->tenant() === null) {
            return __('Es ist noch kein Portal angelegt.');
        }

        if (trim((string) $this->tenant()->domain) === '') {
            return __('Dieses Portal hat keine Domain hinterlegt — ohne Domain gibt es keine Seite zum Anzeigen.');
        }

        if ($this->pfad() === null) {
            return __('Für dieses Portal gibt es noch keine Seite dieses Typs.');
        }

        // Ohne bekannte Herkunft des Panels verwirft der Editor im Portal
        // jede Nachricht der Bruecke — dann liesse sich kein Anker anklicken.
        if (EditorToken::origin() === '') {
            return __('ADS_EDITOR_ORIGIN (oder APP_URL) ist nicht gesetzt — ohne die Herkunft des Panels nimmt das Portal keine Nachrichten der Vorschau an.');
        }

        return null;
    }

    /**
     * Hat das Portal ueberhaupt eine Seite dieses Typs? Ohne sie zeigt auch
     * die Ankerliste nur den Hinweis: eine Liste von Ankern zu einer Seite,
     * die es nicht gibt, laedt zum Belegen ein, das niemand pruefen kann.
     */
    public function hasExamplePage(): bool
    {
        return $this->pfad() !== null;
    }

    /**
     * Die Ankerliste rechts.
     *
     * @return list<array<string, mixed>>
     */
    public function slots(): array
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return [];
        }

        return app(EditorSlotList::class)->build($tenant, $this->pageType(), $this->device(), $this->route());
    }

    /**
     * Anker, die der Katalog fuehrt, die geladene Seite aber nicht gemeldet
     * hat. Das ist der Fall "Anker im Katalog, aber nicht in der View" — er
     * gehoert sichtbar gemacht, sonst sucht niemand danach.
     *
     * @return list<string>
     */
    public function missingSlotKeys(): array
    {
        if ($this->reportedSlotKeys === []) {
            return [];
        }

        // Ein Anker, der auf dieser Route gar nicht steht, fehlt nicht — er
        // gehoert hier nicht hin, sondern traegt in der Liste seine eigene
        // Kennzeichnung (Ticket #27).
        $erwartet = array_column(
            array_filter($this->slots(), fn (array $slot): bool => (bool) $slot['on_route']),
            'key',
        );

        return array_values(array_diff($erwartet, $this->reportedSlotKeys));
    }

    public function themeHinweis(): string
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return '';
        }

        $theme = AdThemeContext::forTenant($tenant);

        return AdThemeContext::hasSlots($tenant)
            ? __('Theme :theme — es werden nur Anker dieses Themes angeboten.', ['theme' => $theme])
            : __('Theme :theme kennt im Katalog keine Anker. Dieses Portal hat bis zur Theme-Umstellung keine Werbeplätze.', ['theme' => $theme]);
    }

    /** Pfad der Vorschauseite: Eingabe, sonst der ermittelte. */
    private function pfad(): ?string
    {
        $pfad = trim((string) ($this->data['path'] ?? ''));

        return $pfad === '' ? null : '/'.ltrim($pfad, '/');
    }

    private function ermittelterPfad(): ?string
    {
        $tenant = $this->tenant();

        return $tenant === null
            ? null
            : app(PreviewUrlResolver::class)->resolve($tenant, $this->pageType(), $this->route());
    }

    /**
     * Ein Wechsel von Portal, Seitentyp, Geraet oder Pfad laedt die Vorschau
     * neu — mit frischer Signatur — und raeumt den gemeldeten Ankerstand weg,
     * der zur alten Seite gehoerte.
     */
    private function vorschauWechseln(): void
    {
        $this->reportedSlotKeys = [];
        $this->selectedSlotKey = null;

        $this->dispatch('ad-editor-reload', url: $this->previewUrl(), device: $this->device()->value);
    }
}
