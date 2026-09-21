<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\FeaturedPlacements;

use App\Constants\FeaturedPlacementStatus;
use App\Exceptions\FeaturedPlacementUnavailableException;
use App\Filament\Dashboard\Resources\FeaturedPlacements\Pages\ListFeaturedPlacements;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\FeaturedPlacement;
use App\Services\Premium\FeaturedPlacementService;
use App\Services\Premium\PremiumAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top-Platzierungen je Stadt x Branche (#18), nur fuer Administratoren.
 * Vergabe und Beenden laufen ueber PremiumAdminService (Log premium-admin).
 */
class FeaturedPlacementResource extends Resource
{
    protected static ?string $model = FeaturedPlacement::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedStar;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Portal';
    }

    public static function getNavigationLabel(): string
    {
        return __('Top-Platzierungen');
    }

    public static function getModelLabel(): string
    {
        return __('Top-Platzierung');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Top-Platzierungen');
    }

    /**
     * Belegte Slots der Kombination als Subquery, damit "frei" ohne N+1 auskommt
     * (Index fp_city_category_status_index).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('featured_placements.*')
            ->addSelect([
                'combination_taken' => FeaturedPlacement::query()
                    ->from('featured_placements', 'fp')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('fp.city_id', 'featured_placements.city_id')
                    ->whereColumn('fp.category_id', 'featured_placements.category_id')
                    ->where('fp.status', FeaturedPlacementStatus::ACTIVE->value),
            ])
            ->with(['company:id,name', 'city:id,name,zipcode', 'category:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Top-Platzierungen je Stadt und Branche; max. :max aktive Slots pro Kombination.', ['max' => app(FeaturedPlacementService::class)->maxSlots()]))
            ->columns([
                TextColumn::make('city.name')
                    ->label(__('Stadt'))
                    ->description(fn (FeaturedPlacement $record): ?string => $record->city?->getAttribute('zipcode'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label(__('Branche'))
                    ->sortable(),
                TextColumn::make('slot')
                    ->label(__('Slot'))
                    ->sortable(),
                TextColumn::make('company.name')
                    ->label(__('Betrieb'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (FeaturedPlacementStatus $state): string => $state->label())
                    ->color(fn (FeaturedPlacementStatus $state): string => $state === FeaturedPlacementStatus::ACTIVE ? 'success' : 'gray'),
                TextColumn::make('free_slots')
                    ->label(__('Freie Slots'))
                    ->state(fn (FeaturedPlacement $record): int => max(0, app(FeaturedPlacementService::class)->maxSlots() - (int) $record->getAttribute('combination_taken'))),
                TextColumn::make('subscription_ref')
                    ->label(__('Herkunft'))
                    ->formatStateUsing(fn (?string $state): string => __('Subscription #:id', ['id' => $state]))
                    ->placeholder(__('manuell')),
                TextColumn::make('starts_at')
                    ->label(__('Beginn'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('Ende'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('city_id')
                ->orderBy('category_id')
                ->orderBy('slot'))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(FeaturedPlacementStatus::cases())->mapWithKeys(fn (FeaturedPlacementStatus $s): array => [$s->value => $s->label()])->all())
                    ->default(FeaturedPlacementStatus::ACTIVE->value)
                    ->label(__('Status')),
                SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->label(__('Stadt')),
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Branche')),
            ])
            ->headerActions([
                self::assignAction(),
            ])
            ->recordActions([
                self::endAction(),
            ]);
    }

    public static function assignAction(): Action
    {
        return Action::make('assignSlot')
            ->label(__('Slot manuell vergeben'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalDescription(__('Ohne Add-on-Abo. Die Stufe des Betriebs muss die Top-Platzierung enthalten (ggf. zuerst den Plan setzen).'))
            ->schema([
                Select::make('company_id')
                    ->label(__('Betrieb'))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Company::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Company::query()->whereKey($value)->value('name'))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $company = $state ? Company::query()->find($state) : null;

                        $set('city_id', $company?->city_id);
                        $set('category_id', $company?->categories()->value('categories.id'));
                    }),
                Select::make('city_id')
                    ->label(__('Stadt'))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => City::query()
                        ->where('name', 'like', "{$search}%")
                        ->orWhere('zipcode', 'like', "{$search}%")
                        ->orderBy('name')
                        ->limit(50)
                        ->get(['id', 'name', 'zipcode'])
                        ->mapWithKeys(fn (City $city): array => [$city->id => self::cityLabel($city)])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($city = City::query()->find($value)) ? self::cityLabel($city) : null)
                    ->live(),
                Select::make('category_id')
                    ->label(__('Branche'))
                    ->required()
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->live()
                    ->helperText(fn (Get $get): ?string => $get('city_id') && $get('category_id')
                        ? __('Freie Slots: :free von :max', [
                            'free' => app(FeaturedPlacementService::class)->availableSlots((int) $get('city_id'), (int) $get('category_id')),
                            'max' => app(FeaturedPlacementService::class)->maxSlots(),
                        ])
                        : null),
                Textarea::make('note')
                    ->label(__('Grund'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (array $data, PremiumAdminService $service, Action $action): void {
                $company = Company::query()->findOrFail((int) $data['company_id']);

                try {
                    $placement = $service->assignSlotManually($company, (int) $data['city_id'], (int) $data['category_id'], trim((string) $data['note']), auth()->user());
                } catch (FeaturedPlacementUnavailableException $e) {
                    Notification::make()->danger()->title(__('Slot nicht vergeben'))->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title(__('Slot :slot vergeben', ['slot' => $placement->slot]))->send();
            });
    }

    public static function endAction(): Action
    {
        return Action::make('endPlacement')
            ->label(__('Beenden'))
            ->icon(Heroicon::OutlinedStop)
            ->color('danger')
            ->modalDescription(fn (FeaturedPlacement $record): string => $record->subscription_ref === null
                ? __('Der Slot wird sofort frei.')
                : __('Der Slot wird sofort frei, das Add-on-Abo #:id wird zum Periodenende gekündigt.', ['id' => $record->subscription_ref]))
            ->schema([
                Textarea::make('note')
                    ->label(__('Grund'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->visible(fn (FeaturedPlacement $record): bool => $record->status === FeaturedPlacementStatus::ACTIVE)
            ->action(function (FeaturedPlacement $record, array $data, PremiumAdminService $service): void {
                $service->endPlacement($record, trim((string) $data['note']), auth()->user());
                Notification::make()->success()->title(__('Top-Platzierung beendet'))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeaturedPlacements::route('/'),
        ];
    }

    private static function cityLabel(City $city): string
    {
        return trim("{$city->name} ({$city->zipcode})", ' ()');
    }
}
