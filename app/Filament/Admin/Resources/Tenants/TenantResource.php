<?php

namespace App\Filament\Admin\Resources\Tenants;

use App\Constants\TenantConfigConstants;
use App\Filament\Admin\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use App\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Filament\Admin\Resources\Tenants\RelationManagers\OrdersRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Models\Tenant;
use App\Models\User;
use App\Themes\ThemeManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tenants');
    }

    public static function getPluralLabel(): string
    {
        return __('Tenants');
    }

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tenant')
                    ->tabs([
                        Tabs\Tab::make(__('Allgemein'))
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->label(__('Name'))
                                    ->maxLength(255),
                                TextInput::make('domain')
                                    ->label(__('Domain'))
                                    ->placeholder('firmenfreund.test')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('Die Domain über die der Tenant erreichbar ist (z.B. firmenfreund.de)')),
                                Select::make('created_by')
                                    ->getSearchResultsUsing(fn (string $search): array => User::where('name', 'like', "%{$search}%")->limit(20)->pluck('name', 'id')->toArray())
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->label(__('Created By'))
                                    ->default(auth()->id())
                                    ->searchable(),
                            ]),

                        Tabs\Tab::make(__('Branding'))
                            ->schema([
                                Section::make(__('Farben'))
                                    ->schema([
                                        ColorPicker::make(TenantConfigConstants::PRIMARY_COLOR)
                                            ->label(__('Primärfarbe'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::PRIMARY_COLOR]),
                                        ColorPicker::make(TenantConfigConstants::SECONDARY_COLOR)
                                            ->label(__('Sekundärfarbe'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::SECONDARY_COLOR]),
                                        ColorPicker::make(TenantConfigConstants::ACCENT_COLOR)
                                            ->label(__('Akzentfarbe'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::ACCENT_COLOR]),
                                    ])
                                    ->columns(3),

                                Section::make(__('Logo & Bilder'))
                                    ->schema([
                                        FileUpload::make(TenantConfigConstants::LOGO_PATH)
                                            ->label(__('Logo'))
                                            ->image()
                                            ->directory('tenants/branding')
                                            ->disk('public')
                                            ->maxSize(2048)
                                            ->helperText(__('Max. 2 MB, empfohlen: PNG oder SVG')),
                                        FileUpload::make(TenantConfigConstants::FAVICON_PATH)
                                            ->label(__('Favicon'))
                                            ->image()
                                            ->directory('tenants/branding')
                                            ->disk('public')
                                            ->maxSize(512)
                                            ->helperText(__('Max. 512 KB, empfohlen: 32x32 oder 64x64 PNG')),
                                        FileUpload::make(TenantConfigConstants::OG_IMAGE_PATH)
                                            ->label(__('OG-Image (Social Media)'))
                                            ->image()
                                            ->directory('tenants/branding')
                                            ->disk('public')
                                            ->maxSize(2048)
                                            ->helperText(__('Wird bei Social-Media-Shares angezeigt. Empfohlen: 1200x630px')),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('SEO & Texte'))
                            ->schema([
                                TextInput::make(TenantConfigConstants::SITE_TITLE)
                                    ->label(__('Seitentitel'))
                                    ->maxLength(70)
                                    ->helperText(__('Wird im Browser-Tab und bei Google angezeigt')),
                                Textarea::make(TenantConfigConstants::SITE_DESCRIPTION)
                                    ->label(__('Meta-Description'))
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->helperText(__('Wird bei Google als Beschreibung angezeigt (max. 160 Zeichen)')),
                                TextInput::make(TenantConfigConstants::META_KEYWORDS)
                                    ->label(__('Meta-Keywords'))
                                    ->maxLength(255)
                                    ->helperText(__('Kommagetrennte Keywords')),
                                Textarea::make(TenantConfigConstants::FOOTER_TEXT)
                                    ->label(__('Footer-Text'))
                                    ->rows(2)
                                    ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::FOOTER_TEXT])
                                    ->helperText(__('Platzhalter: {year}, {tenant_name}')),
                            ]),

                        Tabs\Tab::make(__('Kontakt & Social Media'))
                            ->schema([
                                Section::make(__('Kontakt'))
                                    ->schema([
                                        TextInput::make(TenantConfigConstants::CONTACT_EMAIL)
                                            ->label(__('E-Mail'))
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make(TenantConfigConstants::CONTACT_PHONE)
                                            ->label(__('Telefon'))
                                            ->tel()
                                            ->maxLength(50),
                                        Textarea::make(TenantConfigConstants::CONTACT_ADDRESS)
                                            ->label(__('Adresse'))
                                            ->rows(3),
                                    ])
                                    ->columns(2),

                                Section::make(__('Social Media'))
                                    ->schema([
                                        TextInput::make(TenantConfigConstants::SOCIAL_FACEBOOK)
                                            ->label('Facebook')
                                            ->url()
                                            ->maxLength(255)
                                            ->prefix('https://'),
                                        TextInput::make(TenantConfigConstants::SOCIAL_INSTAGRAM)
                                            ->label('Instagram')
                                            ->url()
                                            ->maxLength(255)
                                            ->prefix('https://'),
                                        TextInput::make(TenantConfigConstants::SOCIAL_TWITTER)
                                            ->label('X / Twitter')
                                            ->url()
                                            ->maxLength(255)
                                            ->prefix('https://'),
                                        TextInput::make(TenantConfigConstants::SOCIAL_LINKEDIN)
                                            ->label('LinkedIn')
                                            ->url()
                                            ->maxLength(255)
                                            ->prefix('https://'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make(__('Features & Analytics'))
                            ->schema([
                                Section::make(__('Feature-Toggles'))
                                    ->schema([
                                        Toggle::make(TenantConfigConstants::REVIEWS_ENABLED)
                                            ->label(__('Bewertungen aktiviert'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::REVIEWS_ENABLED])
                                            ->helperText(__('Nutzer können Firmen bewerten')),
                                        Toggle::make(TenantConfigConstants::REGISTRATION_ENABLED)
                                            ->label(__('Registrierung aktiviert'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::REGISTRATION_ENABLED])
                                            ->helperText(__('Firmeninhaber können sich registrieren und Einträge erstellen')),
                                        Toggle::make(TenantConfigConstants::PREMIUM_LISTINGS_ENABLED)
                                            ->label(__('Premium-Einträge aktiviert'))
                                            ->default(TenantConfigConstants::DEFAULTS[TenantConfigConstants::PREMIUM_LISTINGS_ENABLED])
                                            ->helperText(__('Kostenpflichtige Premium-Einträge verfügbar')),
                                    ]),

                                Section::make(__('Analytics'))
                                    ->schema([
                                        TextInput::make(TenantConfigConstants::GOOGLE_ANALYTICS_ID)
                                            ->label(__('Google Analytics ID'))
                                            ->placeholder('G-XXXXXXXXXX')
                                            ->maxLength(20),
                                        TextInput::make(TenantConfigConstants::GOOGLE_TAG_MANAGER_ID)
                                            ->label(__('Google Tag Manager ID'))
                                            ->placeholder('GTM-XXXXXXX')
                                            ->maxLength(20),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make(__('Theme'))
                            ->schema(static::themeTabSchema()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function themeTabSchema(): array
    {
        $themeManager = app(ThemeManager::class);
        $themes = $themeManager->discover();

        $themeOptions = $themes->mapWithKeys(fn ($theme) => [
            $theme->slug => $theme->name . ' (v' . $theme->version . ')',
        ])->toArray();

        $schema = [
            Section::make(__('Theme-Auswahl'))
                ->schema([
                    Select::make(ThemeManager::TENANT_THEME_KEY)
                        ->label(__('Aktives Theme'))
                        ->options($themeOptions)
                        ->default(ThemeManager::DEFAULT_THEME)
                        ->required()
                        ->live()
                        ->helperText(__('Das Theme bestimmt das Aussehen des Portals')),

                    Placeholder::make('theme_info')
                        ->label(__('Theme-Info'))
                        ->content(function ($get) use ($themeManager): string {
                            $slug = $get(ThemeManager::TENANT_THEME_KEY) ?? ThemeManager::DEFAULT_THEME;
                            $theme = $themeManager->get($slug);
                            if (! $theme) {
                                return __('Theme nicht gefunden.');
                            }

                            return $theme->description . ' — ' . __('Autor') . ': ' . ($theme->author ?: 'Team');
                        }),
                ]),

            Section::make(__('Theme-Optionen'))
                ->schema(function ($get) use ($themeManager): array {
                    $slug = $get(ThemeManager::TENANT_THEME_KEY) ?? ThemeManager::DEFAULT_THEME;
                    $theme = $themeManager->get($slug);

                    if (! $theme || empty($theme->options)) {
                        return [
                            Placeholder::make('no_options')
                                ->label('')
                                ->content(__('Dieses Theme hat keine konfigurierbaren Optionen.')),
                        ];
                    }

                    $fields = [];
                    foreach ($theme->options as $key => $config) {
                        $fieldName = ThemeManager::TENANT_THEME_OPTIONS_KEY . '.' . $key;
                        $label = $config['label'] ?? $key;

                        $fields[] = match ($config['type'] ?? 'string') {
                            'boolean' => Toggle::make($fieldName)
                                ->label(__($label))
                                ->default($config['default'] ?? false),

                            'select' => Select::make($fieldName)
                                ->label(__($label))
                                ->options(
                                    collect($config['choices'] ?? [])
                                        ->mapWithKeys(fn ($choice) => [$choice => $choice])
                                        ->toArray()
                                )
                                ->default($config['default'] ?? null),

                            default => TextInput::make($fieldName)
                                ->label(__($label))
                                ->default($config['default'] ?? ''),
                        };
                    }

                    return $fields;
                })
                ->columns(2),
        ];

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('domain')
                    ->label(__('Domain'))
                    ->searchable()
                    ->url(fn ($record) => $record->domain ? 'https://' . $record->domain : null, shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('subscriptions_count')
                    ->counts('subscriptions')
                    ->label(__('Subscriptions'))
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label(__('Orders'))
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label(__('Users'))
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label(__('UUID'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            SubscriptionsRelationManager::class,
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
