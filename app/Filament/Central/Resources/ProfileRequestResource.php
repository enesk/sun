<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ProfileRequests\Pages\ListProfileRequests;
use App\Filament\Central\Resources\ProfileRequests\Pages\ViewProfileRequest;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Jobs\ClassifyRequest;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\TenantCompanyResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Die zentrale Anfragen-Inbox (SUN-RQ #7).
 *
 * Eine Liste ueber **alle** Portale: `profile_requests` liegt in der
 * Central-DB (#3), damit genau diese Seite ohne 23 Abfragen auskommt. Der
 * Betriebsname steht dort allerdings nicht — er liegt je Portal in der
 * Tenant-DB. Deshalb wird er nach dem Seitenwechsel gebuendelt nachgeladen
 * ({@see self::primeCompanyNames()}): eine Abfrage je vorkommendem Portal
 * statt einer je Zeile.
 *
 * Bearbeitet wird nichts in der Liste. Jede schreibende Aktion steckt in
 * einer Action unter `App\ProfileRequests\Actions`, die den Tenant-Kontext
 * selbst oeffnet und wieder schliesst — ein Tenant-Model im Central-Kontext
 * zu speichern waere ein Schreibvorgang auf der falschen Datenbank.
 */
class ProfileRequestResource extends Resource
{
    /** Standzeit des Menue-Zaehlers in Sekunden. */
    private const BADGE_TTL = 60;

    /** tenant_id.company_id => Betriebsname; gefuellt je Seitenaufbau. */
    private static array $companyNames = [];

    protected static ?string $model = ProfileRequest::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'anfragen';

    public static function getNavigationGroup(): ?string
    {
        return __('Tenancy');
    }

    public static function getNavigationLabel(): string
    {
        return __('Anfragen');
    }

    public static function getPluralLabel(): string
    {
        return __('Anfragen');
    }

    public static function getModelLabel(): string
    {
        return __('Anfrage');
    }

    /**
     * Zahl der offenen Vorgaenge im Menue.
     *
     * Gecacht, weil das Menue auf **jeder** Seite des Panels gerendert wird
     * und die Zahl nicht sekundengenau sein muss. 60 Sekunden sind die
     * Vorgabe aus dem Ticket; nach einer Aktion ist die eigene Liste ohnehin
     * schon neu geladen.
     */
    public static function getNavigationBadge(): ?string
    {
        $offen = Cache::remember(
            'profile-requests.open_count',
            self::BADGE_TTL,
            fn (): int => ProfileRequest::query()->open()->count(),
        );

        return $offen > 0 ? (string) $offen : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        // Angelegt und geaendert wird eine Anfrage nie von Hand — sie kommt
        // aus dem Formular (#4), dem Postfach (#5) oder dem Claim-Dialog.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Alter'))
                    ->formatStateUsing(fn (mixed $state): string => $state === null ? '—' : $state->diffForHumans(short: true))
                    ->tooltip(fn (ProfileRequest $record): ?string => $record->created_at?->format((string) config('app.datetime_format')))
                    ->sortable(),
                TextColumn::make('id')
                    ->label(__('Vorgang'))
                    ->formatStateUsing(fn (mixed $state): string => "REQ-{$state}")
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tenant.name')
                    // 'Portal' nicht durch __(): auf einem Dateisystem ohne
                    // Gross-/Kleinschreibung faende das die Gruppe
                    // lang/de/portal.php und gaebe ein Array zurueck.
                    ->label('Portal')
                    ->placeholder('— unbekannt —')
                    ->wrap()
                    ->sortable(),
                TextColumn::make('company_id')
                    ->label(__('Betrieb'))
                    ->formatStateUsing(fn (ProfileRequest $record): string => self::companyName($record) ?? '— nicht zugeordnet —')
                    ->description(fn (ProfileRequest $record): ?string => $record->requester_email)
                    // Das Suchfeld der Tabelle deckt damit Betriebsname,
                    // Absender und Vorgangsnummer in einem Feld ab.
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::applySearch($query, $search))
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('Typ'))
                    ->badge()
                    ->color(fn (RequestType $state): string => match ($state) {
                        RequestType::DELETE => 'danger',
                        RequestType::CHANGE => 'info',
                        RequestType::CLAIM => 'primary',
                        RequestType::OTHER => 'gray',
                    })
                    ->formatStateUsing(fn (RequestType $state): string => $state->label()),
                TextColumn::make('source')
                    ->label(__('Quelle'))
                    ->formatStateUsing(fn (RequestSource $state): string => $state->label())
                    ->toggleable(),
                TextColumn::make('verified_at')
                    ->label(__('Verifikation'))
                    ->badge()
                    ->color(fn (ProfileRequest $record): string => $record->isVerified() ? 'success' : 'gray')
                    ->formatStateUsing(fn (ProfileRequest $record): string => $record->isVerified()
                        ? ($record->verification_method?->label() ?? __('bestätigt'))
                        : __('offen')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (RequestStatus $state): string => match ($state) {
                        RequestStatus::APPLIED => 'success',
                        RequestStatus::REJECTED, RequestStatus::EXPIRED, RequestStatus::CANCELLED => 'danger',
                        RequestStatus::VERIFIED => 'info',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                    ->sortable(),
            ])
            // Das Aelteste zuerst: die Inbox wird von unten abgearbeitet, und
            // der Rueckstand aus docs/profile-requests.md 2.4 ist genau das,
            // was oben stehen muss.
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Portal')
                    ->options(fn (): array => self::portalOptions())
                    ->searchable(),
                SelectFilter::make('type')
                    ->label(__('Typ'))
                    ->multiple()
                    ->options(fn (): array => collect(RequestType::cases())
                        ->mapWithKeys(fn (RequestType $case): array => [$case->value => $case->label()])
                        ->all()),
                SelectFilter::make('source')
                    ->label(__('Quelle'))
                    ->multiple()
                    ->options(fn (): array => RequestSource::options()),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->options(fn (): array => RequestStatus::options()),
                TernaryFilter::make('verified')
                    ->label(__('Verifikation'))
                    ->placeholder(__('alle'))
                    ->trueLabel(__('bestätigt'))
                    ->falseLabel(__('offen'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('verified_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('ohne_betrieb')
                    ->label(__('Zuordnung'))
                    ->placeholder(__('alle'))
                    ->trueLabel(__('ohne Betrieb'))
                    ->falseLabel(__('mit Betrieb'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('company_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('company_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordUrl(fn (ProfileRequest $record): string => ViewProfileRequest::getUrl(['record' => $record]))
            ->recordActions([
                self::reclassifyAction(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('Keine Anfragen'))
            ->emptyStateDescription(__('Hier stehen die Änderungs-, Lösch- und Übernahmeanfragen aller Portale.'));
    }

    /**
     * „Von Claude einlesen" — je Vorgang einzeln.
     *
     * Nötig für alles, was ohne KI-Lauf in die Inbox kam: der übernommene
     * Altbestand (#16) liegt auf `classified`, und `ClassifyRequest` steigt
     * bei allem außer `new` aus, weil der Automat `CLASSIFIED ->
     * CLASSIFIED` nicht kennt. Mit `erneut: true` läuft die Klassifikation
     * trotzdem und schreibt nur die Vorschlagsfelder, ohne den Status zu
     * bewegen.
     *
     * `verify: false` ist Absicht: der Lauf darf keinen Magic-Link an einen
     * Betrieb schicken, dessen Anfrage Monate alt ist.
     *
     * Der Knopf steht an jeder Zeile und auf der Detailseite. Sichtbar nur
     * bei offenen Vorgängen — ein entschiedener Vorgang braucht keinen
     * neuen Vorschlag.
     */
    public static function reclassifyAction(): Action
    {
        return Action::make('neu_einlesen')
            ->label(__('Von Claude einlesen'))
            ->icon('heroicon-m-sparkles')
            ->color('gray')
            ->visible(fn (ProfileRequest $record): bool => $record->status->isOpen())
            ->requiresConfirmation()
            ->modalHeading(__('Vorgang von Claude einlesen lassen?'))
            ->modalDescription(__('Claude liest Nachricht und Anhänge, schlägt Typ, Betrieb und einen Antwortentwurf vor und legt einen Diff an, wo er einen erkennt. Das kostet einen Aufruf und dauert einen Augenblick. Es geht keine E-Mail an den Betrieb. Schon gesetzte Zuordnungen und bereits entschiedene Diff-Zeilen bleiben unberührt.'))
            ->modalSubmitActionLabel(__('Einlesen'))
            ->action(function (ProfileRequest $record): void {
                ClassifyRequest::dispatch($record->getKey(), verify: false, erneut: true);

                Notification::make()
                    ->title(__('Claude liest den Vorgang ein'))
                    ->body(__('Der Vorschlag erscheint in wenigen Augenblicken — Seite danach neu laden.'))
                    ->info()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileRequests::route('/'),
            'view' => ViewProfileRequest::route('/{record}'),
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

    // ------------------------------------------------------------------
    // Betriebsnamen aus den Tenant-DBs
    // ------------------------------------------------------------------

    /**
     * Namen aller Betriebe einer Ergebnisseite laden — eine Abfrage je
     * vorkommendem Portal. Aufgerufen von {@see ListProfileRequests}, bevor
     * die Tabelle rendert.
     *
     * @param  iterable<int, ProfileRequest>  $records
     */
    public static function primeCompanyNames(iterable $records): void
    {
        $byTenant = [];

        foreach ($records as $record) {
            if ($record->tenant_id === null || $record->company_id === null) {
                continue;
            }

            $key = self::cacheKey((int) $record->tenant_id, (int) $record->company_id);

            if (! array_key_exists($key, self::$companyNames)) {
                $byTenant[(int) $record->tenant_id][] = (int) $record->company_id;
            }
        }

        $resolver = app(TenantCompanyResolver::class);

        foreach ($byTenant as $tenantId => $companyIds) {
            $companyIds = array_values(array_unique($companyIds));

            $names = $resolver->runFor($tenantId, fn (): array => Company::query()
                ->withTrashed()
                ->whereIn('id', $companyIds)
                ->pluck('name', 'id')
                ->all());

            foreach ($companyIds as $companyId) {
                // Auch den Fehlschlag merken: ein nicht gefundener Betrieb
                // soll nicht bei jedem Rendern erneut gesucht werden.
                self::$companyNames[self::cacheKey($tenantId, $companyId)] = $names[$companyId] ?? null;
            }
        }
    }

    public static function companyName(ProfileRequest $record): ?string
    {
        if ($record->tenant_id === null || $record->company_id === null) {
            return null;
        }

        $key = self::cacheKey((int) $record->tenant_id, (int) $record->company_id);

        if (! array_key_exists($key, self::$companyNames)) {
            self::primeCompanyNames([$record]);
        }

        $name = self::$companyNames[$key] ?? null;

        return $name === null ? null : (string) $name;
    }

    /**
     * Portale, zu denen es ueberhaupt Anfragen gibt — plus die, die gerade
     * gefiltert sind. Eine Liste aller 23 Portale waere im Filter nur
     * Suchaufwand.
     *
     * @return array<int, string>
     */
    public static function portalOptions(): array
    {
        $ids = ProfileRequest::query()
            ->whereNotNull('tenant_id')
            ->distinct()
            ->pluck('tenant_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Freitextsuche des Suchfelds: Absender und Vorgangsnummer stehen in der
     * Central-DB, der Firmenname nicht. Deshalb wird er je Portal
     * nachgeschlagen und das Ergebnis als (tenant_id, company_id)-Paare in
     * die Abfrage gehaengt. Gesucht wird nur in Portalen, zu denen es
     * Anfragen gibt.
     *
     * `orWhere`, weil Filament den Rueckruf innerhalb der Suchgruppe der
     * Tabelle aufruft und mehrere durchsuchbare Spalten dort mit ODER
     * verknuepft werden.
     *
     * @param  Builder<ProfileRequest>  $query
     * @return Builder<ProfileRequest>
     */
    private static function applySearch(Builder $query, string $begriff): Builder
    {
        $begriff = trim($begriff);

        if ($begriff === '') {
            return $query;
        }

        $like = '%'.addcslashes($begriff, '%_\\').'%';
        $nummer = (int) preg_replace('/\D+/', '', $begriff);
        $treffer = self::companyIdsByName($begriff);

        return $query->orWhere(function (Builder $inner) use ($like, $nummer, $begriff, $treffer): void {
            $inner->where('requester_email', 'like', $like)
                ->orWhere('requester_name', 'like', $like);

            if ($nummer > 0 && preg_match('/^(req-?)?\d+$/i', str_replace(' ', '', $begriff)) === 1) {
                $inner->orWhere('id', $nummer);
            }

            foreach ($treffer as $tenantId => $companyIds) {
                $inner->orWhere(function (Builder $paar) use ($tenantId, $companyIds): void {
                    $paar->where('tenant_id', $tenantId)->whereIn('company_id', $companyIds);
                });
            }
        });
    }

    /**
     * Betriebe mit passendem Namen, je Portal. Nur Portale mit Anfragen, und
     * je Portal hoechstens 50 Treffer — die Suche soll eine Inbox finden,
     * nicht eine Firmendatenbank durchblaettern.
     *
     * @return array<int, list<int>>
     */
    private static function companyIdsByName(string $begriff): array
    {
        $like = '%'.addcslashes($begriff, '%_\\').'%';
        $resolver = app(TenantCompanyResolver::class);
        $treffer = [];

        foreach (array_keys(self::portalOptions()) as $tenantId) {
            $ids = $resolver->runFor((int) $tenantId, fn (): array => Company::query()
                ->withTrashed()
                ->where('name', 'like', $like)
                ->limit(50)
                ->pluck('id')
                ->all());

            if (! empty($ids)) {
                $treffer[(int) $tenantId] = array_map('intval', $ids);
            }
        }

        return $treffer;
    }

    private static function cacheKey(int $tenantId, int $companyId): string
    {
        return $tenantId.'.'.$companyId;
    }
}
