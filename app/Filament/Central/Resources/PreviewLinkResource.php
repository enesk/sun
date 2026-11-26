<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PreviewLinkResource\Pages\CreatePreviewLink;
use App\Filament\Central\Resources\PreviewLinkResource\Pages\ListPreviewLinks;
use App\Models\Tenant;
use App\Preview\Models\PreviewLink;
use App\Preview\PreviewTokenService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Verwaltung der Vorschau-Links (SUN-PV-002, Ticket #3).
 *
 * Der Klartext-Token existiert nur im Moment der Erzeugung. Er reist als
 * Argument der Action {@see self::urlsAction()} in den Dialog und ist mit
 * dem Schliessen des Dialogs aus dem Livewire-State verschwunden — in der
 * Datenbank steht nur der Hash.
 *
 * Geloescht wird nichts: widerrufene und abgelaufene Links bleiben zur
 * Nachvollziehbarkeit in der Liste.
 */
class PreviewLinkResource extends Resource
{
    /** Laengste erlaubte Laufzeit in Tagen. */
    public const MAX_DAYS = 90;

    /** Vorgabe fuer "Gueltig bis" in Tagen ab heute. */
    public const DEFAULT_DAYS = 14;

    protected static ?string $model = PreviewLink::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-eye';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'vorschau-links';

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Vorschau-Links');
    }

    public static function getPluralLabel(): string
    {
        return __('Vorschau-Links');
    }

    public static function getModelLabel(): string
    {
        return __('Vorschau-Link');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->placeholder(__('z. B. Präsentation Kunde X'))
                ->required()
                ->maxLength(255),
            Toggle::make('applies_to_all_tenants')
                ->label(__('Alle Portale'))
                ->default(false)
                ->live(),
            Select::make('tenant_ids')
                // 'Portale' nicht durch __(): siehe ProfileRequestResource.
                ->label('Portale')
                ->multiple()
                ->searchable()
                ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                ->hidden(fn (Get $get): bool => (bool) $get('applies_to_all_tenants'))
                ->required(fn (Get $get): bool => ! $get('applies_to_all_tenants')),
            self::expiresOnField()
                ->default(today()->addDays(self::DEFAULT_DAYS)),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tenants'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('applies_to_all_tenants')
                    ->label('Portale')
                    ->formatStateUsing(fn (PreviewLink $record): string => $record->applies_to_all_tenants
                        ? __('Alle')
                        : (string) $record->tenants->count())
                    ->tooltip(fn (PreviewLink $record): ?string => $record->applies_to_all_tenants
                        ? null
                        : $record->tenants->sortBy('name')->pluck('name')->implode(', ')),
                TextColumn::make('expires_at')
                    ->label(__('Gültig bis'))
                    ->date((string) config('app.date_format', 'd.m.Y'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(fn (PreviewLink $record): string => self::status($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state]),
                TextColumn::make('use_count')
                    ->label(__('Aufrufe'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label(__('Zuletzt genutzt'))
                    ->dateTime((string) config('app.datetime_format', 'd.m.Y H:i'))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(self::statusOptions())
                    ->default('active')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->whereNull('revoked_at')->where('expires_at', '>', now()),
                        'expired' => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
                        'revoked' => $query->whereNotNull('revoked_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                self::revokeAction(),
                self::extendAction(),
                self::regenerateAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('Keine Vorschau-Links'))
            ->emptyStateDescription(__('Mit einem Vorschau-Link sehen eingeladene Besucher ein Portal im Theme sun-v2 ohne Werbung.'));
    }

    /**
     * Dialog mit den fertigen URLs. Argumente: `record` (Link-ID) und
     * `token` (Klartext) — beides nur, solange der Dialog offen ist.
     */
    public static function urlsAction(): Action
    {
        return Action::make('previewUrls')
            ->modalHeading(__('Vorschau-Link ist bereit'))
            ->modalDescription(__('Kopiere die URLs jetzt. Der Link wird nur in diesem Dialog vollständig angezeigt und lässt sich später nicht mehr abrufen.'))
            ->modalContent(function (array $arguments) {
                $link = PreviewLink::query()->with('tenants')->find($arguments['record'] ?? null);

                return view('filament.central.preview-link-urls', [
                    'urls' => $link === null ? [] : self::urls($link, (string) ($arguments['token'] ?? '')),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'));
    }

    /**
     * Eine URL je Portal, auf der Hauptdomain des Portals.
     *
     * @return array<string, string> Portalname => URL
     */
    public static function urls(PreviewLink $link, string $token): array
    {
        $tenants = $link->applies_to_all_tenants ? Tenant::query()->get() : $link->tenants;

        return $tenants
            ->filter(fn (Tenant $tenant): bool => filled($tenant->domain))
            ->sortBy('name')
            ->mapWithKeys(fn (Tenant $tenant): array => [
                (string) $tenant->name => "https://{$tenant->domain}/vorschau/{$token}",
            ])
            ->all();
    }

    public static function expiresOnField(): DatePicker
    {
        return DatePicker::make('expires_on')
            ->label(__('Gültig bis'))
            ->native(false)
            ->displayFormat('d.m.Y')
            ->minDate(today())
            ->maxDate(today()->addDays(self::MAX_DAYS))
            ->required();
    }

    /**
     * Gilt bis einschliesslich des gewaehlten Tages.
     */
    public static function expiresAt(mixed $date): Carbon
    {
        return Carbon::parse($date)->endOfDay();
    }

    public static function status(PreviewLink $record): string
    {
        if ($record->revoked_at !== null) {
            return 'revoked';
        }

        return $record->expires_at->isFuture() ? 'active' : 'expired';
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'active' => __('aktiv'),
            'expired' => __('abgelaufen'),
            'revoked' => __('widerrufen'),
        ];
    }

    private static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('Widerrufen'))
            ->icon('heroicon-m-no-symbol')
            ->color('danger')
            ->visible(fn (PreviewLink $record): bool => $record->revoked_at === null)
            ->requiresConfirmation()
            ->modalHeading(__('Vorschau-Link widerrufen?'))
            ->modalDescription(__('Der Link und alle damit gesetzten Vorschau-Cookies werden ungültig. Das lässt sich nicht rückgängig machen.'))
            ->modalSubmitActionLabel(__('Widerrufen'))
            ->action(function (PreviewLink $record): void {
                $record->forceFill(['revoked_at' => now()])->save();

                app(PreviewTokenService::class)->forgetCached($record);

                Notification::make()->title(__('Vorschau-Link widerrufen'))->success()->send();
            });
    }

    private static function extendAction(): Action
    {
        return Action::make('extend')
            ->label(__('Verlängern'))
            ->icon('heroicon-m-calendar-days')
            ->color('gray')
            ->visible(fn (PreviewLink $record): bool => $record->revoked_at === null)
            ->modalHeading(__('Vorschau-Link verlängern'))
            ->modalDescription(__('Wer den Link schon geöffnet hat, ruft ihn nach dem bisherigen Ablaufdatum einmal neu auf.'))
            ->fillForm(fn (PreviewLink $record): array => [
                'expires_on' => $record->expires_at->isFuture()
                    ? $record->expires_at->toDateString()
                    : today()->addDays(self::DEFAULT_DAYS)->toDateString(),
            ])
            ->schema([self::expiresOnField()])
            ->modalSubmitActionLabel(__('Speichern'))
            ->action(function (PreviewLink $record, array $data): void {
                $record->forceFill(['expires_at' => self::expiresAt($data['expires_on'])])->save();

                app(PreviewTokenService::class)->forgetCached($record);

                Notification::make()->title(__('Gültigkeit geändert'))->success()->send();
            });
    }

    private static function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label(__('Neu erzeugen'))
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->visible(fn (PreviewLink $record): bool => $record->revoked_at === null)
            ->requiresConfirmation()
            ->modalHeading(__('Neuen Token erzeugen?'))
            ->modalDescription(__('Die bisherigen URLs und alle damit gesetzten Vorschau-Cookies werden ungültig. Danach siehst du die neuen URLs.'))
            ->modalSubmitActionLabel(__('Neu erzeugen'))
            ->action(function (PreviewLink $record, Action $action): void {
                $token = app(PreviewTokenService::class)->regenerate($record);

                $action->getLivewire()->replaceMountedAction('previewUrls', [
                    'record' => $record->getKey(),
                    'token' => $token,
                ]);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreviewLinks::route('/'),
            'create' => CreatePreviewLink::route('/create'),
        ];
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
