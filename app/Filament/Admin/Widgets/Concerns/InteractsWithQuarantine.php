<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Concerns;

use App\AntiSpam\BotQuarantine;
use App\AntiSpam\BotScorer;
use App\Models\Portal\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Spalten und Aktionen der Sicht "Verdächtige Accounts/Einträge" (#10).
 *
 * Konten und Eintraege liegen in verschiedenen Datenbanken und haben
 * verschiedene Spalten, aber dieselbe Quarantaene: dieselben vier
 * `suspected_bot_*`-Felder, dieselben zwei Entscheidungen. Der gemeinsame Teil
 * steht deshalb hier und nicht zweimal.
 *
 * Beide Entscheidungen laufen ueber {@see BotQuarantine}:
 *
 *   Freigeben — Markierung weg, vorheriger Zustand zurueck, dauerhaft aus der
 *               Erkennung heraus (`suspected_bot_cleared_at`). Ein Eintrag,
 *               der vor der Quarantaene nicht oeffentlich war, wird dadurch
 *               NICHT veroeffentlicht.
 *   Löschen   — `deleted_at`. Hart geloescht wird nichts, auch hier nicht; der
 *               Datensatz bleibt samt Begruendung wiederherstellbar.
 */
trait InteractsWithQuarantine
{
    protected function scoreColumn(): TextColumn
    {
        return TextColumn::make('suspected_bot_score')
            ->label(__('Score'))
            ->badge()
            ->color(fn (mixed $state): string => match (true) {
                (int) $state >= 90 => 'danger',
                (int) $state >= 70 => 'warning',
                default => 'gray',
            })
            ->formatStateUsing(fn (mixed $state): string => (int) $state.'/'.BotScorer::MAX_SCORE)
            ->sortable();
    }

    protected function reasonsColumn(): TextColumn
    {
        return TextColumn::make('suspected_bot_reasons_json')
            ->label(__('Gründe'))
            ->formatStateUsing(fn (mixed $state): string => self::reasonsText($state))
            ->wrap();
    }

    protected function markedAtColumn(): TextColumn
    {
        return TextColumn::make('suspected_bot_at')
            ->label(__('Markiert'))
            ->dateTime(config('app.datetime_format'))
            ->description(fn (User|Company $record): string => __('Frist bis :date', [
                'date' => $record->suspected_bot_at?->copy()
                    ->addDays(BotQuarantine::quarantineDays())
                    ->format('d.m.Y') ?? '—',
            ]))
            ->sortable();
    }

    /** Begruendungen aus `suspected_bot_reasons_json` als eine Zeile. */
    protected static function reasonsText(mixed $state): string
    {
        $reasons = is_array($state) ? ($state['reasons'] ?? []) : [];

        if (! is_array($reasons) || $reasons === []) {
            return '—';
        }

        return implode(', ', array_map(
            static fn (mixed $reason): string => is_array($reason)
                ? (string) ($reason['label'] ?? $reason['code'] ?? '?').' (+'.(int) ($reason['weight'] ?? 0).')'
                : (string) $reason,
            $reasons,
        ));
    }

    protected function releaseAction(): Action
    {
        return Action::make('release')
            ->label(__('Freigeben'))
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Freigeben'))
            ->modalDescription(__('Die Markierung wird entfernt und der Zustand von vor der Quarantäne wiederhergestellt. Der Datensatz wird danach nicht mehr erkannt und nicht automatisch gelöscht.'))
            ->action(function (User|Company $record): void {
                app(BotQuarantine::class)->release($record);

                Notification::make()->success()->title(__('Freigegeben'))->send();
            });
    }

    protected function softDeleteAction(): Action
    {
        return Action::make('softDelete')
            ->label(__('Löschen'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Löschen'))
            ->modalDescription(__('Der Datensatz wird nur als gelöscht markiert (deleted_at) und bleibt wiederherstellbar.'))
            ->visible(fn (User|Company $record): bool => ! $record->trashed())
            ->action(function (User|Company $record): void {
                app(BotQuarantine::class)->softDelete($record);

                Notification::make()->success()->title(__('Gelöscht'))->send();
            });
    }

    protected function releaseBulkAction(): BulkAction
    {
        return BulkAction::make('releaseSelected')
            ->label(__('Freigeben'))
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalWidth(Width::Medium)
            ->modalDescription(__('Alle ausgewählten Datensätze werden freigegeben und danach nicht mehr erkannt.'))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records): void {
                $quarantine = app(BotQuarantine::class);

                $records->each(fn (User|Company $record) => $quarantine->release($record));

                Notification::make()
                    ->success()
                    ->title(__(':count freigegeben', ['count' => $records->count()]))
                    ->send();
            });
    }

    protected function softDeleteBulkAction(): BulkAction
    {
        return BulkAction::make('softDeleteSelected')
            ->label(__('Löschen'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalWidth(Width::Medium)
            ->modalDescription(__('Alle ausgewählten Datensätze werden nur als gelöscht markiert (deleted_at) und bleiben wiederherstellbar.'))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records): void {
                $quarantine = app(BotQuarantine::class);

                $records->each(function (User|Company $record) use ($quarantine): void {
                    if (! $record->trashed()) {
                        $quarantine->softDelete($record);
                    }
                });

                Notification::make()
                    ->success()
                    ->title(__(':count gelöscht', ['count' => $records->count()]))
                    ->send();
            });
    }
}
