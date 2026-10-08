<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\AntiSpam\BotQuarantine;
use App\Filament\Admin\Widgets\Concerns\InteractsWithQuarantine;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Turnstile\Support\BotProtectionAccess;
use App\Turnstile\Support\TurnstileLogConnection;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verdaechtige Firmeneintraege (#10), untere Tabelle der Sicht.
 *
 * `companies` liegt je Portal in der Tenant-DB. Gezeigt wird deshalb immer
 * genau ein Portal — das oben auf der Seite gewaehlte; eine Tabelle ueber alle
 * Portale gaebe es nur zusammenkopiert. Umgehaengt wird nur die Verbindung
 * `tenant` ({@see TurnstileLogConnection}), die Standardverbindung des Panels
 * bleibt `central`; dieselbe Loesung wie bei den Sicherheitspruefungen (#9).
 *
 * Die Seite uebergibt `tenantId` und baut die Komponente bei jedem
 * Portalwechsel neu auf (Schluessel im Blade) — die Verbindung muss noch
 * stehen, wenn die Tabelle beim Rendern ihre Abfrage ausfuehrt.
 */
class SuspectedListingsTable extends TableWidget
{
    use InteractsWithQuarantine;

    public ?int $tenantId = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BotProtectionAccess::allowed();
    }

    protected function getTableHeading(): string
    {
        $tenant = $this->tenant();

        return $tenant === null
            ? __('Verdächtige Einträge')
            : __('Verdächtige Einträge · :portal', ['portal' => (string) $tenant->name]);
    }

    /**
     * Auch vor einer Aktion: Filament sucht den Datensatz ueber die Tabelle,
     * und die Verbindung muss dabei schon auf dem Portal stehen.
     */
    public function booted(): void
    {
        $this->pointConnection();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $this->pointConnection();

                return Company::query()->whereNotNull('suspected_bot_at');
            })
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->description(fn (Company $record): string => (string) ($record->email ?: $record->full_address)),
                $this->scoreColumn(),
                $this->reasonsColumn(),
                IconColumn::make('is_active')
                    ->label(__('Öffentlich'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Angelegt'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                $this->markedAtColumn(),
                TextColumn::make('deleted_at')
                    ->label(__('Gelöscht'))
                    ->dateTime(config('app.datetime_format'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('suspected_bot_score', 'desc')
            ->filters([
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
            ->emptyStateHeading(__('Keine verdächtigen Einträge in diesem Portal'))
            ->emptyStateDescription(__('Markiert wird ausschließlich durch "php artisan antispam:scan".'));
    }

    private function tenant(): ?Tenant
    {
        return $this->tenantId === null ? null : Tenant::query()->find($this->tenantId);
    }

    /**
     * Die Verbindung `tenant` auf das gewaehlte Portal richten. Ohne Portal
     * bleibt sie stehen — dann hat die Seite ohnehin nichts anzuzeigen.
     */
    private function pointConnection(): void
    {
        $tenant = $this->tenant();

        if ($tenant !== null) {
            TurnstileLogConnection::point($tenant);
        }
    }
}
