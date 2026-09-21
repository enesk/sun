<?php

namespace App\Filament\Dashboard\Resources\CityContents;

use App\Filament\Dashboard\Resources\CityContents\Pages\CreateCityContent;
use App\Filament\Dashboard\Resources\CityContents\Pages\EditCityContent;
use App\Filament\Dashboard\Resources\CityContents\Pages\ListCityContents;
use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use App\Models\Portal\CityContentTemplate;
use App\Services\Content\CityContentResolver;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Stadt-Overrides des Local Hubs (#11): Introtext, Stadtteile, FAQ.
 * Tenant-Vorlage: App\Filament\Dashboard\Pages\CityContentTemplates.
 */
class CityContentResource extends Resource
{
    protected static ?string $model = CityContent::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Portal';
    }

    public static function getNavigationLabel(): string
    {
        return 'Stadtinhalte';
    }

    public static function getModelLabel(): string
    {
        return 'Stadtinhalt';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Stadtinhalte';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Select::make('city_id')
                        ->label('Stadt')
                        ->relationship('city', 'name', fn ($query) => $query->named()->orderBy('name'))
                        ->getOptionLabelFromRecordUsing(fn (City $record): string => trim("{$record->name} {$record->zipcode}"))
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->live(),
                    Toggle::make('is_published')
                        ->label('Freigegeben')
                        ->helperText('Nicht freigegebene Overrides werden ignoriert, die Stadt zeigt dann die Tenant-Vorlage.')
                        ->default(true)
                        ->live(),
                ])->columns(2),

                Section::make([
                    RichEditor::make('intro_text')
                        ->label('Einleitung')
                        ->helperText('Leer = Einleitung aus der Tenant-Vorlage. Erlaubt: Absätze, fett, kursiv, Links, Listen. Platzhalter werden hier nicht ersetzt.')
                        ->toolbarButtons(CityContentResolver::INTRO_TOOLBAR)
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                    TagsInput::make('districts')
                        ->label('Stadtteile')
                        ->helperText('Wert für {districts} in der Tenant-Vorlage.')
                        ->placeholder('Stadtteil hinzufügen')
                        ->live(),
                    Repeater::make('faqs')
                        ->label('FAQ')
                        ->helperText('Leer = FAQ aus der Tenant-Vorlage. Antworten als Klartext, Absätze durch Leerzeile.')
                        ->schema(self::faqFields())
                        ->addActionLabel('Frage hinzufügen')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->live(onBlur: true),
                ])->heading('Inhalt'),

                Section::make([
                    View::make('filament.dashboard.partials.city-content-preview')
                        ->viewData(fn (Get $get): array => self::previewData($get)),
                ])->heading('Vorschau')
                    ->description('So wird der Block für diese Stadt aufgelöst.')
                    ->collapsible(),
            ])
            ->columns(1);
    }

    /**
     * Frage/Antwort-Felder, gemeinsam mit der Vorlagen-Seite.
     *
     * @return array<int, TextInput|Textarea>
     */
    public static function faqFields(): array
    {
        return [
            TextInput::make('question')
                ->label('Frage')
                ->required()
                ->maxLength(255),
            Textarea::make('answer')
                ->label('Antwort')
                ->required()
                ->rows(4)
                ->maxLength(3000),
        ];
    }

    /**
     * Speicherpfad aus Create- und Edit-Seite.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        $data['intro_text'] = CityContentResolver::sanitizeIntro($data['intro_text'] ?? null);
        $data['districts'] = CityContentResolver::normalizeDistricts($data['districts'] ?? null) ?: null;
        $data['faqs'] = CityContentResolver::normalizeFaqs($data['faqs'] ?? null) ?: null;

        return $data;
    }

    /**
     * @return array{city: ?City, content: ?array<string, mixed>}
     */
    private static function previewData(Get $get): array
    {
        $city = filled($get('city_id')) ? City::query()->find($get('city_id')) : null;

        if ($city === null) {
            return ['city' => null, 'content' => null];
        }

        $override = new CityContent([
            'intro_text' => $get('intro_text'),
            'districts' => $get('districts'),
            'faqs' => array_values((array) $get('faqs')),
            'is_published' => (bool) $get('is_published'),
        ]);

        return [
            'city' => $city,
            'content' => app(CityContentResolver::class)->resolve($city, $override, CityContentTemplate::current()),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Stadtspezifische Einleitung, Stadtteile und FAQ. Ohne Eintrag gilt die Tenant-Vorlage.')
            ->modifyQueryUsing(fn ($query) => $query->with('city'))
            ->columns([
                TextColumn::make('city.name')
                    ->label('Stadt')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('has_intro')
                    ->label('Einleitung')
                    ->boolean()
                    ->state(fn (CityContent $record): bool => filled($record->intro_text)),
                TextColumn::make('districts_count')
                    ->label('Stadtteile')
                    ->state(fn (CityContent $record): int => count($record->districts ?? [])),
                TextColumn::make('faqs_count')
                    ->label('FAQ')
                    ->state(fn (CityContent $record): int => count($record->faqs ?? [])),
                IconColumn::make('is_published')
                    ->label('Freigegeben')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Geändert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Freigegeben'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCityContents::route('/'),
            'create' => CreateCityContent::route('/create'),
            'edit' => EditCityContent::route('/{record}/edit'),
        ];
    }
}
