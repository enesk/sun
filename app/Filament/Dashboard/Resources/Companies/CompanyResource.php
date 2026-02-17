<?php

namespace App\Filament\Dashboard\Resources\Companies;

use App\Filament\Dashboard\Resources\Companies\Pages\CreateCompany;
use App\Filament\Dashboard\Resources\Companies\Pages\EditCompany;
use App\Filament\Dashboard\Resources\Companies\Pages\ListCompanies;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use App\Models\Portal\CompanyOpeningHour;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationGroup(): ?string
    {
        return __('Portal');
    }

    public static function getNavigationLabel(): string
    {
        return __('Firmen');
    }

    public static function getModelLabel(): string
    {
        return __('Firma');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Firmen');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Firmendaten'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label(__('Firmenname')),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->label(__('Slug'))
                            ->helperText(__('Wird automatisch generiert wenn leer.')),
                        Textarea::make('description')
                            ->rows(4)
                            ->label(__('Beschreibung')),
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->label(__('Kategorien')),
                    ])->columns(2),

                Section::make(__('Adresse'))
                    ->schema([
                        TextInput::make('street')
                            ->maxLength(255)
                            ->label(__('Straße')),
                        TextInput::make('house_no')
                            ->maxLength(20)
                            ->label(__('Hausnummer')),
                        TextInput::make('zipcode')
                            ->maxLength(10)
                            ->label(__('PLZ')),
                        Select::make('city_id')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->label(__('Stadt')),
                    ])->columns(2),

                Section::make(__('Kontakt'))
                    ->schema([
                        TextInput::make('tel')
                            ->tel()
                            ->maxLength(50)
                            ->label(__('Telefon')),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->label(__('E-Mail')),
                        TextInput::make('website')
                            ->url()
                            ->maxLength(255)
                            ->label(__('Website')),
                    ])->columns(3),

                Section::make(__('Bilder'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->label(__('Logo'))
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('300')
                            ->imageResizeTargetHeight('300')
                            ->maxSize(2048)
                            ->helperText(__('Max. 2 MB — PNG, JPEG oder WebP. Wird auf 300x300px zugeschnitten.')),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->label(__('Galerie'))
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(10)
                            ->maxSize(2048)
                            ->helperText(__('Bis zu 10 Bilder, je max. 2 MB.')),
                    ])->columns(1),

                Section::make(__('Öffnungszeiten'))
                    ->schema([
                        Repeater::make('openingHours')
                            ->relationship()
                            ->schema([
                                Select::make('day_of_week')
                                    ->options(CompanyOpeningHour::DAYS)
                                    ->required()
                                    ->label(__('Tag')),
                                TimePicker::make('opens_at')
                                    ->seconds(false)
                                    ->label(__('Öffnet')),
                                TimePicker::make('closes_at')
                                    ->seconds(false)
                                    ->label(__('Schließt')),
                                Checkbox::make('is_closed')
                                    ->label(__('Geschlossen')),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel(__('Tag hinzufügen'))
                            ->reorderable(false)
                            ->label(''),
                    ])->collapsible(),

                Section::make(__('Status'))
                    ->schema([
                        Checkbox::make('is_active')
                            ->default(true)
                            ->label(__('Aktiv')),
                    ]),

                Section::make(__('Admin-Einstellungen'))
                    ->schema([
                        TextInput::make('google_places_id')
                            ->maxLength(255)
                            ->label(__('Google Places ID')),
                        TextInput::make('rating')
                            ->numeric()
                            ->disabled()
                            ->label(__('Bewertung')),
                        TextInput::make('rating_count')
                            ->numeric()
                            ->disabled()
                            ->label(__('Anzahl Bewertungen')),
                        Checkbox::make('is_premium')
                            ->label(__('Premium')),
                        Checkbox::make('is_verified')
                            ->label(__('Verifiziert')),
                        Select::make('user_id')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload()
                            ->label(__('Inhaber')),
                    ])->columns(3)
                    ->visible(fn () => auth()->user()->isAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Alle eingetragenen Firmen verwalten.'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->sortable()
                    ->searchable()
                    ->limit(40),
                TextColumn::make('city.name')
                    ->label(__('Stadt'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('zipcode')
                    ->label(__('PLZ'))
                    ->searchable(),
                TextColumn::make('categories.name')
                    ->label(__('Kategorien'))
                    ->badge()
                    ->limit(30),
                TextColumn::make('rating')
                    ->label(__('Bewertung'))
                    ->sortable(),
                TextColumn::make('rating_count')
                    ->label(__('Reviews'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Aktiv')),
                IconColumn::make('is_premium')
                    ->boolean()
                    ->label(__('Premium'))
                    ->visible(fn () => auth()->user()->isAdmin()),
                TextColumn::make('created_at')
                    ->label(__('Erstellt'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Stadt')),
                SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Kategorie')),
                TernaryFilter::make('is_active')
                    ->label(__('Aktiv')),
                TernaryFilter::make('is_premium')
                    ->label(__('Premium'))
                    ->visible(fn () => auth()->user()->isAdmin()),
                TernaryFilter::make('is_verified')
                    ->label(__('Verifiziert'))
                    ->visible(fn () => auth()->user()->isAdmin()),
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
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
