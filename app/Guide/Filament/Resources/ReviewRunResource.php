<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources;

use App\Guide\Filament\Resources\ReviewRunResource\Pages\ListReviewRuns;
use App\Guide\Filament\Resources\ReviewRunResource\Pages\ReviewRun;
use App\Guide\Models\TopicRun;
use App\Guide\Services\RunOverviewService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\Usd;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Pruefung (#16, design/guide-dashboard.md §8): alle Laeufe im Status review
 * der gewaehlten Portale, aelteste zuerst. Die Zaehlmarke in der Navigation
 * ist die Zahl dieser Laeufe (§1.2).
 *
 * Wie die Themenliste ohne Eloquent-Abfrage: die Laeufe liegen in den
 * Tenant-DBs, die Zeilen kommen aus RunOverviewService::reviewQueue().
 * Pruefblatt unter /pruefung/{tenant-id}-{run-id} (ReviewRun); ein Lauf, der
 * auf die Gliederung wartet, fuehrt direkt in den Gliederungs-Editor.
 */
class ReviewRunResource extends Resource
{
    protected static ?string $model = TopicRun::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'pruefung';

    public static function getNavigationLabel(): string
    {
        return __('Prüfung');
    }

    public static function getModelLabel(): string
    {
        return __('Lauf zur Prüfung');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Prüfung');
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

    public static function getNavigationBadge(): ?string
    {
        $count = count(static::queue());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'status-review';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewRuns::route('/'),
            'view' => new PageRegistration(
                page: ReviewRun::class,
                route: fn (Panel $panel) => ReviewRun::route('/{record}')->registerRoute($panel)?->where('record', '[0-9]+-[0-9]+'),
            ),
        ];
    }

    /**
     * Ziel eines Eintrags: Pruefblatt, bei offener Gliederung der Editor.
     *
     * @param  array<string, mixed>  $row
     */
    public static function entryUrl(array $row): string
    {
        return $row['awaits_outline']
            ? TopicResource::detailUrl($row['topic_key'], 'gliederung')
            : static::getUrl('view', ['record' => $row['__key']]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(fn (int $page, int|string $recordsPerPage): LengthAwarePaginator => new LengthAwarePaginator(
                collect(static::queue())->forPage($page, (int) $recordsPerPage)->keyBy('__key')->all(),
                count(static::queue()),
                (int) $recordsPerPage,
                $page,
            ))
            ->columns([
                TextColumn::make('question')
                    ->label(__('Thema'))
                    ->description(fn (array $record): string => collect([
                        static::directory()->isNetworkWide() ? $record['tenant_name'] : null,
                        $record['mode'] === 'create' ? __('Neuanlage') : __('Aktualisierung'),
                        $record['changed_sections'] > 0 ? trans_choice('{1} 1 Abschnitt|[2,*] :count Abschnitte', $record['changed_sections'], ['count' => $record['changed_sections']]) : null,
                    ])->filter()->implode(' · '))
                    ->lineClamp(2)
                    ->wrap(),
                TextColumn::make('awaits_outline')
                    ->label(__('Anlass'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Gliederung sperren') : __('Qualitätsgate'))
                    ->badge()
                    ->color('status-review'),
                TextColumn::make('score')
                    ->label(__('Bewertung'))
                    ->placeholder('–')
                    ->numeric(),
                TextColumn::make('waiting_since')
                    ->label(__('Wartet'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? static::waitingLabel($state) : '–')
                    ->color(fn (?string $state): ?string => $state !== null && Carbon::parse($state)->lt(Carbon::now()->subDay()) ? 'status-review' : null),
                TextColumn::make('cost')
                    ->label(__('Kosten'))
                    ->formatStateUsing(fn (float $state): string => Usd::format($state))
                    ->visible(fn (): bool => Filament::auth()->user()?->canSeeContentCosts() ?? false),
            ])
            ->recordAction(null)
            ->recordUrl(fn (array $record): string => static::entryUrl($record))
            ->paginated([25, 50])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading(__('Nichts zu prüfen.'))
            ->emptyStateDescription(__('Neue Einträge erscheinen hier, sobald das Qualitätsgate einen Lauf nicht selbst freigibt.'));
    }

    public static function waitingLabel(string $since): string
    {
        $hours = (int) Carbon::parse($since)->diffInHours(Carbon::now());

        return $hours < 1
            ? __('seit weniger als 1 Std.')
            : ($hours < 48 ? __('seit :count Std.', ['count' => $hours]) : __('seit :count Tagen', ['count' => intdiv($hours, 24)]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function queue(): array
    {
        return app(RunOverviewService::class)->reviewQueue(static::directory()->tenants());
    }

    public static function directory(): TopicDirectory
    {
        return app(TopicDirectory::class);
    }
}
