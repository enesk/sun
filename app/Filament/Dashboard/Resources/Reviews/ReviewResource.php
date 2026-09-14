<?php

namespace App\Filament\Dashboard\Resources\Reviews;

use App\Enums\ModerationStatus;
use App\Filament\Dashboard\Resources\Reviews\Pages\EditReview;
use App\Filament\Dashboard\Resources\Reviews\Pages\ListReviews;
use App\Models\Portal\Review;
use App\Support\TenantCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ReviewResource extends Resource
{
    private const FILTER_OPEN = 'open';

    protected static ?string $model = Review::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedStar;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user->isAdmin()) {
            return $query;
        }

        // Firmeninhaber sehen nur Reviews ihrer eigenen Firmen
        return $query->whereHas('company', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Portal');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bewertungen');
    }

    public static function getModelLabel(): string
    {
        return __('Bewertung');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bewertungen');
    }

    /**
     * Offene Bewertungen (pending + needs_review), 60 s gecacht. Der Schluessel
     * traegt Mandant und Sichtbereich, Inhaber sehen nur ihre eigenen Betriebe.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $scope = $user?->isAdmin() ? 'admin' : 'user.'.$user?->getAuthIdentifier();

        $count = (int) Cache::remember(
            TenantCache::key("reviews.moderation.open_badge.{$scope}"),
            (int) config('moderation.reviews.badge_cache_seconds', 60),
            fn (): int => static::getEloquentQuery()->whereIn('moderation_status', ModerationStatus::openValues())->count(),
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    Select::make('company_id')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled()
                        ->label(__('Firma')),
                    TextInput::make('author_name')
                        ->maxLength(255)
                        ->disabled()
                        ->label(__('Autor')),
                    TextInput::make('rating')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(5)
                        ->step(0.1)
                        ->disabled()
                        ->label(__('Bewertung (1.0–5.0)')),
                    TextInput::make('title')
                        ->maxLength(255)
                        ->disabled()
                        ->label(__('Titel')),
                    Textarea::make('body')
                        ->rows(4)
                        ->disabled()
                        ->label(__('Text'))
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make(__('Moderation'))
                    ->schema([
                        Select::make('moderation_status')
                            ->options(ModerationStatus::options())
                            ->required()
                            ->label(__('Status'))
                            ->reactive(),
                        TextInput::make('moderation_reason')
                            ->maxLength(255)
                            ->label(__('Grund'))
                            ->helperText(__('Heuristik, Meldung oder Ablehnungsgrund.')),
                        Textarea::make('moderation_note')
                            ->rows(3)
                            ->label(__('Moderationsnotiz'))
                            ->helperText(__('Intern — wird dem Autor nicht angezeigt.'))
                            ->columnSpanFull(),
                        Placeholder::make('moderated_by_info')
                            ->label(__('Moderiert von'))
                            ->content(fn (?Review $record) => collect([$record?->moderated_by_name, $record?->moderated_at?->format('d.m.Y H:i')])->filter()->implode(', ') ?: '—'),
                        Placeholder::make('approved_at_info')
                            ->label(__('Freigegeben am'))
                            ->content(fn (?Review $record) => $record?->approved_at?->format('d.m.Y H:i') ?? '—'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Bewertungen moderieren und verwalten.'))
            ->columns([
                TextColumn::make('company.name')
                    ->label(__('Betrieb'))
                    ->sortable()
                    ->searchable()
                    ->limit(30),
                TextColumn::make('author_name')
                    ->label(__('Autor'))
                    ->searchable()
                    ->placeholder(__('Anonym')),
                TextColumn::make('rating')
                    ->label(__('Sterne'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '')),
                TextColumn::make('body')
                    ->label(__('Auszug'))
                    ->state(fn (Review $record): string => trim(($record->title ? "{$record->title} – " : '').(string) $record->body))
                    ->limit(80)
                    ->tooltip(fn (Review $record): ?string => $record->body)
                    ->searchable(['title', 'body']),
                TextColumn::make('moderation_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ModerationStatus::labelFor($state))
                    ->color(fn (string $state) => ModerationStatus::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('moderation_reason')
                    ->label(__('Grund'))
                    ->limit(50)
                    ->tooltip(fn (Review $record): ?string => $record->moderation_reason)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Datum'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('moderation_status')
                    ->options([self::FILTER_OPEN => __('Zu prüfen'), ...ModerationStatus::options()])
                    ->default(self::FILTER_OPEN)
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        self::FILTER_OPEN => $query->whereIn('moderation_status', ModerationStatus::openValues()),
                        default => $query->where('moderation_status', $data['value']),
                    })
                    ->label(__('Status')),
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Firma')),
                SelectFilter::make('rating')
                    ->options([
                        1 => '1 Stern',
                        2 => '2 Sterne',
                        3 => '3 Sterne',
                        4 => '4 Sterne',
                        5 => '5 Sterne',
                    ])
                    ->label(__('Bewertung')),
            ])
            ->recordActions([
                Action::make('quick_approve')
                    ->label(__('Freigeben'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Review $record) => $record->approve())
                    ->visible(fn (Review $record) => static::canModerate() && $record->moderation_status !== Review::STATUS_APPROVED),
                Action::make('quick_reject')
                    ->label(__('Ablehnen'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('Grund (optional)'))
                            ->rows(2),
                    ])
                    ->action(fn (Review $record, array $data) => $record->reject($data['reason'] ?? null))
                    ->visible(fn (Review $record) => static::canModerate() && $record->moderation_status !== Review::STATUS_REJECTED),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('Freigeben'))
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(fn (Review $review) => $review->approve());
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    BulkAction::make('reject')
                        ->label(__('Ablehnen'))
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->form([
                            Textarea::make('reason')
                                ->label(__('Grund (optional)'))
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Review $review) => $review->reject($data['reason'] ?? null));
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => static::canModerate()),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'edit' => EditReview::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Inhaber sehen die Liste nur lesend, moderiert wird ausschliesslich von
     * Administratoren (#17). Die Sperre sitzt hier und nicht nur an den
     * Buttons, damit auch die Edit-Route und Livewire-Aufrufe abgewiesen werden.
     */
    public static function canEdit(Model $record): bool
    {
        return static::canModerate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canModerate();
    }

    public static function canDeleteAny(): bool
    {
        return static::canModerate();
    }

    private static function canModerate(): bool
    {
        return Review::canBeModeratedBy(auth()->user());
    }
}
