{{--
    Rechte Seite des Filterbalkens in der Artikelliste (#36), §3a.

    Trefferzeile, damit die Gesamtzahl ohne Scrollen sichtbar ist, dazu der
    Stand und die Schaltflaeche "Aktualisieren" — die Liste pollt bewusst
    nicht. Die Trefferzahl bleibt leer, bis sie echt ist.
--}}
<span wire:loading.remove wire:target="portals,statuses,regions,branches,sortBy,applySort,goToPage,resetFilters,refreshList">
    @if ($result['total'] > 0)
        {{ __(':from–:to von :total', [
            'from' => $result['from'],
            'to' => $result['to'],
            'total' => $result['total'],
        ]) }}
    @endif
</span>

<span>{{ __('Stand: :time', ['time' => $refreshedAt]) }}</span>

<button
    type="button"
    wire:click="refreshList"
    class="text-content-label font-medium text-content-700 underline underline-offset-4"
>
    {{ __('Aktualisieren') }}
</button>
