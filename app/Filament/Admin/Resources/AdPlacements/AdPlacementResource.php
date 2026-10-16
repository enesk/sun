<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AdPlacements;

use App\Ads\AdProviderRegistry;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Models\AdPlacement;
use App\Ads\Observers\AdCacheInvalidator;
use App\Ads\Support\AdsAdminAccess;
use App\Ads\Support\AdSlotCatalog;
use App\Filament\Admin\Resources\AdPlacements\Pages\ListAdPlacements;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Alle Placements (`ad_placements`, Ticket #10).
 *
 * Die Tabelle ist die **Massen-Ansicht** neben dem visuellen Editor (#7/#8):
 * filterbar nach Werbeplatz, Portal, Status und Geraet, mit Bulk-Aktionen
 * Pausieren und Aktivieren. „Alle AdSense-Placements pausieren" ist damit ein
 * Filter plus eine Aktion statt 111 Klicks im Editor.
 *
 * Angelegt und bearbeitet werden Belegungen **nicht** hier, sondern im
 * Konfigurationspanel des Editors (#8): dort haengen Anker-Auswahl,
 * Geraeteverengung und Portal-Scope an der Vorschau, und zwei Formulare fuer
 * dieselbe Zeile liefen zwangslaeufig auseinander. Diese Resource ist deshalb
 * lesend — mit Ausnahme des Status.
 *
 * Status: nur die Uebergaenge, die {@see AdPlacementStatus::canTransitionTo()}
 * zulaesst. Eine abgelaufene Belegung wird nicht „aktiviert" — sie braucht
 * einen neuen Zeitraum, und den setzt #8.
 *
 * Cache: `save()` loest den Observer aus; beim Massenlauf wird jede Zeile
 * einzeln gespeichert, damit keine betroffene Portal-Karte stehen bleibt
 * (ein `update()` auf dem Query feuert keine Model-Ereignisse).
 */
class AdPlacementResource extends Resource
{
    protected static ?string $model = AdPlacement::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'placements';

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Alle Placements');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Placements');
    }

    public static function getModelLabel(): string
    {
        return __('Placement');
    }

    public static function canAccess(): bool
    {
        return AdsAdminAccess::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        // Gepflegt wird im Konfigurationspanel (#8), nicht hier.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_key')
                    ->label(__('Werbeplatz'))
                    ->formatStateUsing(fn (string $state): string => (string) (AdSlotCatalog::find($state)['label'] ?? $state))
                    // Ein routengebundener Anker steht nicht auf jeder Seite
                    // seines Seitentyps (Ticket #27) — das gehoert an die
                    // Belegung, nicht nur in den Katalog.
                    ->description(fn (AdPlacement $record): string => AdSlotCatalog::isRouteRestricted((string) $record->slot_key)
                        ? (string) $record->slot_key.' · '.__('nur :routes', ['routes' => implode(', ', AdSlotCatalog::routeLabelsOf((string) $record->slot_key))])
                        : (string) $record->slot_key)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('page_type')
                    ->label(__('Seitentyp'))
                    ->formatStateUsing(fn (AdPageType $state): string => $state->label())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('adUnit.name')
                    ->label(__('Werbemittel'))
                    ->description(fn (AdPlacement $record): string => $record->adUnit?->providerLabel() ?? '–')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('device')
                    ->label(__('Gerät'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (AdDevice $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (AdPlacementStatus $state, AdPlacement $record): string => $record->hasExpired()
                        ? AdPlacementStatus::EXPIRED->color()
                        : $state->color())
                    ->formatStateUsing(fn (AdPlacementStatus $state, AdPlacement $record): string => $record->hasExpired()
                        ? AdPlacementStatus::EXPIRED->label()
                        : $state->label())
                    ->sortable(),
                TextColumn::make('portale')
                    ->label(__('Portale'))
                    ->state(fn (AdPlacement $record): string => $record->applies_to_all_tenants
                        ? __('alle Portale')
                        : trans_choice(':count Portal|:count Portale', $record->tenants_count ?? 0, ['count' => $record->tenants_count ?? 0]))
                    ->description(fn (AdPlacement $record): ?string => $record->applies_to_all_tenants
                        ? null
                        : $record->tenants->pluck('name')->take(3)->implode(', ')),
                TextColumn::make('priority')
                    ->label(__('Priorität'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label(__('Zeitraum'))
                    ->state(fn (AdPlacement $record): string => static::schedule($record))
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('slot_key')
                    ->label(__('Werbeplatz'))
                    ->options(fn (): array => AdSlotCatalog::options())
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('page_type')
                    ->label(__('Seitentyp'))
                    ->options(fn (): array => AdPageType::options())
                    ->multiple(),
                SelectFilter::make('portal')
                    ->label('Portal')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    // Netzweite Belegungen gelten fuer jedes Portal und
                    // gehoeren deshalb in jedes Portal-Ergebnis — genau das
                    // macht der Scope am Model.
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<AdPlacement> $query */
                        return filled($data['value'] ?? null)
                            ? $query->forTenant((int) $data['value'])
                            : $query;
                    }),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(fn (): array => AdPlacementStatus::options())
                    ->multiple(),
                SelectFilter::make('device')
                    ->label(__('Gerät'))
                    ->options(fn (): array => AdDevice::options())
                    ->multiple(),
                SelectFilter::make('ad_unit_id')
                    ->label(__('Werbemittel'))
                    ->relationship('adUnit', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('provider')
                    ->label(__('Art des Werbemittels'))
                    ->options(fn (): array => app(AdProviderRegistry::class)->options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('adUnit', fn (Builder $q) => $q->where('provider', $data['value']))
                        : $query),
                TernaryFilter::make('applies_to_all_tenants')
                    ->label(__('Netzweit')),
            ])
            ->recordActions([
                static::statusAction('pausieren', AdPlacementStatus::PAUSED),
                static::statusAction('aktivieren', AdPlacementStatus::ACTIVE),
            ])
            ->toolbarActions([
                static::statusBulkAction('pausieren', AdPlacementStatus::PAUSED),
                static::statusBulkAction('aktivieren', AdPlacementStatus::ACTIVE),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('Keine Placements'))
            ->emptyStateDescription(__('Belegungen werden im Werbeplatz-Editor angelegt; hier stehen sie für Massenänderungen.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['adUnit', 'tenants:id,name'])
            ->withCount('tenants');
    }

    /** Status einer einzelnen Zeile. */
    protected static function statusAction(string $name, AdPlacementStatus $target): Action
    {
        return Action::make($name)
            ->label($target === AdPlacementStatus::PAUSED ? __('Pausieren') : __('Aktivieren'))
            ->icon($target === AdPlacementStatus::PAUSED ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color($target === AdPlacementStatus::PAUSED ? 'warning' : 'success')
            ->visible(fn (AdPlacement $record): bool => static::mayMove($record, $target))
            ->requiresConfirmation()
            ->action(function (AdPlacement $record) use ($target): void {
                static::move($record, $target);

                Notification::make()
                    ->title(__('Placement ist jetzt :status.', ['status' => $target->label()]))
                    ->success()
                    ->send();
            });
    }

    /** Derselbe Schritt fuer eine Auswahl — der Grund dieser Tabelle. */
    protected static function statusBulkAction(string $name, AdPlacementStatus $target): BulkAction
    {
        return BulkAction::make($name)
            ->label($target === AdPlacementStatus::PAUSED ? __('Pausieren') : __('Aktivieren'))
            ->icon($target === AdPlacementStatus::PAUSED ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color($target === AdPlacementStatus::PAUSED ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalHeading($target === AdPlacementStatus::PAUSED
                ? __('Ausgewählte Placements pausieren')
                : __('Ausgewählte Placements aktivieren'))
            ->modalDescription(__('Wirkt sofort: die betroffenen Portale bekommen eine neue Auflösung.'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($target): void {
                $moved = 0;

                foreach ($records as $record) {
                    if (! $record instanceof AdPlacement || ! static::mayMove($record, $target)) {
                        continue;
                    }

                    static::move($record, $target);
                    $moved++;
                }

                $skipped = $records->count() - $moved;

                Notification::make()
                    ->title(trans_choice(':count Placement geändert|:count Placements geändert', $moved, ['count' => $moved]))
                    ->body($skipped > 0
                        ? __(':count übersprungen — dieser Übergang ist aus dem aktuellen Status nicht erlaubt.', ['count' => $skipped])
                        : null)
                    ->success()
                    ->send();
            });
    }

    /**
     * Abgelaufene Belegungen bleiben aussen vor: `expired` kommt nur zurueck
     * in `draft`, und einen neuen Zeitraum setzt das Konfigurationspanel.
     */
    protected static function mayMove(AdPlacement $record, AdPlacementStatus $target): bool
    {
        if ($record->hasExpired()) {
            return false;
        }

        return $record->status->canTransitionTo($target);
    }

    protected static function move(AdPlacement $record, AdPlacementStatus $target): void
    {
        $record->status = $target;
        $record->save();

        // Der Observer greift bereits auf `saved`; der Pivot loest selbst kein
        // Ereignis aus, deshalb hier derselbe Aufruf wie in #8.
        AdCacheInvalidator::forPlacement($record);
    }

    protected static function schedule(AdPlacement $record): string
    {
        $format = config('app.date_format', 'd.m.Y');
        $from = $record->starts_at?->format($format);
        $to = $record->ends_at?->format($format);

        if ($from === null && $to === null) {
            return __('unbefristet');
        }

        return trim(($from ?? __('sofort')).' – '.($to ?? __('offen')));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdPlacements::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
