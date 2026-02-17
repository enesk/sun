<?php

namespace App\Filament\Dashboard\Resources\Cities;

use App\Filament\Dashboard\Resources\Cities\Pages\CreateCity;
use App\Filament\Dashboard\Resources\Cities\Pages\EditCity;
use App\Filament\Dashboard\Resources\Cities\Pages\ListCities;
use App\Models\Portal\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedMapPin;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Portal');
    }

    public static function getNavigationLabel(): string
    {
        return __('Städte');
    }

    public static function getModelLabel(): string
    {
        return __('Stadt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Städte');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label(__('Stadtname')),
                    TextInput::make('zipcode')
                        ->maxLength(10)
                        ->label(__('PLZ')),
                    TextInput::make('administrative_area_level_1')
                        ->maxLength(255)
                        ->label(__('Bundesland')),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Städte und Orte verwalten.'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Stadt'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('zipcode')
                    ->label(__('PLZ'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('administrative_area_level_1')
                    ->label(__('Bundesland'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('companies_count')
                    ->counts('companies')
                    ->label(__('Firmen'))
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('administrative_area_level_1')
                    ->options(fn () => City::query()
                        ->whereNotNull('administrative_area_level_1')
                        ->distinct()
                        ->pluck('administrative_area_level_1', 'administrative_area_level_1')
                        ->toArray())
                    ->label(__('Bundesland')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
