<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\ProfileRequests\Pages;

use App\Filament\Central\Resources\ProfileRequestResource;
use App\Filament\ListDefaults;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Die Inbox (SUN-RQ #7): alle offenen Anfragen aller Portale, aelteste
 * zuerst.
 *
 * Die Schnellfilter stehen als Reiter ueber der Tabelle und nicht im
 * Filtermenue: „offen" ist der Normalfall und muss ohne Klick stimmen,
 * und ein Wechsel zwischen Aenderung und Loeschung ist der haeufigste
 * Handgriff beim Abarbeiten.
 */
class ListProfileRequests extends ListRecords
{
    use ListDefaults;

    protected static string $resource = ProfileRequestResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $offen = RequestStatus::openValues();

        $reiter = [
            'offen' => Tab::make(__('Offen'))
                ->icon('heroicon-m-inbox')
                ->badge(fn (): int => ProfileRequest::query()->open()->count())
                // Die Scopes des Models stehen hier bewusst nicht: der
                // Rueckruf bekommt einen Builder ohne Modelltyp, und ein
                // Scope darauf ist fuer die statische Analyse unbekannt.
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $offen)),
        ];

        foreach ([
            'aenderung' => RequestType::CHANGE,
            'loeschung' => RequestType::DELETE,
            'uebernahme' => RequestType::CLAIM,
            'sonstiges' => RequestType::OTHER,
        ] as $schluessel => $typ) {
            $reiter[$schluessel] = Tab::make($typ->label())
                ->badge(fn (): int => ProfileRequest::query()->open()->ofType($typ)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', $offen)
                    ->where('type', $typ->value));
        }

        $reiter['bestaetigt'] = Tab::make(__('Bestätigt'))
            ->icon('heroicon-m-check-badge')
            ->badge(fn (): int => ProfileRequest::query()->where('status', RequestStatus::VERIFIED->value)->count())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RequestStatus::VERIFIED->value));

        $reiter['erledigt'] = Tab::make(__('Erledigt'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('status', $offen));

        $reiter['alle'] = Tab::make(__('Alle'));

        return $reiter;
    }

    /**
     * Die Betriebsnamen der angezeigten Seite gebuendelt nachladen — eine
     * Abfrage je vorkommendem Portal statt einer je Zeile. Erst hier, weil
     * vorher nicht feststeht, welche Zeilen die Seite traegt.
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();

        ProfileRequestResource::primeCompanyNames(
            $records instanceof Collection ? $records->all() : $records->items(),
        );

        return $records;
    }
}
