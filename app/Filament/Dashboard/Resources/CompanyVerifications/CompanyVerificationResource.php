<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Resources\CompanyVerifications;

use App\Constants\CompanyVerificationDocumentType;
use App\Constants\CompanyVerificationStatus;
use App\Filament\Dashboard\Resources\CompanyVerifications\Pages\ListCompanyVerifications;
use App\Filament\Dashboard\Resources\CompanyVerifications\Pages\ViewCompanyVerification;
use App\Models\Portal\CompanyVerification;
use App\Models\User;
use App\Services\Premium\CompanyVerificationService;
use App\Support\TenantCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Pruefung der Verifizierungsnachweise (#11), nur fuer Administratoren.
 * Freigabe setzt companies.verified_at, Ablehnung verschickt eine Mail mit Grund.
 */
class CompanyVerificationResource extends Resource
{
    private const BADGE_CACHE_KEY = 'premium.verifications.open_badge';

    protected static ?string $model = CompanyVerification::class;

    protected static bool $isScopedToTenant = false;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedCheckBadge;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Portal';
    }

    public static function getNavigationLabel(): string
    {
        return __('Verifizierungen');
    }

    public static function getModelLabel(): string
    {
        return __('Verifizierung');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Verifizierungen');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) Cache::remember(
            TenantCache::key(self::BADGE_CACHE_KEY),
            60,
            fn (): int => CompanyVerification::query()->pending()->count(),
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Nachweis'))
                    ->schema([
                        TextEntry::make('company.name')->label(__('Betrieb')),
                        TextEntry::make('document_type')
                            ->label(__('Art'))
                            ->formatStateUsing(fn (CompanyVerificationDocumentType $state): string => $state->label()),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (CompanyVerificationStatus $state): string => $state->label())
                            ->color(fn (CompanyVerificationStatus $state): string => self::statusColor($state)),
                        TextEntry::make('created_at')->label(__('Eingereicht'))->dateTime(config('app.datetime_format')),
                        TextEntry::make('reviewed_at')->label(__('Entschieden'))->dateTime(config('app.datetime_format'))->placeholder('—'),
                        TextEntry::make('reviewed_by_user_id')
                            ->label(__('Geprüft von'))
                            ->formatStateUsing(fn (?int $state): string => $state ? (string) (User::query()->whereKey($state)->value('name') ?? "#{$state}") : '—')
                            ->placeholder('—'),
                        TextEntry::make('rejection_reason')->label(__('Ablehnungsgrund'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('document_purged_at')->label(__('Datei gelöscht'))->dateTime(config('app.datetime_format'))->placeholder('—'),
                    ])->columns(2),
                Section::make(__('Vorschau'))
                    ->schema([
                        ViewEntry::make('document_path')
                            ->hiddenLabel()
                            ->view('filament.dashboard.verification-preview'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Gewerbenachweise für das Badge „Verifizierter Betrieb" prüfen.'))
            ->columns([
                TextColumn::make('company.name')
                    ->label(__('Betrieb'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('document_type')
                    ->label(__('Art'))
                    ->formatStateUsing(fn (CompanyVerificationDocumentType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (CompanyVerificationStatus $state): string => $state->label())
                    ->color(fn (CompanyVerificationStatus $state): string => self::statusColor($state)),
                TextColumn::make('created_at')
                    ->label(__('Eingereicht'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label(__('Entschieden'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CompanyVerificationStatus::cases())->mapWithKeys(fn (CompanyVerificationStatus $s): array => [$s->value => $s->label()])->all())
                    ->default(CompanyVerificationStatus::PENDING->value)
                    ->label(__('Status')),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::rejectAction(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Freigeben'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('Der Betrieb erhält das Badge „Verifizierter Betrieb", solange sein Paket es enthält.'))
            ->visible(fn (CompanyVerification $record): bool => $record->isPending())
            ->action(function (CompanyVerification $record, CompanyVerificationService $service): void {
                $service->approve($record, auth()->user());
                self::forgetBadge();
                Notification::make()->success()->title(__('Nachweis freigegeben'))->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('Ablehnen'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->modalDescription(__('Der Grund wird dem Betrieb per E-Mail mitgeteilt.'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Grund'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->visible(fn (CompanyVerification $record): bool => $record->isPending())
            ->action(function (CompanyVerification $record, array $data, CompanyVerificationService $service): void {
                $service->reject($record, auth()->user(), trim((string) $data['reason']));
                self::forgetBadge();
                Notification::make()->success()->title(__('Nachweis abgelehnt, E-Mail wird versendet'))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyVerifications::route('/'),
            'view' => ViewCompanyVerification::route('/{record}'),
        ];
    }

    private static function statusColor(CompanyVerificationStatus $status): string
    {
        return match ($status) {
            CompanyVerificationStatus::PENDING => 'warning',
            CompanyVerificationStatus::APPROVED => 'success',
            CompanyVerificationStatus::REJECTED => 'danger',
        };
    }

    private static function forgetBadge(): void
    {
        Cache::forget(TenantCache::key(self::BADGE_CACHE_KEY));
    }
}
