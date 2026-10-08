<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\AntiSpam\BotQuarantine;
use App\Filament\Admin\Widgets\Concerns\InteractsWithQuarantine;
use App\Models\Tenant;
use App\Models\User;
use App\Turnstile\Support\BotProtectionAccess;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verdaechtige Konten (#10), obere Tabelle der Sicht.
 *
 * `users` liegt zentral, ein Konto kann an mehreren Portalen haengen. Darum
 * zeigt diese Tabelle alle Portale zusammen und das Portal als Spalte — anders
 * als die Eintraege darunter, die je Portal in einer eigenen Datenbank stehen
 * und deshalb die Portalauswahl der Seite brauchen.
 *
 * Gezeigt wird nur, was markiert ist. Freigegebene Konten verschwinden damit
 * aus der Liste; soft-geloeschte kommen ueber den Filter "Gelöschte" zurueck.
 */
class SuspectedAccountsTable extends TableWidget
{
    use InteractsWithQuarantine;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BotProtectionAccess::allowed();
    }

    protected function getTableHeading(): string
    {
        return __('Verdächtige Accounts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()->whereNotNull('suspected_bot_at')->with('tenants'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->description(fn (User $record): string => (string) $record->email),
                $this->scoreColumn(),
                $this->reasonsColumn(),
                TextColumn::make('tenants.name')
                    // __('Portal') faende auf einem Dateisystem ohne Gross-/
                    // Kleinschreibung die Gruppe lang/de/portal.php.
                    ->label('Portale')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Angelegt'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                $this->markedAtColumn(),
                TextColumn::make('email_verified_at')
                    ->label(__('E-Mail bestätigt'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder(__('nein'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('Gelöscht'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('suspected_bot_score', 'desc')
            ->filters([
                SelectFilter::make('tenant')
                    ->label('Portale')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'tenants',
                            fn ($relation) => $relation->whereKey((int) $data['value']),
                        ),
                    )),
                Filter::make('expired')
                    ->label(__('Frist abgelaufen'))
                    ->query(fn (Builder $query): Builder => $query->where(
                        'suspected_bot_at',
                        '<=',
                        now()->subDays(BotQuarantine::quarantineDays()),
                    )),
                TrashedFilter::make()
                    ->label(__('Gelöschte')),
            ])
            ->recordActions([
                $this->releaseAction(),
                $this->softDeleteAction(),
            ])
            ->toolbarActions([
                $this->releaseBulkAction(),
                $this->softDeleteBulkAction(),
            ])
            ->emptyStateHeading(__('Keine verdächtigen Accounts'))
            ->emptyStateDescription(__('Markiert wird ausschließlich durch "php artisan antispam:scan".'));
    }
}
