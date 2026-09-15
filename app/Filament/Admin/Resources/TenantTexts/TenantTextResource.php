<?php

namespace App\Filament\Admin\Resources\TenantTexts;

use App\Filament\Admin\Resources\TenantTexts\Pages\CreateTenantText;
use App\Filament\Admin\Resources\TenantTexts\Pages\EditTenantText;
use App\Filament\Admin\Resources\TenantTexts\Pages\ListTenantTexts;
use App\Models\Tenant;
use App\Models\TenantText;
use App\Support\Tenancy\TenantTerms;
use App\Support\Translation\PortalTextCatalog;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

/**
 * Tenant-Overrides einzelner Portaltexte aus lang/de/portal.php (#13).
 *
 * Die Tabelle zeigt nur vorhandene Overrides; "Auf Standard zuruecksetzen"
 * loescht den Datensatz. Den Cache leert TenantText selbst ueber seine
 * Model-Events.
 */
class TenantTextResource extends Resource
{
    protected static ?string $model = TenantText::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Texte');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Texte');
    }

    public static function getModelLabel(): string
    {
        return __('Text-Override');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Override'))
                    ->schema([
                        Select::make('tenant_id')
                            ->label(__('Tenant'))
                            ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabledOn('edit'),
                        Select::make('key')
                            ->label(__('Key'))
                            ->options(fn (): array => PortalTextCatalog::groupedOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->disableOptionWhen(fn (string $value): bool => ! static::mayEditKey($value))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('tenant_id', $get('tenant_id'))
                                    ->where('locale', PortalTextCatalog::LOCALE)
                                    ->where('group', PortalTextCatalog::GROUP),
                            )
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! PortalTextCatalog::exists($value)) {
                                        $fail(__('Diesen Key gibt es in lang/de/portal.php nicht.'));
                                    }

                                    if (! static::mayEditKey($value)) {
                                        $fail(__('Einwilligungstexte dürfen nur Administratoren überschreiben.'));
                                    }
                                },
                            ]),
                        Placeholder::make('default_text')
                            ->label(__('Standardtext'))
                            ->content(fn (Get $get): string => PortalTextCatalog::default($get('key')) ?? '–'),
                        Placeholder::make('available_placeholders')
                            ->label(__('Verfügbare Platzhalter'))
                            ->content(fn (Get $get): string => PortalTextCatalog::format(
                                PortalTextCatalog::availablePlaceholders($get('key')),
                            )),
                        Textarea::make('value')
                            ->label(__('Override'))
                            ->required()
                            ->rows(4)
                            ->live(onBlur: true)
                            ->rule('not_regex:/[<>]/')
                            ->validationMessages([
                                'not_regex' => __('HTML ist im Override nicht erlaubt.'),
                            ]),
                        Placeholder::make('warnings')
                            ->label(__('Warnungen'))
                            ->visible(fn (Get $get): bool => PortalTextCatalog::warnings($get('key'), $get('value')) !== [])
                            ->content(fn (Get $get): HtmlString => new HtmlString(implode('<br>', array_map(
                                'e',
                                PortalTextCatalog::warnings($get('key'), $get('value')),
                            )))),
                        Placeholder::make('preview')
                            ->label(__('Vorschau'))
                            ->helperText(__('Mit den Branchenbegriffen des gewählten Tenants und Beispielwerten für die übrigen Platzhalter.'))
                            ->content(fn (Get $get): string => PortalTextCatalog::preview(
                                $get('value'),
                                static::termsFor($get('tenant_id')),
                            )),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label(__('Tenant'))
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('Key'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label(__('Override'))
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label(__('Tenant'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                static::resetAction(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function resetAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label(__('Auf Standard zurücksetzen'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->modalHeading(__('Auf Standard zurücksetzen'))
            ->modalDescription(__('Der Override wird gelöscht, das Portal zeigt wieder den Standardtext.'))
            ->successNotificationTitle(__('Auf Standard zurückgesetzt'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! static::mayEditKey($record->getAttribute('key'))) {
            return Response::deny();
        }

        return parent::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! static::mayEditKey($record->getAttribute('key'))) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function mayEditKey(?string $key): bool
    {
        if (! PortalTextCatalog::isProtected($key)) {
            return true;
        }

        return (bool) auth()->user()?->hasRole(PortalTextCatalog::ADMIN_ROLE);
    }

    /**
     * @return array<string, string>
     */
    protected static function termsFor(mixed $tenantId): array
    {
        if (blank($tenantId)) {
            return TenantTerms::defaults();
        }

        return Tenant::query()->find($tenantId)?->terms ?? TenantTerms::defaults();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantTexts::route('/'),
            'create' => CreateTenantText::route('/create'),
            'edit' => EditTenantText::route('/{record}/edit'),
        ];
    }
}
