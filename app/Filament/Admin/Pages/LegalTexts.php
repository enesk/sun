<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\LegalTextKey;
use App\Models\Tenant;
use App\Services\LegalTextService;
use App\Services\TenantBrandingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Einstellungen › Rechtliche Texte (Ticket #17): eine Fassung fuer alle
 * Portale, zentral gepflegt.
 *
 * Vorher lag jeder Text als Kopie je Portal in der Tenant-Konfiguration. Das
 * waren 23 Kopien, die auseinandergelaufen sind — gemessen am 22.10.2026 fuenf
 * Generationen desselben Textes. Hier wird einmal gepflegt; die
 * portalspezifischen Angaben kommen beim Ausliefern ueber die Platzhalter
 * `[BETREIBER_*]` dazu ({@see TenantBrandingService::resolveLegalPlaceholders()}).
 *
 * **Die Auslieferung haengt am Inhalt, nicht an einem Schalter.** Solange ein
 * Text hier leer ist, liefern die Portale weiter ihren eigenen
 * ({@see \App\Http\Controllers\Portal\StaticPageController::resolveContent()}).
 * Deshalb braucht es keinen Umstellungszeitpunkt: Speichern genuegt, und ein
 * Leeren nimmt die Umstellung fuer diese Textart zurueck.
 *
 * Aenderungen greifen sofort — die Texte stehen in der Datenbank und werden
 * nicht zwischengespeichert (Begruendung in {@see LegalTextService}).
 *
 * @property-read Schema $form
 */
class LegalTexts extends Page
{
    protected string $view = 'filament.admin.pages.legal-texts';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'rechtstexte';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Rechtliche Texte');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Rechtliche Texte');
    }

    public function getSubheading(): ?string
    {
        return __('Eine Fassung für alle Portale. Änderungen wirken sofort.');
    }

    /**
     * Nur Betreiber: Administratoren mit dem Recht "update settings" — dieselbe
     * Huerde wie beim Bot-Schutz und bei den Werbe-Seiten. Wer hier schreibt,
     * aendert die Pflichtangaben aller Portale auf einmal.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof \App\Models\User
            && $user->isAdmin()
            && $user->hasPermissionTo('update settings');
    }

    public function mount(): void
    {
        $this->form->fill(app(LegalTextService::class)->all());
    }

    public function form(Schema $schema): Schema
    {
        $toolbar = [
            'bold', 'italic', 'underline',
            'h2', 'h3',
            'bulletList', 'orderedList',
            'link',
        ];

        return $schema
            ->components([
                Section::make(__('Impressum'))
                    ->description(__('Pflichtangaben gemäß § 5 DDG. Gilt für alle Portale.'))
                    ->schema([
                        RichEditor::make(LegalTextKey::IMPRESSUM->value)
                            ->label(__('Impressum'))
                            ->toolbarButtons($toolbar)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Datenschutzerklärung'))
                    ->description(__('Der Abschnitt zur Werbung wird je Portal automatisch angehängt, wenn dort Werbung läuft — er gehört nicht in diesen Text.'))
                    ->schema([
                        RichEditor::make(LegalTextKey::DATENSCHUTZ->value)
                            ->label(__('Datenschutzerklärung'))
                            ->toolbarButtons($toolbar)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Redaktionelle Grundsätze'))
                    ->description(__('Ausgegeben unter /ratgeber/redaktion, verlinkt aus jedem Ratgeber-Artikel. Leer lassen für den eingebauten Standardtext.'))
                    ->schema([
                        RichEditor::make(LegalTextKey::EDITORIAL_PRINCIPLES->value)
                            ->label(__('Redaktionelle Grundsätze'))
                            ->toolbarButtons($toolbar)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Fassung aus einem Portal übernehmen'))
                    ->description(__('Lädt die drei Texte eines Portals in die Felder oben — zum Übernehmen einer bestehenden Fassung, statt sie abzuschreiben. Gespeichert wird erst mit „Speichern".'))
                    ->collapsed()
                    ->schema([
                        Select::make('vorlage_tenant_id')
                            ->label('Portal')
                            ->options(fn (): array => $this->portalOptions())
                            ->searchable()
                            ->helperText(__('Die Felder oben werden überschrieben, sobald Sie hier ein Portal wählen.'))
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $this->loadFromTenant($state === null ? null : (int) $state, $set);
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $service = app(LegalTextService::class);
        $branding = app(TenantBrandingService::class);
        $userId = auth()->id();

        foreach (LegalTextKey::all() as $key) {
            // sanitizeLegalHtml() haelt die Platzhalter unversehrt; sie darf
            // genau einmal laufen, darum hier und nicht im Service.
            $service->set(
                $key,
                $branding->sanitizeLegalHtml($data[$key->value] ?? null),
                $userId,
            );
        }

        Notification::make()
            ->title(__('Rechtliche Texte gespeichert'))
            ->body($this->saveHint($service))
            ->success()
            ->send();
    }

    /**
     * Portale, deren Platzhalter nicht vollstaendig aufloesen — die zentrale
     * Fassung ergaebe dort eine Luecke in den Pflichtangaben.
     *
     * @return list<array{portal: string, fehlt: string}>
     */
    public function unresolvedPortals(): array
    {
        $branding = app(TenantBrandingService::class);
        $offen = [];

        foreach (Tenant::all() as $tenant) {
            $fehlt = [];

            if (! ($branding->legalAddressStatus($tenant)['hasAddress'] ?? false)) {
                $fehlt[] = __('Anschrift');
            }

            if (! $branding->get($tenant, \App\Constants\TenantConfigConstants::CONTACT_EMAIL)) {
                $fehlt[] = __('E-Mail');
            }

            if (! $branding->get($tenant, \App\Constants\TenantConfigConstants::CONTACT_PHONE)) {
                $fehlt[] = __('Telefon');
            }

            if ($fehlt !== []) {
                $offen[] = [
                    'portal' => (string) ($tenant->getAttribute('name') ?? $tenant->getKey()),
                    'fehlt' => implode(', ', $fehlt),
                ];
            }
        }

        return $offen;
    }

    /**
     * Welche Textarten zentral noch leer sind — dort liefern die Portale
     * weiter ihre eigene Fassung aus.
     *
     * @return list<string>
     */
    public function missingLabels(): array
    {
        return array_map(
            fn (LegalTextKey $key): string => $key->label(),
            app(LegalTextService::class)->missing(),
        );
    }

    /** @return array<string, string> Platzhalter => Erklaerung */
    public function placeholders(): array
    {
        return TenantBrandingService::LEGAL_PLACEHOLDERS;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vorschau')
                ->label(__('Impressum eines Portals ansehen'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => $this->firstPortalImpressumUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->firstPortalImpressumUrl() !== null),
        ];
    }

    /** @return array<int, string> */
    private function portalOptions(): array
    {
        return Tenant::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Tenant $t): array => [
                $t->getKey() => sprintf('%s (%s)', $t->getAttribute('name') ?? '—', $t->getKey()),
            ])
            ->all();
    }

    private function loadFromTenant(?int $tenantId, Set $set): void
    {
        if ($tenantId === null) {
            return;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $branding = app(TenantBrandingService::class);

        foreach (LegalTextKey::all() as $key) {
            $inhalt = $branding->get($tenant, $key->tenantConfigKey());
            $inhalt = is_string($inhalt) ? trim($inhalt) : null;

            // **Niemals einen leeren String in ein RichEditor-Feld setzen.**
            // Der Editor schickt den Wert durch Tiptap, und
            // Tiptap\Core\DOMParser::getDocumentBody() wirft bei '' oder
            // Leerraum einen TypeError ('Return value must be of type
            // DOMElement, null returned'). Die Seite bricht dann mit
            // "There was an error while attempting to load this page" ab.
            // `null` ist der Zustand, den der Editor als leer kennt.
            $set($key->value, $inhalt === '' ? null : $inhalt);
        }

        Notification::make()
            ->title(__('Fassung geladen'))
            ->body(__('Die Texte von :portal stehen jetzt in den Feldern. Prüfen und dann speichern.', [
                'portal' => (string) ($tenant->getAttribute('name') ?? $tenant->getKey()),
            ]))
            ->info()
            ->send();
    }

    private function saveHint(LegalTextService $service): string
    {
        $fehlend = $service->missing();

        if ($fehlend === []) {
            return __('Alle Portale liefern ab sofort diese Fassung aus.');
        }

        return __('Noch leer und daher weiter je Portal: :arten', [
            'arten' => implode(', ', array_map(fn (LegalTextKey $k): string => $k->label(), $fehlend)),
        ]);
    }

    private function firstPortalImpressumUrl(): ?string
    {
        $tenant = Tenant::query()->orderBy('id')->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $domain = trim((string) $tenant->getAttribute('domain'));

        return $domain === '' ? null : "https://{$domain}/impressum";
    }
}
