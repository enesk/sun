<?php

namespace App\Filament\Dashboard\Resources\Companies;

use App\Filament\Dashboard\Resources\Companies\Pages\CreateCompany;
use App\Filament\Dashboard\Resources\Companies\Pages\EditCompany;
use App\Filament\Dashboard\Resources\Companies\Pages\ListCompanies;
use App\Enums\PlanTier;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Tenant;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyPlanService;
use App\Services\Premium\PremiumAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
                Tabs::make('company')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('Stammdaten'))
                            ->schema(self::baseFormComponents()),
                        Tab::make(__('Plan'))
                            ->schema(self::planFormComponents())
                            ->visible(fn (?Company $record): bool => $record !== null && (bool) auth()->user()?->isAdmin()),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function baseFormComponents(): array
    {
        return [
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
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->label(__('Titelbild / Banner'))
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('3:1')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('400')
                            ->maxSize(5120)
                            ->helperText(__('Max. 5 MB — Empfohlen: 1200×400px (3:1). Wird als Banner auf der Firmenseite angezeigt.')),
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
        ];
    }

    /**
     * Plan-Uebersicht (#18); geaendert wird nur ueber planAction().
     *
     * @return array<int, Component>
     */
    private static function planFormComponents(): array
    {
        return [
            Section::make(__('Aktueller Plan'))
                ->schema([
                    TextEntry::make('plan_overview_tier')
                        ->label(__('Stufe'))
                        ->badge()
                        ->state(fn (?Company $record): string => ($record?->plan_tier ?? PlanTier::Free)->label()),
                    TextEntry::make('plan_overview_effective')
                        ->label(__('Wirksame Stufe'))
                        ->badge()
                        ->state(fn (?Company $record): string => $record ? app(CompanyEntitlementService::class)->effectiveTier($record)->label() : '—'),
                    TextEntry::make('plan_overview_source')
                        ->label(__('Herkunft'))
                        ->state(fn (?Company $record): string => self::planSource($record)),
                    TextEntry::make('plan_overview_started')
                        ->label(__('Beginn'))
                        ->state(fn (?Company $record): ?string => $record?->plan_started_at?->format(config('app.datetime_format')))
                        ->placeholder('—'),
                    TextEntry::make('plan_overview_ends')
                        ->label(__('Laufzeit bis'))
                        ->state(fn (?Company $record): ?string => $record?->plan_ends_at?->format(config('app.datetime_format')))
                        ->placeholder(__('unbegrenzt')),
                    TextEntry::make('plan_overview_grace')
                        ->label(__('Grace Period bis'))
                        ->state(fn (?Company $record): ?string => $record?->plan_grace_until?->format(config('app.datetime_format')))
                        ->placeholder('—'),
                ])
                ->columns(3)
                ->footerActions([
                    self::planAction(),
                ]),
        ];
    }

    /**
     * Plan ohne Stripe setzen oder verlaengern (Testkunden, Partner, Kulanz).
     */
    public static function planAction(): Action
    {
        return Action::make('setPlanManually')
            ->label(__('Plan manuell setzen'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
            ->modalDescription(fn (Company $record): ?string => self::runningSubscriptionHint($record))
            ->fillForm(fn (Company $record): array => [
                'tier' => ($record->plan_tier ?? PlanTier::Free)->value,
                'ends_at' => $record->plan_ends_at?->toDateString(),
            ])
            ->schema([
                Select::make('tier')
                    ->label(__('Stufe'))
                    ->options(PlanTier::options())
                    ->required()
                    ->live(),
                DatePicker::make('ends_at')
                    ->label(__('Enddatum'))
                    ->helperText(__('Der Plan läuft am Ende dieses Tages aus (premium:process-expirations).'))
                    ->minDate(now()->toDateString())
                    ->required(fn (Get $get): bool => $get('tier') !== PlanTier::Free->value)
                    ->visible(fn (Get $get): bool => $get('tier') !== PlanTier::Free->value),
                Textarea::make('note')
                    ->label(__('Grund'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (Company $record, array $data, PremiumAdminService $service): void {
                $tier = PlanTier::from((string) $data['tier']);
                $endsAt = filled($data['ends_at'] ?? null) ? Carbon::parse((string) $data['ends_at']) : null;

                $service->setPlanManually($record, $tier, $endsAt, trim((string) $data['note']), auth()->user());

                Notification::make()->success()->title(__('Plan gesetzt: :tier', ['tier' => $tier->label()]))->send();
            });
    }

    private static function planSource(?Company $record): string
    {
        if ($record === null || ($record->plan_tier ?? PlanTier::Free) === PlanTier::Free) {
            return '—';
        }

        return $record->subscription_ref === null
            ? __('manuell')
            : __('Subscription #:id', ['id' => $record->subscription_ref]);
    }

    private static function runningSubscriptionHint(Company $record): ?string
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $subscription = app(CompanyPlanService::class)->currentSubscription($tenant, $record);

        if ($subscription === null) {
            return __('Manuell gesetzte Pläne haben keine Subscription und laufen zum Enddatum aus.');
        }

        return __('Achtung: Subscription #:id läuft noch. Der nächste Stripe-Webhook überschreibt den manuellen Plan; ggf. zuerst die Subscription kündigen.', ['id' => $subscription->getKey()]);
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
                TextColumn::make('plan_tier')
                    ->label(__('Plan'))
                    ->badge()
                    ->formatStateUsing(fn (?PlanTier $state): string => ($state ?? PlanTier::Free)->label())
                    ->description(fn (Company $record): ?string => $record->plan_ends_at?->format(config('app.date_format')))
                    ->sortable()
                    ->visible(fn () => auth()->user()->isAdmin()),
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
                SelectFilter::make('plan_tier')
                    ->options(PlanTier::options())
                    ->label(__('Plan'))
                    ->visible(fn () => auth()->user()->isAdmin()),
                TernaryFilter::make('is_premium')
                    ->label(__('Premium'))
                    ->visible(fn () => auth()->user()->isAdmin()),
                TernaryFilter::make('is_verified')
                    ->label(__('Verifiziert'))
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->recordActions([
                EditAction::make(),
                self::planAction(),
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
