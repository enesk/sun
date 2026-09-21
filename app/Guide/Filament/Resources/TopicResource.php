<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources;

use App\Guide\Enums\RunDisplay;
use App\Guide\Enums\TopicStatus;
use App\Guide\Filament\Pages\ConfirmOutlines;
use App\Guide\Filament\Pages\ImportWizard;
use App\Guide\Filament\Pages\LegacyOverlaps;
use App\Guide\Filament\Resources\TopicResource\Pages\ListTopics;
use App\Guide\Filament\Resources\TopicResource\Pages\ViewTopic;
use App\Guide\Models\Topic;
use App\Guide\Services\TopicAdminService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Services\TopicRunStarter;
use App\Guide\Support\Usd;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Themen des Ratgebers (#15, design/guide-dashboard.md §5): Navigationspunkt
 * "Themen" mit den Reitern Themen · Kategorien · Import.
 *
 * Die Tabelle liest ueber TopicDirectory aus allen gewaehlten Portalen (eine
 * Zeile = ein Thema in einem Portal); Filter, Suche, Sortierung und
 * Seitenaufteilung passieren auf diesen Zeilen, weil es keine zentrale
 * Themen-Tabelle gibt. Aenderungen laufen ueber TopicAdminService.
 *
 * Detailansicht /themen/{tenant-id}-{topic-id} mit Gliederungs-Editor,
 * Fakten, Quellen und Laeufen (ViewTopic).
 */
class TopicResource extends Resource
{
    protected static ?string $model = Topic::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'themen';

    protected static ?string $recordTitleAttribute = 'question';

    public const PAGE_SIZE = 50;

    /** Prüfabstand-Auswahl in Tagen (design/guide-dashboard.md §9.1). */
    public const INTERVALS = [1, 3, 7, 14, 30];

    public static function getNavigationLabel(): string
    {
        return __('Themen');
    }

    public static function getModelLabel(): string
    {
        return __('Thema');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Themen');
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

    /**
     * Der Navigationspunkt bleibt auch unter Kategorien, Import, Altartikel
     * und "Gliederung bestaetigen" markiert.
     *
     * @return array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteBaseName().'.*',
            CategoryResource::getRouteBaseName().'.*',
            ImportWizard::getRouteName(),
            ConfirmOutlines::getRouteName(),
            LegacyOverlaps::getRouteName(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTopics::route('/'),
            // Nur "<tenant>-<thema>", damit /themen/kategorien usw. nie als
            // Thema gelesen wird.
            'view' => new PageRegistration(
                page: ViewTopic::class,
                route: fn (Panel $panel) => ViewTopic::route('/{record}')->registerRoute($panel)?->where('record', '[0-9]+-[0-9]+'),
            ),
        ];
    }

    public static function detailUrl(string $key, ?string $tab = null): string
    {
        return static::getUrl('view', array_filter(['record' => $key, 'reiter' => $tab]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(fn (ListTopics $livewire, ?string $search, ?string $sortColumn, ?string $sortDirection, int $page, int|string $recordsPerPage): LengthAwarePaginator => static::paginate(
                static::sorted(static::filtered($livewire, $search), $sortColumn, $sortDirection),
                $page,
                $recordsPerPage,
            ))
            ->resolveSelectedRecordsUsing(fn (ListTopics $livewire, array $keys, array $deselectedKeys, bool $isTrackingDeselectedKeys): Collection => $isTrackingDeselectedKeys
                ? static::filtered($livewire, $livewire->getTableSearch())->except($deselectedKeys)
                : static::filtered($livewire, $livewire->getTableSearch())->only($keys))
            ->columns([
                TextColumn::make('question')
                    ->label(__('Thema'))
                    // Kategorie, darunter ggf. "1 Quelle nicht erreichbar" (§5.7.2) —
                    // bewusst keine eigene Spalte.
                    ->description(fn (array $record): HtmlString => new HtmlString(view('content.guide.partials.cells.topic-category', ['record' => $record])->render()))
                    ->lineClamp(2)
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant_name')
                    // Ohne __(): "Portal" loest auf die Gruppe lang/de/portal.php auf.
                    ->label('Portal')
                    ->visible(fn (): bool => static::directory()->isNetworkWide())
                    ->sortable(),
                ViewColumn::make('status')
                    ->label(__('Thema-Status'))
                    ->view('content.guide.partials.cells.topic-status')
                    ->sortable(),
                ViewColumn::make('run_display')
                    ->label(__('Letzter Lauf'))
                    ->view('content.guide.partials.cells.run-status')
                    ->sortable(),
                TextColumn::make('last_changed_at')
                    ->label(__('Aktualisiert'))
                    ->date('d.m.Y', config('guide.timezone'))
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('last_checked_at')
                    ->label(__('Geprüft'))
                    ->date('d.m.Y', config('guide.timezone'))
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('next_due_at')
                    ->label(__('Nächste Prüfung'))
                    ->formatStateUsing(fn (?string $state): string => static::dueLabel($state))
                    ->color(fn (?string $state): ?string => static::isOverdue($state) ? 'status-failed' : null)
                    ->icon(fn (?string $state): ?string => static::isOverdue($state) ? 'heroicon-m-exclamation-triangle' : null)
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('interval')
                    ->label(__('Prüfabstand'))
                    ->formatStateUsing(fn (int $state, array $record): string => trans_choice('{1} täglich|[2,*] alle :count Tage', $state, ['count' => $state])
                        .($record['interval_custom'] ? '' : ' '.__('(Vorgabe)')))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kategorie')
                    ->label(__('Kategorie'))
                    ->multiple()
                    ->options(fn (): array => static::categoryOptions()),
                SelectFilter::make('thema')
                    ->label(__('Themenstatus'))
                    ->multiple()
                    ->options(TopicStatus::options()),
                SelectFilter::make('lauf')
                    ->label(__('Laufergebnis'))
                    ->multiple()
                    ->options([...RunDisplay::options(), 'ohne' => __('Noch kein Lauf')]),
                TernaryFilter::make('quelle')
                    ->label(__('Quelle nicht erreichbar'))
                    ->trueLabel(__('Ja'))
                    ->falseLabel(__('Nein')),
                // Ziel der Zeile "Ausserhalb des Plans" auf Heute (§3.1, #33).
                TernaryFilter::make('ueberfaellig')
                    ->label(__('Außerhalb des Plans'))
                    ->trueLabel(__('Ja'))
                    ->falseLabel(__('Nein')),
            ])
            // ListRecords setzt Vorgaben fuer Eloquent-Zeilen; hier sind es Arrays.
            ->recordAction(null)
            ->recordUrl(fn (array $record): string => static::detailUrl($record['__key']))
            // ⋯-Menue je Zeile (§5.1). Ohne Symbole: 50 Zeilen mit je einem
            // Menue sind sonst ueber 1 MB HTML. Pruefabstand nur als Sammelaktion.
            ->recordActions([
                ActionGroup::make([
                    static::runNowAction(Action::make('runNow'))->icon(null),
                    Action::make('pause')
                        ->label(__('Pausieren'))
                        ->visible(fn (array $record): bool => $record['status'] === TopicStatus::ACTIVE->value)
                        ->action(fn (array $record) => static::notifyResult(app(TopicAdminService::class)->pause([$record['__key']]), __('pausiert'))),
                    Action::make('resume')
                        ->label(__('Fortsetzen'))
                        ->visible(fn (array $record): bool => $record['status'] === TopicStatus::PAUSED->value)
                        ->action(fn (array $record) => static::notifyResult(app(TopicAdminService::class)->activate([$record['__key']]), __('fortgesetzt'))),
                    static::categoryAction(Action::make('category'))->icon(null),
                    static::archiveAction(Action::make('archive'))
                        ->icon(null)
                        ->visible(fn (array $record): bool => $record['status'] !== TopicStatus::ARCHIVED->value),
                ])->label(__('Aktionen'))->tooltip(__('Aktionen')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('lockOutlines')
                        ->label(__('Gliederungen sperren'))
                        ->icon('heroicon-o-lock-closed')
                        ->requiresConfirmation()
                        ->modalDescription(__('Die vorliegenden Gliederungsvorschläge werden unverändert gesperrt. Es entstehen keine Kosten. Themen ohne Vorschlag oder mit bereits gesperrter Gliederung werden übersprungen.'))
                        ->action(fn (Collection $records) => static::notifyResult(app(TopicAdminService::class)->lockOutlines($records->pluck('__key')), __('gesperrt')))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('activate')
                        ->label(__('Aktivieren / fortsetzen'))
                        ->icon('heroicon-o-play-circle')
                        ->requiresConfirmation()
                        ->modalDescription(__('Pausierte Themen und Entwürfe mit gesperrter Gliederung werden aktiv und laufen wieder im Tageslauf mit.'))
                        ->action(fn (Collection $records) => static::notifyResult(app(TopicAdminService::class)->activate($records->pluck('__key')), __('aktiviert')))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('pause')
                        ->label(__('Pausieren'))
                        ->icon('heroicon-o-pause-circle')
                        ->requiresConfirmation()
                        ->modalDescription(__('Pausierte Themen laufen nicht im Tageslauf mit. Veröffentlichte Artikel bleiben online.'))
                        ->action(fn (Collection $records) => static::notifyResult(app(TopicAdminService::class)->pause($records->pluck('__key')), __('pausiert')))
                        ->deselectRecordsAfterCompletion(),
                    static::categoryAction(BulkAction::make('category'))->deselectRecordsAfterCompletion(),
                    static::intervalAction(BulkAction::make('interval'))->deselectRecordsAfterCompletion(),
                    static::runNowAction(BulkAction::make('runNow'))->deselectRecordsAfterCompletion(),
                    static::archiveAction(BulkAction::make('archive'))->deselectRecordsAfterCompletion(),
                ])->label(__('Sammelaktionen')),
            ])
            ->defaultSort('next_due_at', 'asc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(self::PAGE_SIZE)
            // Fortschritt laufender Laeufe; nur solange einer unterwegs ist.
            ->poll(fn (): ?string => collect(static::directory()->topics())
                ->contains(fn (array $row): bool => in_array($row['run_display'], [RunDisplay::QUEUED->value, RunDisplay::IN_PROGRESS->value], true)) ? '10s' : null)
            ->emptyStateHeading(fn (): string => static::directory()->topics() === []
                ? __('Noch keine Themen')
                : __('Keine Themen für diese Filter'))
            ->emptyStateDescription(fn (): ?string => static::directory()->topics() === []
                ? __('Themen kommen ausschließlich über den Import einer Themenliste.')
                : null)
            ->emptyStateActions([
                Action::make('import')
                    ->label(__('Themen importieren'))
                    ->url(fn (): string => ImportWizard::getUrl())
                    ->visible(fn (): bool => static::directory()->topics() === []),
            ]);
    }

    /**
     * "Jetzt ausfuehren": startet guide:run, Fortschritt per Polling in der
     * Liste bzw. im Reiter Laeufe der Detailansicht.
     */
    public static function runNowAction(Action $action): Action
    {
        return $action
            ->label(__('Jetzt ausführen …'))
            ->icon('heroicon-o-bolt')
            ->requiresConfirmation()
            ->modalHeading(__('Jetzt ausführen'))
            ->modalDescription(fn (Action $action): string => static::runCostLine(static::rowsOf($action)))
            ->modalSubmitActionLabel(__('Lauf starten'))
            ->action(function (Action $action): void {
                $keys = static::rowsOf($action)->pluck('__key')->all();
                $results = collect(app(TopicAdminService::class)->runNow($keys));
                $started = $results->where('outcome', TopicRunStarter::STARTED)->count();
                $notStarted = $results->where('outcome', '!=', TopicRunStarter::STARTED);

                $notification = Notification::make()
                    ->title(trans_choice('{0} Kein Lauf gestartet|{1} Ein Lauf gestartet|[2,*] :count Läufe gestartet', $started, ['count' => $started]))
                    ->body($notStarted->isNotEmpty()
                        ? $notStarted->pluck('reason')->filter()->unique()->take(3)->implode(' ')
                        : __('Der Fortschritt erscheint in der Spalte „Letzter Lauf“ und im Thema unter „Läufe“.'));

                ($started > 0 ? $notification->success() : $notification->warning())->send();
            });
    }

    public static function categoryAction(Action $action): Action
    {
        return $action
            ->label(__('Kategorie ändern …'))
            ->icon('heroicon-o-folder')
            ->schema([
                Select::make('category')
                    ->label(__('Kategorie'))
                    ->options(fn (): array => static::categoryOptions())
                    ->helperText(__('In Portalen ohne diese Kategorie bleibt das Thema unverändert.'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $keys = static::rowsOf($action)->pluck('__key')->all();

                static::notifyResult(app(TopicAdminService::class)->setCategory($keys, (string) $data['category']), __('umsortiert'));
            });
    }

    public static function intervalAction(Action $action): Action
    {
        return $action
            ->label(__('Prüfabstand setzen …'))
            ->icon('heroicon-o-calendar-days')
            ->schema([
                Select::make('days')
                    ->label(__('Prüfabstand'))
                    ->options(collect(self::INTERVALS)
                        ->mapWithKeys(fn (int $days): array => [(string) $days => trans_choice('{1} täglich|[2,*] alle :count Tage', $days, ['count' => $days])])
                        ->prepend(__('Vorgabe (alle :count Tage)', ['count' => (int) config('guide.schedule.probe_interval_days', 7)]), 'default')
                        ->all())
                    ->helperText(fn (): string => __('Jede Prüfung kostet ≈ :check, mit Änderung bis ≈ :update. Ein kürzerer Abstand erhöht die laufenden Kosten entsprechend.', [
                        'check' => Usd::format(Usd::estimate('check')),
                        'update' => Usd::format(Usd::estimate('update')),
                    ]))
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $keys = static::rowsOf($action)->pluck('__key')->all();
                $days = $data['days'] === 'default' ? null : (int) $data['days'];

                static::notifyResult(app(TopicAdminService::class)->setInterval($keys, $days), __('geändert'));
            });
    }

    public static function archiveAction(Action $action): Action
    {
        return $action
            ->label(__('Archivieren …'))
            ->icon('heroicon-o-archive-box')
            ->color('status-failed')
            ->requiresConfirmation()
            ->modalDescription(__('Archivierte Themen laufen nicht mehr im Tageslauf mit. Ein veröffentlichter Artikel wird zurückgezogen. Über „Entwurf“ lässt sich ein Thema zurückholen.'))
            ->action(function (Action $action): void {
                $keys = static::rowsOf($action)->pluck('__key')->all();

                static::notifyResult(app(TopicAdminService::class)->archive($keys), __('archiviert'));
            });
    }

    /**
     * @param  array{done: int, skipped: int}  $result
     */
    public static function notifyResult(array $result, string $verb): void
    {
        $notification = Notification::make()
            ->title(trans_choice('{0} Kein Thema :verb|{1} Ein Thema :verb|[2,*] :count Themen :verb', $result['done'], ['count' => $result['done'], 'verb' => $verb]));

        if ($result['skipped'] > 0) {
            $notification->body(trans_choice('{1} Ein Thema übersprungen, weil der Wechsel in seinem Status nicht möglich ist.|[2,*] :count Themen übersprungen, weil der Wechsel in ihrem Status nicht möglich ist.', $result['skipped'], ['count' => $result['skipped']]));
        }

        ($result['done'] > 0 ? $notification->success() : $notification->warning())->send();
    }

    /**
     * Kostenzeile fuer "Jetzt ausfuehren" (Abnahmefrage 4).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public static function runCostLine(Collection $rows): string
    {
        $creates = $rows->whereNull('article_id')->count();
        $checks = $rows->whereNotNull('article_id')->count();
        $parts = [];

        if ($checks > 0) {
            $parts[] = trans_choice('{1} Eine Prüfung ≈ :low, bei Änderung bis ≈ :high.|[2,*] :count Prüfungen ≈ :low, bei Änderung bis ≈ :high.', $checks, [
                'count' => $checks,
                'low' => Usd::format(Usd::estimate('check', $checks)),
                'high' => Usd::format(Usd::estimate('update', $checks)),
            ]);
        }

        if ($creates > 0) {
            $parts[] = trans_choice('{1} Eine Neuanlage ≈ :cost.|[2,*] :count Neuanlagen ≈ :cost.', $creates, [
                'count' => $creates,
                'cost' => Usd::format(Usd::estimate('create', $creates)),
            ]);
        }

        $parts[] = __('Je Thema ist ein Lauf am Tag möglich; pausierte, archivierte und Themen mit offener Gliederung werden übersprungen.');

        return implode(' ', $parts);
    }

    /**
     * Zeilen, auf die eine Einzel- oder Sammelaktion wirkt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rowsOf(Action $action): Collection
    {
        if ($action->isBulk()) {
            return collect($action->getSelectedRecords())->values();
        }

        $record = $action->getRecord();

        return collect(is_array($record) ? [$record] : []);
    }

    /**
     * Kategorien der gewaehlten Portale, Slug => Name.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return collect(static::directory()->categories())
            ->sortBy('name')
            ->mapWithKeys(fn (array $row): array => [$row['slug'] => $row['name']])
            ->all();
    }

    /**
     * Zeilen nach Suche und Filtern.
     *
     * @return Collection<array-key, array<string, mixed>>
     */
    public static function filtered(ListTopics $livewire, ?string $search): Collection
    {
        $filters = $livewire->tableFilters ?? [];
        $categories = array_filter((array) ($filters['kategorie']['values'] ?? []));
        $statuses = array_filter((array) ($filters['thema']['values'] ?? []));
        $runs = array_filter((array) ($filters['lauf']['values'] ?? []));
        $unreachable = $filters['quelle']['value'] ?? null;
        $outOfPlan = $filters['ueberfaellig']['value'] ?? null;
        $needle = Str::lower(trim((string) $search));

        /** @var Collection<array-key, array<string, mixed>> $rows */
        $rows = collect(static::directory()->topics());

        /** @var Collection<array-key, array<string, mixed>> $filtered */
        $filtered = $rows
            ->when($needle !== '', fn (Collection $rows) => $rows->filter(fn (array $row): bool => str_contains(Str::lower($row['question']), $needle)))
            ->when($categories !== [], fn (Collection $rows) => $rows->filter(fn (array $row): bool => in_array($row['category_slug'], $categories, true)))
            ->when($statuses !== [], fn (Collection $rows) => $rows->filter(fn (array $row): bool => in_array($row['status'], $statuses, true)))
            ->when($runs !== [], fn (Collection $rows) => $rows->filter(fn (array $row): bool => in_array($row['run_display'] ?? 'ohne', $runs, true)))
            ->when(filled($unreachable), fn (Collection $rows) => $rows->filter(fn (array $row): bool => (($row['unreachable_sources'] ?? 0) > 0) === (bool) $unreachable))
            ->when(filled($outOfPlan), fn (Collection $rows) => $rows->filter(fn (array $row): bool => TopicDirectory::isOutOfPlan($row) === (bool) $outOfPlan))
            ->keyBy('__key');

        return $filtered;
    }

    /**
     * @param  Collection<array-key, array<string, mixed>>  $rows
     * @return Collection<array-key, array<string, mixed>>
     */
    public static function sorted(Collection $rows, ?string $column, ?string $direction): Collection
    {
        $column ??= 'next_due_at';
        $descending = $direction === 'desc';

        $value = match ($column) {
            'status' => fn (array $row): int => array_search($row['status'], array_column(TopicStatus::cases(), 'value'), true),
            'run_display' => fn (array $row): int => $row['run_display'] !== null ? RunDisplay::from($row['run_display'])->rank() : 99,
            'question', 'tenant_name' => fn (array $row): string => Str::lower((string) $row[$column]),
            // Leere Termine immer ans Ende
            default => fn (array $row): string => (string) ($row[$column] ?? ($descending ? '' : '9999')),
        };

        return $rows->sortBy($value, SORT_REGULAR, $descending);
    }

    /**
     * @param  Collection<array-key, array<string, mixed>>  $rows
     */
    public static function paginate(Collection $rows, int $page, int|string $perPage): LengthAwarePaginator
    {
        $perPage = is_numeric($perPage) ? max(1, (int) $perPage) : max(1, $rows->count());

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->all(),
            $rows->count(),
            $perPage,
            $page,
        );
    }

    public static function dueLabel(?string $state): string
    {
        if ($state === null) {
            return '–';
        }

        $due = Carbon::parse($state)->timezone(config('guide.timezone'))->startOfDay();
        $today = Carbon::now(config('guide.timezone'))->startOfDay();
        $days = (int) $today->diffInDays($due, false);

        return match (true) {
            $days === 0 => __('heute'),
            $days > 0 => trans_choice('{1} morgen|[2,*] in :count Tagen', $days, ['count' => $days]),
            default => trans_choice('{1} seit gestern fällig|[2,*] seit :count Tagen fällig', -$days, ['count' => -$days]),
        };
    }

    public static function isOverdue(?string $state): bool
    {
        return $state !== null
            && Carbon::parse($state)->timezone(config('guide.timezone'))->startOfDay()->lt(Carbon::now(config('guide.timezone'))->startOfDay());
    }

    public static function directory(): TopicDirectory
    {
        return app(TopicDirectory::class);
    }
}
