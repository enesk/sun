<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TurnstileVerifications;

use App\Filament\Admin\Resources\TurnstileVerifications\Pages\ListTurnstileVerifications;
use App\Models\Tenant;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Models\TurnstileVerification;
use App\Turnstile\Support\BotProtectionAccess;
use App\Turnstile\Support\TurnstileAdminPortal;
use App\Turnstile\Support\TurnstileLogConnection;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sicherheitsprüfungen (#9): das Verifikations-Log eines Portals, nur lesend.
 *
 * `turnstile_verifications` liegt je Portal in der Tenant-DB. Eine Tabelle über
 * alle Portale gaebe es nur zusammenkopiert, deshalb zeigt diese Ressource
 * immer genau ein Portal — gewaehlt oben im Filter, geteilt mit der
 * Einstellungsseite ({@see TurnstileAdminPortal}). Netzweit summiert allein das
 * Kennzahlen-Widget.
 *
 * Das Umhaengen der Verbindung erledigt {@see TurnstileLogConnection}: nur die
 * Verbindung `tenant` zeigt auf das Portal, die Standardverbindung des
 * Admin-Panels bleibt `central`.
 *
 * Nur lesend, weil eine Logzeile nie geaendert wird (die Tabelle hat kein
 * `updated_at`). Geraeumt wird ueber `turnstile:prune` (#12), nicht von Hand.
 * Angezeigt werden ausschliesslich Hashes — keine IP, keine E-Mail, kein Token.
 */
class TurnstileVerificationResource extends Resource
{
    protected static ?string $model = TurnstileVerification::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'sicherheitspruefungen';

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sicherheitsprüfungen');
    }

    public static function getPluralLabel(): string
    {
        return __('Sicherheitsprüfungen');
    }

    public static function getModelLabel(): string
    {
        return __('Sicherheitsprüfung');
    }

    /** Wie die Einstellungsseite: nur Betreiber, keine Redaktionsrollen. */
    public static function canAccess(): bool
    {
        return BotProtectionAccess::allowed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Zeitpunkt'))
                    ->dateTime(config('app.datetime_format'))
                    ->sortable(),
                TextColumn::make('action')
                    ->label(__('Formular'))
                    ->badge()
                    ->formatStateUsing(fn (TurnstileAction $state): string => $state->label()),
                TextColumn::make('outcome')
                    ->label(__('Ergebnis'))
                    ->badge()
                    ->color(fn (VerificationOutcome $state): string => $state->color())
                    ->formatStateUsing(fn (VerificationOutcome $state): string => $state->label()),
                TextColumn::make('error_codes_json')
                    ->label(__('Fehlercodes'))
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) && $state !== [] ? implode(', ', $state) : '—')
                    ->wrap(),
                TextColumn::make('hostname_reported')
                    ->label(__('Hostname laut Cloudflare'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('hostname_expected')
                    ->label(__('erwarteter Hostname'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_ms')
                    ->label(__('Dauer'))
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : "{$state} ms")
                    ->sortable()
                    ->toggleable(),
                // Nur der Hash. Er dient dem Zusammenzaehlen von Versuchen
                // derselben Herkunft (#8) und laesst sich nicht zurueckrechnen.
                TextColumn::make('ip_hash')
                    ->label(__('IP (Hash)'))
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : mb_substr((string) $state, 0, 12).'…')
                    ->copyable()
                    ->copyableState(fn (mixed $state): string => (string) $state)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email_hash')
                    ->label(__('E-Mail (Hash)'))
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : mb_substr((string) $state, 0, 12).'…')
                    ->copyable()
                    ->copyableState(fn (mixed $state): string => (string) $state)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_agent')
                    ->label(__('User-Agent'))
                    ->limit(40)
                    ->tooltip(fn (mixed $state): ?string => $state === null ? null : (string) $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('portal')
                    // __('Portal') fände auf einem Dateisystem ohne Gross-/Kleinschreibung
                    // die Gruppe lang/de/portal.php und gäbe ein Array zurück.
                    ->label('Portal')
                    ->options(fn (): array => TurnstileAdminPortal::options())
                    ->default(TurnstileAdminPortal::currentId())
                    ->selectablePlaceholder(false)
                    // Die Abfrage selbst bleibt unberuehrt — gewechselt wird die
                    // Datenbank, auf die sie laeuft. Das passiert hier und nicht
                    // erst beim Rendern, damit auch Zaehlung und Seitenwechsel
                    // dasselbe Portal treffen.
                    ->query(function (Builder $query, array $data): Builder {
                        self::pointAt($data['value'] ?? null);

                        return $query;
                    }),
                SelectFilter::make('action')
                    ->label(__('Formular'))
                    ->multiple()
                    ->options(fn (): array => collect(TurnstileAction::cases())
                        ->mapWithKeys(fn (TurnstileAction $action): array => [$action->value => $action->label()])
                        ->all()),
                SelectFilter::make('outcome')
                    ->label(__('Ergebnis'))
                    ->multiple()
                    ->options(fn (): array => collect(VerificationOutcome::cases())
                        ->mapWithKeys(fn (VerificationOutcome $outcome): array => [$outcome->value => $outcome->label()])
                        ->all()),
                Filter::make('zeitraum')
                    ->label(__('Zeitraum'))
                    ->schema([
                        DatePicker::make('von')->label(__('von')),
                        DatePicker::make('bis')->label(__('bis')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['von'] ?? null, fn (Builder $q, string $von): Builder => $q->whereDate('created_at', '>=', $von))
                        ->when($data['bis'] ?? null, fn (Builder $q, string $bis): Builder => $q->whereDate('created_at', '<=', $bis)))
                    ->indicateUsing(function (array $data): array {
                        $teile = array_filter([$data['von'] ?? null, $data['bis'] ?? null]);

                        return $teile === [] ? [] : [__('Zeitraum: :range', ['range' => implode(' – ', $teile)])];
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('Keine Prüfungen im gewählten Zeitraum'))
            ->emptyStateDescription(__('Hier steht jede Prüfung dieses Portals, sobald ein geschütztes Formular abgeschickt wurde.'));
    }

    /**
     * Grundabfrage auf dem Portal aus der gemeinsamen Auswahl. Der Filter kann
     * sie danach noch auf ein anderes Portal umhaengen.
     */
    public static function getEloquentQuery(): Builder
    {
        self::pointAt(TurnstileAdminPortal::currentId());

        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTurnstileVerifications::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function pointAt(mixed $tenantId): void
    {
        if (! filled($tenantId)) {
            return;
        }

        $tenant = Tenant::query()->find((int) $tenantId);

        if ($tenant === null) {
            return;
        }

        TurnstileAdminPortal::select((int) $tenant->getKey());
        TurnstileLogConnection::point($tenant);
    }
}
