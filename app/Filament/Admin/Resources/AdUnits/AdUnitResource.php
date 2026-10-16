<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdUnits;

use App\Ads\AdProviderRegistry;
use App\Ads\Forms\ProviderConfigForm;
use App\Ads\Models\AdUnit;
use App\Ads\Support\AdsAdminAccess;
use App\Filament\Admin\Resources\AdUnits\Pages\CreateAdUnit;
use App\Filament\Admin\Resources\AdUnits\Pages\EditAdUnit;
use App\Filament\Admin\Resources\AdUnits\Pages\ListAdUnits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Werbemittel (`ad_units`, Ticket #10; docs/ad-slots.md 4.2/5).
 *
 * Das Formular kennt **keinen** Provider: die providerspezifischen Felder
 * kommen aus {@see \App\Ads\Contracts\AdProvider::configSchema()} und landen
 * in `config_json`. Ein dritter Provider (docs 5.3) ist damit eine Klasse plus
 * eine Zeile in `config/ad-slots.php` — diese Resource bleibt unberuehrt.
 *
 * Loeschen und Archivieren:
 *
 * * `ad_placements.ad_unit_id` haengt per ON DELETE CASCADE am Werbemittel.
 *   Loeschen nimmt also jede Belegung mit, auch die laufende — deshalb ist es
 *   nur erlaubt, solange **keine** Belegung daran haengt
 *   ({@see AdUnit::isDeletable()}).
 * * Alles andere wird archiviert: `archived_at` wird gesetzt, `is_active`
 *   fallt mit (Model-Zusicherung), die Belegungen bleiben nachvollziehbar und
 *   das Werbemittel ist in der Auswahl eines neuen Placements nicht mehr
 *   waehlbar ({@see AdUnit::scopeSelectable()}).
 *
 * Vorschau: AdSense wird nie geladen — eine Impression auf einer
 * Verwaltungsseite ist bei Google ein ungueltiger Vorfall (docs 6). Dort steht
 * der Platzhalter mit Anzeigenblock-ID und Format. Eigenes HTML laeuft in
 * einem `<iframe sandbox>` ueber die eigene Route
 * ({@see \App\Ads\Http\Controllers\AdUnitPreviewController}), nie im DOM des
 * Panels.
 */
class AdUnitResource extends Resource
{
    protected static ?string $model = AdUnit::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'werbemittel';

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Werbemittel');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Werbemittel');
    }

    public static function getModelLabel(): string
    {
        return __('Werbemittel');
    }

    /** Wie die uebrigen Werbe-Seiten: nur Betreiber des Netzes. */
    public static function canAccess(): bool
    {
        return AdsAdminAccess::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Werbemittel'))
                    ->description(__('Ein Werbemittel wird einmal gepflegt; welche Portale es ausspielen, steht in der Belegung.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Bezeichnung'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('Nur für die Verwaltung, z. B. „AdSense Konto A · Rechteck".')),
                        Select::make('provider')
                            ->label(__('Art'))
                            ->options(fn (): array => app(AdProviderRegistry::class)->options())
                            ->required()
                            ->live()
                            ->helperText(__('Die Art entscheidet, welche Felder unten stehen und wie ausgespielt wird.')),
                        Toggle::make('is_active')
                            ->label(__('Aktiv'))
                            ->default(true)
                            ->disabled(fn (?AdUnit $record): bool => $record?->isArchived() === true)
                            ->helperText(__('Not-Aus über alle Belegungen hinweg. Aus: der Anker bleibt leer, die Belegungen bleiben bestehen.'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Angaben der gewählten Art'))
                    ->description(fn (Get $get): string => static::providerHint($get('provider')))
                    ->schema(fn (Get $get): array => static::configFields($get('provider')))
                    ->visible(fn (Get $get): bool => filled($get('provider'))),

                Section::make(__('Vorschau'))
                    ->description(__('AdSense wird hier nicht geladen; eigenes HTML läuft in einem abgeschotteten Rahmen.'))
                    ->schema([
                        View::make('filament.admin.ads.unit-preview')
                            ->viewData(fn (Get $get, ?AdUnit $record): array => static::previewData($get, $record)),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('provider'))),
            ])
            ->columns(1);
    }

    /**
     * Filament-Felder aus dem Schema des Providers — gebaut von
     * {@see ProviderConfigForm}, damit das Inline-Anlegen im
     * Konfigurationspanel (#8) dieselben Felder bekommt. Die Felder stehen in
     * einer Section, deren `schema`-Closure auf den gewaehlten Provider
     * reagiert: alle Provider gleichzeitig zu rendern und wegzuschalten wuerde
     * bei gleichnamigen Feldern (`format` hat jeder) denselben Statuspfad
     * doppelt belegen.
     *
     * @return list<Select|Textarea|TextInput>
     */
    public static function configFields(mixed $provider): array
    {
        return ProviderConfigForm::fields($provider);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Bezeichnung'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (AdUnit $record): ?string => $record->isArchived()
                        ? __('archiviert am :date', ['date' => $record->archived_at?->format(config('app.date_format', 'd.m.Y'))])
                        : null),
                TextColumn::make('provider')
                    ->label(__('Art'))
                    ->badge()
                    ->formatStateUsing(fn (AdUnit $record): string => $record->providerLabel())
                    ->sortable(),
                TextColumn::make('config_summary')
                    ->label(__('Angaben'))
                    ->state(fn (AdUnit $record): string => static::summary($record))
                    ->wrap(),
                TextColumn::make('placements_count')
                    ->label(__('Verwendung'))
                    ->counts('placements')
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn (mixed $state): string => (int) $state === 0
                        ? __('nicht verwendet')
                        : trans_choice('verwendet in :count Placement|verwendet in :count Placements', (int) $state, ['count' => (int) $state])),
                IconColumn::make('is_active')
                    ->label(__('Aktiv'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label(__('Art'))
                    ->options(fn (): array => app(AdProviderRegistry::class)->options()),
                TernaryFilter::make('is_active')
                    ->label(__('Aktiv')),
                TernaryFilter::make('archiviert')
                    ->label(__('Archiv'))
                    ->placeholder(__('nur nicht archivierte'))
                    ->trueLabel(__('nur archivierte'))
                    ->falseLabel(__('nur nicht archivierte'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('archived_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('archived_at'),
                        // Ohne Auswahl bleibt das Archiv aussen vor: es waechst
                        // mit jedem Umbau und soll die Liste nicht fuellen.
                        blank: fn (Builder $query): Builder => $query->whereNull('archived_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                static::previewAction(),
                static::archiveAction(),
                static::unarchiveAction(),
                DeleteAction::make()
                    ->visible(fn (AdUnit $record): bool => $record->isDeletable()),
            ])
            ->toolbarActions([])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('Noch kein Werbemittel'))
            ->emptyStateDescription(__('Ein Werbemittel wird zentral gepflegt und über Placements auf Werbeplätze gelegt.'));
    }

    /**
     * Vorschau als Modal — derselbe Inhalt wie im Formular, damit die Liste
     * nicht erst in die Bearbeitung fuehrt.
     */
    public static function previewAction(): Action
    {
        return Action::make('vorschau')
            ->label(__('Vorschau'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (AdUnit $record): string => __('Vorschau: :name', ['name' => $record->name]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'))
            ->schema([
                View::make('filament.admin.ads.unit-preview')
                    ->viewData(fn (AdUnit $record): array => static::previewDataFor($record)),
            ]);
    }

    public static function archiveAction(): Action
    {
        return Action::make('archivieren')
            ->label(__('Archivieren'))
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->visible(fn (AdUnit $record): bool => ! $record->isArchived())
            ->requiresConfirmation()
            ->modalHeading(__('Werbemittel archivieren'))
            ->modalDescription(fn (AdUnit $record): string => __('Das Werbemittel wird abgeschaltet und ist in neuen Placements nicht mehr wählbar. Bestehende :count Placements bleiben stehen, spielen aber nichts mehr aus.', [
                'count' => $record->placements()->count(),
            ]))
            ->action(function (AdUnit $record): void {
                $record->archive();

                Notification::make()
                    ->title(__('Werbemittel archiviert.'))
                    ->success()
                    ->send();
            });
    }

    public static function unarchiveAction(): Action
    {
        return Action::make('reaktivieren')
            ->label(__('Aus dem Archiv holen'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (AdUnit $record): bool => $record->isArchived())
            ->requiresConfirmation()
            ->modalHeading(__('Werbemittel aus dem Archiv holen'))
            ->modalDescription(__('Das Werbemittel ist danach wieder wählbar, bleibt aber abgeschaltet — anschalten ist ein eigener Schritt.'))
            ->action(function (AdUnit $record): void {
                $record->unarchive();

                Notification::make()
                    ->title(__('Werbemittel ist wieder wählbar.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Daten der Vorschau aus dem **Formularstand** — so zeigt die Vorschau
     * auch eine gerade eingetragene Anzeigenblock-ID. Der Sandbox-Rahmen kann
     * nur den gespeicherten Stand zeigen: er laedt ueber eine Route, und die
     * kennt nur die Datenbank.
     *
     * @return array<string, mixed>
     */
    public static function previewData(Get $get, ?AdUnit $record): array
    {
        $provider = is_string($get('provider')) ? $get('provider') : null;
        $config = (array) ($get('config_json') ?? []);

        return [
            'provider' => $provider,
            'providerLabel' => $provider === null
                ? null
                : (app(AdProviderRegistry::class)->get($provider)?->label() ?? $provider),
            'config' => $config,
            'formatLabel' => static::formatLabel($config['format'] ?? null),
            'previewUrl' => static::previewUrl($record),
            'unsaved' => $record === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function previewDataFor(AdUnit $record): array
    {
        $config = (array) $record->config_json;

        return [
            'provider' => (string) $record->provider,
            'providerLabel' => $record->providerLabel(),
            'config' => $config,
            'formatLabel' => static::formatLabel($config['format'] ?? null),
            'previewUrl' => static::previewUrl($record),
            'unsaved' => false,
        ];
    }

    protected static function previewUrl(?AdUnit $record): ?string
    {
        if ($record === null || $record->getKey() === null || $record->html() === null) {
            return null;
        }

        // Abdruck des Markups in der URL: so zeigt der Rahmen nach dem
        // Speichern sofort den neuen Stand.
        return route('admin.ads.unit-preview', [
            'adUnit' => $record->getKey(),
            'v' => substr(sha1((string) $record->html()), 0, 8),
        ]);
    }

    protected static function formatLabel(mixed $format): ?string
    {
        if (! is_string($format) || $format === '') {
            return null;
        }

        return (string) config("ad-slots.formats.{$format}.label", $format);
    }

    /** Kurzfassung der providerspezifischen Angaben fuer die Tabelle. */
    protected static function summary(AdUnit $record): string
    {
        $config = (array) $record->config_json;
        $parts = [];

        if ($record->adsenseSlotId() !== null) {
            $parts[] = __('Block :id', ['id' => $record->adsenseSlotId()]);
        }

        if (($config['width'] ?? null) && ($config['height'] ?? null)) {
            $parts[] = sprintf('%d×%d px', (int) $config['width'], (int) $config['height']);
        }

        $format = static::formatLabel($config['format'] ?? null);

        if ($format !== null) {
            $parts[] = $format;
        }

        return $parts === [] ? '–' : implode(' · ', $parts);
    }

    protected static function providerHint(mixed $provider): string
    {
        return ProviderConfigForm::hint($provider);
    }

    /**
     * Ein Werbemittel in Verwendung wird archiviert, nicht geloescht — die
     * Datenbank wuerde seine Belegungen per Cascade mitnehmen.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof AdUnit && $record->isDeletable();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdUnits::route('/'),
            'create' => CreateAdUnit::route('/create'),
            'edit' => EditAdUnit::route('/{record}/edit'),
        ];
    }
}
