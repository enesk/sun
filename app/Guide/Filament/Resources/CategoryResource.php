<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources;

use App\Guide\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Guide\Import\ImportPreview;
use App\Guide\Models\Category;
use App\Guide\Services\CategoryAdminService;
use App\Guide\Services\TopicDirectory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use LogicException;

/**
 * Reiter "Kategorien" unter Themen (#15, design/guide-dashboard.md §6).
 *
 * Mit gewaehltem Portal eine Zeile je Kategorie, sortierbar per Ziehgriff
 * und ↑/↓. Bei "Alle Portale" eine Zeile je Slug; Aenderungen wirken dann in
 * allen Portalen, die die Kategorie haben, sortiert wird je Portal.
 *
 * Umbenennen aendert nie die Adresse. "Adresse aendern …" verlangt eine
 * ausdrueckliche Bestaetigung und hinterlaesst eine 301 von der alten
 * Adresse (Category::booted, guide_redirects).
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $slug = 'themen/kategorien';

    protected static bool $shouldRegisterNavigation = false;

    public const MIN_TOPICS_FOR_PAGE = ImportPreview::MIN_CATEGORY_TOPICS;

    public static function getModelLabel(): string
    {
        return __('Kategorie');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kategorien');
    }

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
        ];
    }

    /**
     * Zeilen der Tabelle: je Portal-Kategorie oder je Slug.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function rows(): Collection
    {
        $directory = app(TopicDirectory::class);

        return collect($directory->isNetworkWide() ? $directory->categoriesBySlug() : $directory->categories())
            ->keyBy('__key');
    }

    /**
     * Schluessel "<tenant-id>-<id>", auf die eine Zeile wirkt.
     *
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    public static function keysOf(array $record): array
    {
        if (isset($record['members'])) {
            return collect($record['members'])
                ->map(fn (int $id, int $tenantId): string => TopicDirectory::key($tenantId, $id))
                ->values()
                ->all();
        }

        return [(string) $record['__key']];
    }

    public static function table(Table $table): Table
    {
        $network = app(TopicDirectory::class)->isNetworkWide();

        return $table
            ->records(fn (): array => static::rows()->all())
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('name')
                    ->label(__('Kategorie'))
                    ->weight('medium')
                    ->description(fn (array $record): string => '/ratgeber/kategorie/'.$record['slug']
                        .($record['published'] > 0 && $record['published_since'] !== null
                            ? ' · '.__('Adresse fest seit :date', ['date' => Carbon::parse($record['published_since'])->timezone(config('guide.timezone'))->format('d.m.Y')])
                            : ''))
                    ->icon(fn (array $record): ?string => $record['published'] > 0 ? 'heroicon-m-lock-closed' : null)
                    ->tooltip(fn (array $record): ?string => isset($record['names']) && count($record['names']) > 1
                        ? __('Abweichende Namen: :names', ['names' => implode(', ', $record['names'])])
                        : null),
                TextColumn::make('portals')
                    ->label(__('Portale'))
                    ->visible($network)
                    ->formatStateUsing(fn (int $state, array $record): string => "{$state} / {$record['portal_total']}"),
                TextColumn::make('topics')
                    ->label(__('Themen'))
                    ->formatStateUsing(fn (array $record): string => __(':active aktiv · :drafts Entwurf', ['active' => $record['active'], 'drafts' => $record['drafts']])),
                TextColumn::make('active')
                    ->label(__('Eigene Seite'))
                    ->formatStateUsing(fn (int $state): string => $state >= self::MIN_TOPICS_FOR_PAGE
                        ? __('ja')
                        : __('nein — weniger als :min aktive Themen', ['min' => self::MIN_TOPICS_FOR_PAGE])),
                TextColumn::make('last_changed_at')
                    ->label(__('Jüngste Aktualisierung'))
                    ->date('d.m.Y', config('guide.timezone'))
                    ->placeholder('–'),
            ])
            // Hinweisband nach denselben Regeln wie im Import (§4.3).
            ->description(fn (): ?HtmlString => static::hintsBand())
            ->reorderable('position', ! $network)
            ->paginated(false)
            ->headerActions([
                static::createAction(),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label(__('Nach oben'))
                    ->icon('heroicon-m-arrow-up')
                    ->iconButton()
                    ->color('gray')
                    ->visible(! $network)
                    ->action(fn (array $record, ListCategories $livewire) => $livewire->move($record['__key'], -1)),
                Action::make('moveDown')
                    ->label(__('Nach unten'))
                    ->icon('heroicon-m-arrow-down')
                    ->iconButton()
                    ->color('gray')
                    ->visible(! $network)
                    ->action(fn (array $record, ListCategories $livewire) => $livewire->move($record['__key'], 1)),
                ActionGroup::make([
                    static::editAction(),
                    static::slugAction(),
                    Action::make('delete')
                        ->label(__('Löschen'))
                        ->icon('heroicon-o-trash')
                        ->color('status-failed')
                        ->disabled(fn (array $record): bool => $record['topics'] > 0)
                        ->tooltip(fn (array $record): ?string => $record['topics'] > 0 ? __('Nur leere Kategorien lassen sich löschen.') : null)
                        ->requiresConfirmation()
                        ->modalDescription(fn (array $record): string => __('Die Kategorie „:name“ wird gelöscht:portals.', [
                            'name' => $record['name'],
                            'portals' => isset($record['tenant_names']) ? ' '.__('in :portals', ['portals' => implode(', ', $record['tenant_names'])]) : '',
                        ]))
                        ->action(function (array $record): void {
                            try {
                                app(CategoryAdminService::class)->delete(static::keysOf($record));
                            } catch (LogicException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();

                                return;
                            }

                            Notification::make()->title(__('Kategorie gelöscht.'))->success()->send();
                        }),
                ])->label(__('Aktionen'))->tooltip(__('Aktionen')),
            ])
            ->emptyStateHeading(__('Noch keine Kategorien'))
            ->emptyStateDescription(__('Der Import legt fehlende Kategorien automatisch an; hier lassen sich weitere anlegen.'));
    }

    public static function hintsBand(): ?HtmlString
    {
        $counts = static::rows()
            ->mapWithKeys(fn (array $row): array => [$row['name'] => (int) $row['topics']])
            ->all();

        $hints = $counts !== [] ? app(ImportPreview::class)->hints($counts) : [];

        if ($hints === []) {
            return null;
        }

        return new HtmlString(view('content.guide.partials.hints', ['hints' => $hints])->render());
    }

    public static function createAction(): Action
    {
        return Action::make('create')
            ->label(__('Kategorie anlegen'))
            ->icon('heroicon-o-plus')
            ->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => blank($get('slug')) ? $set('slug', CategoryAdminService::slugify((string) $state)) : null),
                TextInput::make('slug')
                    ->label(__('Adresse'))
                    ->prefix('/ratgeber/kategorie/')
                    ->required()
                    ->maxLength(120)
                    ->helperText(__('Nach der Anlage nur noch mit ausdrücklicher Bestätigung änderbar.')),
                ...self::metaFields(),
                CheckboxList::make('tenants')
                    ->label(__('Portale'))
                    ->options(fn (): array => app(TopicDirectory::class)->tenants()->mapWithKeys(fn ($tenant): array => [(string) $tenant->getKey() => (string) $tenant->name])->all())
                    ->default(fn (): array => app(TopicDirectory::class)->tenants()->keys()->map(fn (int $id): string => (string) $id)->all())
                    ->visible(fn (): bool => app(TopicDirectory::class)->isNetworkWide())
                    ->bulkToggleable()
                    ->columns(2)
                    ->required(fn (): bool => app(TopicDirectory::class)->isNetworkWide()),
            ])
            ->action(function (array $data): void {
                $directory = app(TopicDirectory::class);
                $tenants = $directory->isNetworkWide()
                    ? $directory->tenants()->only(array_map('intval', (array) ($data['tenants'] ?? [])))
                    : $directory->tenants();

                try {
                    $result = app(CategoryAdminService::class)->create($tenants, $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(trans_choice('{0} Keine Kategorie angelegt|{1} Kategorie angelegt|[2,*] Kategorie in :count Portalen angelegt', $result['done'], ['count' => $result['done']]))
                    ->body($result['skipped'] > 0 ? trans_choice('{1} Ein Portal hat diese Adresse schon.|[2,*] :count Portale haben diese Adresse schon.', $result['skipped'], ['count' => $result['skipped']]) : null)
                    ->success()
                    ->send();
            });
    }

    public static function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('Umbenennen, Beschreibung, Meta …'))
            ->icon('heroicon-o-pencil-square')
            ->fillForm(fn (array $record): array => [
                'name' => $record['name'],
                'description' => $record['description'],
                'meta_title' => $record['meta_title'],
                'meta_description' => $record['meta_description'],
            ])
            ->modalDescription(fn (array $record): string => __('Die Adresse /ratgeber/kategorie/:slug bleibt.', ['slug' => $record['slug']])
                .(isset($record['tenant_names']) ? ' '.__('Wirkt in: :portals.', ['portals' => implode(', ', $record['tenant_names'])]) : ''))
            ->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(120),
                ...self::metaFields(),
            ])
            ->action(function (array $data, array $record): void {
                app(CategoryAdminService::class)->update(static::keysOf($record), $data);

                Notification::make()->title(__('Kategorie gespeichert.'))->success()->send();
            });
    }

    /**
     * Neue Adresse nur mit ausdruecklicher Bestaetigung; die alte leitet
     * danach dauerhaft (301) um.
     */
    public static function slugAction(): Action
    {
        return Action::make('changeSlug')
            ->label(__('Adresse ändern …'))
            ->icon('heroicon-o-link')
            ->color('status-review')
            ->modalHeading(__('Adresse der Kategorie ändern'))
            ->modalDescription(fn (array $record): string => __('Die Kategorieseite ist heute unter /ratgeber/kategorie/:slug erreichbar. Nach der Änderung leitet diese Adresse dauerhaft (301) auf die neue um; Suchmaschinen brauchen einige Zeit, bis sie die neue Adresse übernehmen.', ['slug' => $record['slug']])
                .($record['published'] > 0 ? ' '.__('Die Kategorie hat bereits veröffentlichte Artikel.') : ''))
            ->fillForm(fn (array $record): array => ['slug' => $record['slug']])
            ->schema([
                TextInput::make('slug')
                    ->label(__('Neue Adresse'))
                    ->prefix('/ratgeber/kategorie/')
                    ->required()
                    ->maxLength(120),
                Checkbox::make('confirmed')
                    ->label(__('Ich möchte die Adresse ändern. Die bisherige Adresse leitet dauerhaft auf die neue um.'))
                    ->accepted()
                    ->validationMessages(['accepted' => __('Bitte bestätigen Sie die Änderung der Adresse.')]),
            ])
            ->modalSubmitActionLabel(__('Adresse ändern'))
            ->action(function (array $data, array $record): void {
                try {
                    app(CategoryAdminService::class)->changeSlug(static::keysOf($record), (string) $data['slug']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(__('Adresse geändert.'))
                    ->body(__('/ratgeber/kategorie/:old leitet jetzt dauerhaft auf /ratgeber/kategorie/:new um.', [
                        'old' => $record['slug'],
                        'new' => CategoryAdminService::slugify((string) $data['slug']),
                    ]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private static function metaFields(): array
    {
        return [
            Textarea::make('description')
                ->label(__('Beschreibung'))
                ->rows(3)
                ->maxLength(1000)
                ->helperText(__('Erscheint als Einleitung auf der Kategorieseite.')),
            TextInput::make('meta_title')
                ->label(__('Meta-Titel'))
                ->maxLength(255)
                ->helperText(__('Empfohlen bis 60 Zeichen; leer = Name der Kategorie.')),
            Textarea::make('meta_description')
                ->label(__('Meta-Beschreibung'))
                ->rows(2)
                ->maxLength(500)
                ->helperText(__('Empfohlen bis 155 Zeichen.')),
        ];
    }
}
