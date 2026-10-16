<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Ads\Actions\SavePlacement;
use App\Ads\AdProviderRegistry;
use App\Ads\AdSlotResolver;
use App\Ads\Dto\AdResolution;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Forms\ProviderConfigForm;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdUnit;
use App\Ads\Rules\DeviceFitsSlot;
use App\Ads\Support\AdsAdminAccess;
use App\Ads\Support\AdsAdminPortal;
use App\Ads\Support\AdSlotCatalog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Konfigurationspanel eines Ankers (Ticket #8, docs/ad-slots.md 6).
 *
 * Haengt im Werbeplatz-Editor (#7) unter der Ankerliste: oben die Belegungen
 * dieses Ankers, darunter das Formular. Was hier bewusst so ist:
 *
 * * **Eine Komponente je Anker.** Der Editor bindet das Panel mit einem
 *   `:key` aus Portal, Anker und Geraet ein — ein Wechsel baut es neu auf,
 *   statt Formularstand von einem anderen Anker mitzunehmen.
 * * **Geraete kommen aus dem Katalog.** `sidebar_*` ist desktop-only; dort
 *   gibt es keine Wahl, nur die Feststellung (docs/ad-slots.md 3.2). Das Model
 *   verengt zusaetzlich beim Speichern — die Oberflaeche soll nur nichts
 *   anbieten, was dort stillschweigend geaendert wuerde.
 * * **Portale: Schalter oder Liste.** `applies_to_all_tenants` und der Pivot
 *   sind zwei Darstellungen derselben Aussage, nie beide zugleich
 *   ({@see SavePlacement}).
 * * **Vorgabe-Prioritaet haengt am Scope.** Portalgenau 20, netzweit 10: eine
 *   portalgenaue Belegung schlaegt eine netzweite nicht von selbst, das regelt
 *   allein `priority` (docs/ad-slots.md 4.3). Die Vorgabe sorgt dafuer, dass
 *   der haeufige Fall ohne Nachrechnen stimmt.
 * * **Konflikt und Format sind Hinweise, keine Sperren.** Zwei aktive
 *   Belegungen mit gleicher Prioritaet und ein zu grosses Format sind fast
 *   immer ein Versehen — aber beides ist zulaessig, und der Altbestand (#15)
 *   muss weiter speicherbar bleiben.
 *
 * Gespeichert wird ueber {@see SavePlacement} (Pivot und Cache), gemeldet per
 * `ad-editor-placement-updated` — die Bruecke macht daraus ein
 * `placement:updated` an den iframe, der seinen Rahmen ohne Reload nachzieht
 * (docs/ad-slots.md 6.2).
 *
 * @property-read Schema $form
 */
class AdPlacementPanel extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** Vorgabe-Prioritaet einer portalgenauen Belegung. */
    public const PRIORITY_TENANT = 20;

    /** Vorgabe-Prioritaet einer netzweiten Belegung. */
    public const PRIORITY_NETWORK = 10;

    public ?int $tenantId = null;

    public ?string $slotKey = null;

    /** Geraet der Vorschau — Vorgabe im Formular und Blick der Liste. */
    public string $device = AdDevice::MOBILE->value;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Belegung in Bearbeitung; null heisst: neue Belegung. */
    public ?int $editingId = null;

    public bool $formOpen = false;

    /** @var Collection<int, AdPlacement>|null */
    private ?Collection $placementCache = null;

    /**
     * Zugang bei **jeder** Anfrage pruefen, nicht nur beim Aufbau (#11,
     * docs/ad-slots-security-review.md 4).
     *
     * `mount()` laeuft genau einmal; jeder weitere Aufruf der Komponente
     * kommt als Livewire-Update mit einem Schnappschuss, der die Pruefung
     * sonst umgeht. Die Pruefungen in den schreibenden Methoden bleiben
     * trotzdem stehen — zwei Grenzen, die unabhaengig voneinander halten.
     */
    public function boot(): void
    {
        abort_unless(AdsAdminAccess::allowed(), 403);
    }

    public function mount(): void
    {
        if ($this->slotKey !== null && ! AdSlotCatalog::exists($this->slotKey)) {
            $this->slotKey = null;
        }

        $this->form->fill($this->defaults());
    }

    public function render(): View
    {
        return view('livewire.admin.ad-placement-panel', [
            'placements' => $this->placements(),
            'fremde' => $this->foreignCount(),
            'konflikte' => $this->conflicts(),
            'formatHinweis' => $this->formatHint(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Werbemittel'))
                    ->schema([
                        Select::make('ad_unit_id')
                            ->label(__('Werbemittel'))
                            ->options(fn (): array => $this->adUnitOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): ?string => $this->adUnitHint($get('ad_unit_id')))
                            ->createOptionForm(fn (): array => [
                                TextInput::make('name')
                                    ->label(__('Bezeichnung'))
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText(__('Nur für die Verwaltung, z. B. „AdSense Konto A · Rechteck".')),
                                Select::make('provider')
                                    ->label(__('Art'))
                                    ->options(fn (): array => app(AdProviderRegistry::class)->options())
                                    ->required()
                                    ->live(),
                                Section::make(__('Angaben der gewählten Art'))
                                    ->description(fn (Get $get): string => ProviderConfigForm::hint($get('provider')))
                                    // Die Felder kommen aus dem Schema des
                                    // Providers, nicht aus diesem Formular
                                    // (docs/ad-slots.md 5.3).
                                    ->schema(fn (Get $get): array => ProviderConfigForm::fields($get('provider')))
                                    ->visible(fn (Get $get): bool => filled($get('provider'))),
                            ])
                            ->createOptionModalHeading(__('Neues Werbemittel'))
                            ->createOptionUsing(fn (array $data): int => $this->createAdUnit($data)),
                    ]),

                Section::make(__('Ausspielung'))
                    ->schema([
                        ToggleButtons::make('device')
                            ->label(__('Gerät'))
                            ->options(fn (): array => $this->deviceOptions())
                            ->inline()
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => count($this->deviceOptions()) === 1)
                            // Ein abgeschaltetes Feld wird in Filament nicht
                            // mehr ausgelesen — der Wert gehoert aber in den
                            // Datensatz.
                            ->dehydrated()
                            ->rules([fn (): DeviceFitsSlot => new DeviceFitsSlot($this->slotKey)])
                            ->helperText(fn (): ?string => count($this->deviceOptions()) === 1
                                ? __('Dieser Anker steht nur auf :device.', ['device' => $this->slotDevice()->label()])
                                : null),

                        Toggle::make('applies_to_all_tenants')
                            ->label(__('Alle Portale'))
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set): void {
                                // Die Vorgabe-Prioritaet haengt am Scope; eine
                                // selbst gesetzte Zahl bleibt stehen.
                                $set('tenant_ids', $state ? [] : [$this->tenantId]);
                                $set('priority', $state ? self::PRIORITY_NETWORK : self::PRIORITY_TENANT);
                            })
                            ->helperText(__('An: die Belegung gilt für jedes Portal des Netzes. Aus: nur für die ausgewählten.')),

                        Select::make('tenant_ids')
                            ->label('Portale')
                            ->multiple()
                            ->options(fn (): array => AdsAdminPortal::names())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->visible(fn (Get $get): bool => ! $get('applies_to_all_tenants'))
                            ->hintAction(
                                Action::make('invertieren')
                                    ->label(__('alle außer der Auswahl'))
                                    ->icon('heroicon-m-arrows-right-left')
                                    ->action(function (Get $get, Set $set): void {
                                        $set('tenant_ids', $this->invertTenants((array) $get('tenant_ids')));
                                    }),
                            )
                            ->helperText(__('Suche nach Name oder Domain. „alle außer der Auswahl" dreht die Auswahl um.')),
                    ])
                    ->columns(1),

                Section::make(__('Zeitraum, Priorität, Status'))
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label(__('Start'))
                            ->seconds(false)
                            ->placeholder(__('sofort')),

                        DateTimePicker::make('ends_at')
                            ->label(__('Ende'))
                            ->seconds(false)
                            ->placeholder(__('unbefristet'))
                            ->after('starts_at'),

                        TextInput::make('priority')
                            ->label(__('Priorität'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->live(onBlur: true)
                            ->helperText(__('Größer gewinnt; bei Gleichstand die neuere Belegung.')),

                        ToggleButtons::make('status')
                            ->label(__('Status'))
                            ->options([
                                AdPlacementStatus::DRAFT->value => AdPlacementStatus::DRAFT->label(),
                                AdPlacementStatus::ACTIVE->value => AdPlacementStatus::ACTIVE->label(),
                                AdPlacementStatus::PAUSED->value => AdPlacementStatus::PAUSED->label(),
                            ])
                            ->colors([
                                AdPlacementStatus::DRAFT->value => AdPlacementStatus::DRAFT->color(),
                                AdPlacementStatus::ACTIVE->value => AdPlacementStatus::ACTIVE->color(),
                                AdPlacementStatus::PAUSED->value => AdPlacementStatus::PAUSED->color(),
                            ])
                            ->inline()
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /** Neues Placement: Formular mit den Vorgaben oeffnen. */
    public function neu(): void
    {
        $this->editingId = null;
        $this->form->fill($this->defaults());
        $this->formOpen = true;
    }

    public function bearbeiten(int $id): void
    {
        $placement = $this->findPlacement($id);

        if ($placement === null) {
            return;
        }

        $this->editingId = (int) $placement->getKey();
        $this->form->fill([
            'ad_unit_id' => (int) $placement->ad_unit_id,
            'device' => $placement->device->value,
            'applies_to_all_tenants' => (bool) $placement->applies_to_all_tenants,
            'tenant_ids' => $placement->tenants->modelKeys(),
            'starts_at' => $placement->starts_at,
            'ends_at' => $placement->ends_at,
            'priority' => (int) $placement->priority,
            // `expired` steht nicht zur Wahl — es wird errechnet, nicht
            // gesetzt (docs/ad-slots.md 4.2). Eine so gespeicherte Belegung
            // kommt als Entwurf zurueck; das ist der einzige erlaubte
            // Uebergang.
            'status' => $placement->status === AdPlacementStatus::EXPIRED
                ? AdPlacementStatus::DRAFT->value
                : $placement->status->value,
        ]);
        $this->formOpen = true;
    }

    public function abbrechen(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
        $this->form->fill($this->defaults());
    }

    public function speichern(): void
    {
        abort_unless(AdsAdminAccess::allowed(), 403);

        if ($this->slotKey === null) {
            return;
        }

        $data = $this->form->getState();
        $placement = $this->editingId === null ? null : $this->findPlacement($this->editingId);

        try {
            $placement = app(SavePlacement::class)->execute($this->slotKey, $data, $placement);
        } catch (ValidationException $exception) {
            // Die Pruefungen des Models (Katalog, Geraet, Archiv) als
            // Formularfehler, nicht als 500.
            Notification::make()
                ->title(__('Die Belegung konnte nicht gespeichert werden.'))
                ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                ->danger()
                ->send();

            return;
        }

        $this->formOpen = false;
        $this->editingId = null;
        $this->placementCache = null;
        $this->form->fill($this->defaults());

        Notification::make()
            ->title(__('Belegung gespeichert.'))
            ->body(__('Status :status. Der Cache der betroffenen Portale ist geleert.', [
                'status' => $placement->status->label(),
            ]))
            ->success()
            ->send();

        $this->melde($placement);
    }

    /** Status einer Belegung aus der Liste heraus wechseln. */
    public function statusWechseln(int $id, string $status): void
    {
        abort_unless(AdsAdminAccess::allowed(), 403);

        $placement = $this->findPlacement($id);
        $ziel = AdPlacementStatus::tryFrom($status);

        if ($placement === null || $ziel === null || ! $placement->status->canTransitionTo($ziel)) {
            return;
        }

        $placement = app(SavePlacement::class)->changeStatus($placement, $ziel);
        $this->placementCache = null;

        Notification::make()
            ->title(__('Belegung ist jetzt :status.', ['status' => $ziel->label()]))
            ->success()
            ->send();

        $this->melde($placement);
    }

    /**
     * Belegungen dieses Ankers, die das gezeigte Portal betreffen — netzweite
     * eingeschlossen, in der Rangfolge der Aufloesung.
     *
     * @return Collection<int, AdPlacement>
     */
    public function placements(): Collection
    {
        if ($this->slotKey === null || $this->tenantId === null) {
            return new Collection;
        }

        return $this->placementCache ??= AdPlacement::query()
            ->with(['adUnit', 'tenants:id,name'])
            ->where('slot_key', $this->slotKey)
            ->forTenant($this->tenantId)
            ->resolutionOrder()
            ->get();
    }

    /**
     * Belegungen desselben Ankers, die nur andere Portale betreffen. Sie
     * gehoeren nicht in die Liste, aber die Zahl gehoert gesagt — sonst sieht
     * ein leerer Anker nach "noch nie belegt" aus.
     */
    public function foreignCount(): int
    {
        if ($this->slotKey === null || $this->tenantId === null) {
            return 0;
        }

        return AdPlacement::query()
            ->where('slot_key', $this->slotKey)
            ->whereNotIn('id', $this->placements()->modelKeys())
            ->count();
    }

    /**
     * Konkurrierende aktive Belegungen zum aktuellen Formularstand.
     *
     * @return Collection<int, AdPlacement>
     */
    public function conflicts(): Collection
    {
        if (! $this->formOpen || $this->slotKey === null) {
            return new Collection;
        }

        return app(SavePlacement::class)->conflicts(
            $this->slotKey,
            (array) $this->data,
            $this->editingId === null ? null : $this->findPlacement($this->editingId),
        );
    }

    /** Formatwarnung zum gewaehlten Werbemittel (docs/ad-slots.md 7). */
    public function formatHint(): ?string
    {
        $unitId = $this->data['ad_unit_id'] ?? null;

        if (! $this->formOpen || $this->slotKey === null || ! filled($unitId)) {
            return null;
        }

        return app(SavePlacement::class)->formatHint(
            $this->slotKey,
            AdUnit::query()->find((int) $unitId),
        );
    }

    public function slotLabel(): string
    {
        return (string) (AdSlotCatalog::find((string) $this->slotKey)['label'] ?? $this->slotKey);
    }

    /**
     * Geraete, die der Anker zulaesst. Ein desktop-only Anker bietet genau
     * eine Angabe — waehlbar waere sie eine Einladung zu einer Belegung, die
     * das Model stillschweigend verengt.
     *
     * @return array<string, string>
     */
    public function deviceOptions(): array
    {
        $slotDevice = $this->slotDevice();

        if ($slotDevice !== AdDevice::ALL) {
            return [$slotDevice->value => $slotDevice->label()];
        }

        return [
            AdDevice::ALL->value => AdDevice::ALL->label(),
            AdDevice::MOBILE->value => AdDevice::MOBILE->label(),
            AdDevice::DESKTOP->value => AdDevice::DESKTOP->label(),
        ];
    }

    private function slotDevice(): AdDevice
    {
        return $this->slotKey === null ? AdDevice::ALL : AdSlotCatalog::deviceOf($this->slotKey);
    }

    /**
     * Vorgabe einer neuen Belegung: das gezeigte Portal, das gezeigte Geraet,
     * Entwurf.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $device = AdDevice::tryFrom($this->device) ?? AdDevice::ALL;

        return [
            'ad_unit_id' => null,
            'device' => $device->narrowTo($this->slotDevice())->value,
            'applies_to_all_tenants' => false,
            'tenant_ids' => $this->tenantId === null ? [] : [$this->tenantId],
            'starts_at' => null,
            'ends_at' => null,
            'priority' => self::PRIORITY_TENANT,
            'status' => AdPlacementStatus::DRAFT->value,
        ];
    }

    /**
     * Waehlbare Werbemittel: alles, was nicht archiviert ist (#10). Ein
     * abgeschaltetes bleibt waehlbar — `is_active` ist der Not-Aus, keine
     * Aussage ueber die Eignung.
     *
     * @return array<int, string>
     */
    private function adUnitOptions(): array
    {
        return AdUnit::query()
            ->selectable()
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'is_active'])
            ->mapWithKeys(fn (AdUnit $unit): array => [
                (int) $unit->getKey() => sprintf(
                    '%s · %s%s',
                    $unit->name,
                    $unit->providerLabel(),
                    $unit->is_active ? '' : ' ('.__('abgeschaltet').')',
                ),
            ])
            ->all();
    }

    private function adUnitHint(mixed $unitId): ?string
    {
        if (! filled($unitId)) {
            return null;
        }

        $unit = AdUnit::query()->find((int) $unitId);

        if ($unit === null) {
            return null;
        }

        return $unit->is_active
            ? null
            : __('Dieses Werbemittel ist abgeschaltet und spielt nichts aus, solange der Not-Aus steht.');
    }

    /**
     * Alle Portale ausser den gewaehlten — „alle ausser Berlin" ist im Netz
     * der haeufigere Fall als eine Liste von zwanzig Haken.
     *
     * @param  array<int, mixed>  $selected
     * @return list<int>
     */
    private function invertTenants(array $selected): array
    {
        $gewaehlt = array_map('intval', array_filter($selected, fn ($id): bool => filled($id)));

        return array_values(array_diff(array_keys(AdsAdminPortal::names()), $gewaehlt));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAdUnit(array $data): int
    {
        abort_unless(AdsAdminAccess::allowed(), 403);

        // Pruefung und Normalisierung stecken im Model
        // ({@see AdProvider::validateConfig()}) — hier wird nichts doppelt
        // geprueft, damit es keine zweite Wahrheit gibt.
        $unit = new AdUnit([
            'name' => (string) ($data['name'] ?? ''),
            'provider' => (string) ($data['provider'] ?? ''),
            'config_json' => (array) ($data['config_json'] ?? []),
            'is_active' => true,
        ]);

        $unit->save();

        return (int) $unit->getKey();
    }

    private function findPlacement(int $id): ?AdPlacement
    {
        return AdPlacement::query()
            ->with(['adUnit', 'tenants:id,name'])
            ->where('slot_key', (string) $this->slotKey)
            ->whereKey($id)
            ->first();
    }

    /**
     * Der Rahmen im iframe soll sofort stimmen. Gemeldet wird deshalb nicht
     * der gespeicherte Stand, sondern die **neu aufgeloeste** Belegung des
     * Ankers (`fresh`): nach dem Pausieren kann eine andere Belegung
     * nachruecken, und nach dem Anlegen gewinnt nicht zwangslaeufig die
     * gerade gespeicherte (docs/ad-slots.md 4.3).
     */
    private function melde(AdPlacement $placement): void
    {
        $device = AdDevice::tryFrom($this->device) ?? AdDevice::MOBILE;

        $resolution = $this->slotKey === null || $this->tenantId === null
            ? null
            : app(AdSlotResolver::class)->resolution($this->slotKey, $this->tenantId, $device, fresh: true);

        $this->dispatch(
            'ad-editor-placement-updated',
            slotKey: $this->slotKey,
            device: $device->value,
            status: $placement->status->value,
            unitName: $resolution?->adUnitName,
            filled: $resolution !== null,
        );

        $hinweis = $this->deliveryHint($placement, $resolution, $device);

        if ($hinweis !== null) {
            Notification::make()
                ->title(__('Gespeichert, ausgespielt wird hier aber nichts.'))
                ->body($hinweis)
                ->warning()
                ->send();
        }
    }

    /**
     * Warum der Rahmen trotz aktiver Belegung leer bleibt — der Fall, in dem
     * jemand sonst im Portal sucht: der Hauptschalter des Portals steht aus
     * (`ad_settings.ads_enabled`), oder eine andere Belegung hat die hoehere
     * Prioritaet. Null heisst: es laeuft, was gerade gespeichert wurde.
     */
    private function deliveryHint(AdPlacement $placement, ?AdResolution $resolution, AdDevice $device): ?string
    {
        if (! $placement->status->isDeliverable() || $resolution?->placementId === (int) $placement->getKey()) {
            return null;
        }

        if ($this->tenantId !== null && ! app(AdSlotResolver::class)->adsEnabled($this->tenantId)) {
            return __('Dieses Portal hat Werbung nicht freigegeben — in den Werbe-Einstellungen steht „ads_enabled" aus. Bis dahin bleibt jeder Anker leer.');
        }

        if ($resolution !== null) {
            return __('Auf :device läuft weiterhin :name (Priorität :priority).', [
                'device' => $device->label(),
                'name' => $resolution->adUnitName,
                'priority' => $resolution->priority,
            ]);
        }

        if ($placement->hasExpired() || ($placement->starts_at !== null && $placement->starts_at->isFuture())) {
            return __('Der Zeitraum dieser Belegung läuft gerade nicht.');
        }

        if ($placement->adUnit?->is_active === false) {
            return __('Das Werbemittel ist abgeschaltet (Not-Aus) und spielt nichts aus.');
        }

        return __('Auf :device wird dieser Anker nicht ausgespielt — prüfe Gerät und Portal-Auswahl.', [
            'device' => $device->label(),
        ]);
    }
}
