<?php

namespace App\Filament\Dashboard\Resources\Reviews;

use App\Filament\Dashboard\Resources\Reviews\Pages\EditReview;
use App\Filament\Dashboard\Resources\Reviews\Pages\ListReviews;
use App\Models\Portal\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewResource extends Resource
{
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->pending()->count();

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
                            ->options([
                                Review::STATUS_PENDING => __('Ausstehend'),
                                Review::STATUS_APPROVED => __('Freigegeben'),
                                Review::STATUS_REJECTED => __('Abgelehnt'),
                            ])
                            ->required()
                            ->label(__('Status'))
                            ->reactive(),
                        Textarea::make('moderation_note')
                            ->rows(3)
                            ->label(__('Moderationsnotiz'))
                            ->helperText(__('Intern — wird dem Autor nicht angezeigt.'))
                            ->columnSpanFull(),
                        Placeholder::make('moderated_by_info')
                            ->label(__('Moderiert von'))
                            ->content(fn (?Review $record) => $record?->moderated_by ?? '—'),
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
                    ->label(__('Firma'))
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
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state)),
                TextColumn::make('title')
                    ->label(__('Titel'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('body')
                    ->label(__('Text'))
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('moderation_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Review::STATUS_PENDING => __('Ausstehend'),
                        Review::STATUS_APPROVED => __('Freigegeben'),
                        Review::STATUS_REJECTED => __('Abgelehnt'),
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        Review::STATUS_PENDING => 'warning',
                        Review::STATUS_APPROVED => 'success',
                        Review::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Erstellt'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('moderation_status')
                    ->options([
                        Review::STATUS_PENDING => __('Ausstehend'),
                        Review::STATUS_APPROVED => __('Freigegeben'),
                        Review::STATUS_REJECTED => __('Abgelehnt'),
                    ])
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
                    ->visible(fn (Review $record) => $record->moderation_status !== Review::STATUS_APPROVED),
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
                    ->visible(fn (Review $record) => $record->moderation_status !== Review::STATUS_REJECTED),
                EditAction::make(),
            ])
            ->bulkActions([
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
}
