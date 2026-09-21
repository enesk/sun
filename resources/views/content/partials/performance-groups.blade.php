{{--
    Leistung, Gruppentabelle des Reiters "Regionen" (#25).

    Neben der Summe steht immer "Klicks je Artikel": eine grosse Gruppe mit
    vielen schwachen Artikeln sieht in der Summe sonst besser aus als sie ist.

    Erwartete Variablen: $groups, $heading, $empty, $int, $dec.
--}}
<div class="mt-content-8 overflow-hidden rounded-content-lg bg-surface-card">
    @if ($groups === [])
        <p class="p-content-8 text-center text-content-body text-text-muted">{{ $empty }}</p>
    @else
        <table class="w-full table-fixed border-collapse text-content-table">
            <thead>
                <tr class="bg-surface-sunken">
                    <th scope="col" class="h-10 px-content-3 text-start text-content-label font-medium text-text-base">
                        {{ $heading }}
                    </th>
                    <th scope="col" class="h-10 w-[110px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Artikel') }}
                    </th>
                    <th scope="col" class="h-10 w-[130px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Impressionen') }}
                    </th>
                    <th scope="col" class="h-10 w-[110px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Klicks') }}
                    </th>
                    <th scope="col" class="h-10 w-[150px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Klicks je Artikel') }}
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($groups as $group)
                    <tr wire:key="group-{{ md5($group['label']) }}" class="h-11 border-t border-line-soft">
                        <td class="max-w-0 px-content-3">
                            <span class="block truncate text-text-strong" title="{{ $group['label'] }}">{{ $group['label'] }}</span>
                        </td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">{{ $int($group['articles']) }}</td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">{{ $int($group['impressions']) }}</td>
                        <td class="px-content-3 text-end tabular-nums text-text-strong">{{ $int($group['clicks']) }}</td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">{{ $dec($group['clicks_per_article'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
