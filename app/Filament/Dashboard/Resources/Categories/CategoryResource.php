<?php

namespace App\Filament\Dashboard\Resources\Categories;

use App\Filament\Dashboard\Resources\Categories\Pages\CreateCategory;
use App\Filament\Dashboard\Resources\Categories\Pages\EditCategory;
use App\Filament\Dashboard\Resources\Categories\Pages\ListCategories;
use App\Models\Portal\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedTag;

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
        return __('Kategorien');
    }

    public static function getModelLabel(): string
    {
        return __('Kategorie');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kategorien');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label(__('Name')),
                    TextInput::make('slug')
                        ->maxLength(255)
                        ->label(__('Slug'))
                        ->helperText(__('Wird automatisch generiert wenn leer.')),
                    Textarea::make('description')
                        ->rows(3)
                        ->label(__('Beschreibung')),
                    TextInput::make('icon')
                        ->maxLength(50)
                        ->label(__('Icon'))
                        ->helperText(__('CSS-Klasse oder Icon-Name.')),
                    Select::make('parent_id')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->preload()
                        ->label(__('Übergeordnete Kategorie'))
                        ->placeholder(__('Keine (Hauptkategorie)')),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label(__('Sortierung')),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Kategorien für das Branchenverzeichnis verwalten.'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label(__('Übergeordnet'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('icon')
                    ->label(__('Icon'))
                    ->placeholder('—'),
                TextColumn::make('sort_order')
                    ->label(__('Sortierung'))
                    ->sortable(),
                TextColumn::make('companies_count')
                    ->counts('companies')
                    ->label(__('Firmen'))
                    ->sortable(),
                TextColumn::make('children_count')
                    ->counts('children')
                    ->label(__('Unterkategorien'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Übergeordnete Kategorie')),
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
