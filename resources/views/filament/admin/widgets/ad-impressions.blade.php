{{--
    Eigene Impressionen je Anker und Portal (Ticket #12, docs/ad-slots.md 14).
    Markup zu App\Filament\Admin\Widgets\AdImpressionsWidget.

    Bewusst eine schlichte Tabelle und kein Diagramm: verglichen werden
    Anker gegen Anker und Mobil gegen Desktop, und dafuer sind Zahlen
    nebeneinander ablesbarer als Balken.
--}}
<x-filament::section>
    <x-slot name="heading">
        {{ __('Werbeplätze — eigene Impressionen') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Ab :from, :days Tage', ['from' => \Illuminate\Support\Carbon::parse($summary['from'])->format('d.m.Y'), 'days' => $summary['days']]) }}
        @if($tenantId === null)
            · {{ __('alle Portale') }}
        @endif
        · {{ __('Zählung: 50 % der Fläche für 1 Sekunde, ohne Cookie') }}
    </x-slot>

    <x-slot name="afterHeader">
        <div class="flex gap-1">
            @foreach($windows as $window)
                <x-filament::button
                    size="xs"
                    :color="$summary['days'] === $window ? 'primary' : 'gray'"
                    wire:click="setDays({{ $window }})"
                >
                    {{ __(':days Tage', ['days' => $window]) }}
                </x-filament::button>
            @endforeach
        </div>
    </x-slot>

    @if($summary['total'] === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Für diesen Zeitraum liegen noch keine Impressionen vor. Gezählt wird erst, wenn ein belegter Anker im Browser sichtbar war; verdichtet wird stündlich (ads:aggregate-impressions).') }}
        </p>
    @else
        <div class="mb-4 grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-gray-500 dark:text-gray-400">{{ __('Impressionen') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($summary['total'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">{{ __('Mobil') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($summary['mobile'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">{{ __('Desktop') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($summary['desktop'], 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">{{ __('Anker') }}</th>
                        <th class="py-2 pr-4 text-right font-medium">{{ __('Mobil') }}</th>
                        <th class="py-2 pr-4 text-right font-medium">{{ __('Desktop') }}</th>
                        <th class="py-2 pr-4 text-right font-medium">{{ __('Gesamt') }}</th>
                        <th class="py-2 text-right font-medium">{{ __('Anteil mobil') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach($summary['slots'] as $slot)
                        <tr>
                            <td class="py-2 pr-4">
                                {{ $slot['label'] }}
                                <span class="block font-mono text-xs text-gray-400">{{ $slot['slot_key'] }}</span>
                            </td>
                            <td class="py-2 pr-4 text-right">{{ number_format($slot['mobile'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($slot['desktop'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-4 text-right font-semibold">{{ number_format($slot['total'], 0, ',', '.') }}</td>
                            <td class="py-2 text-right">
                                {{ $slot['total'] > 0 ? round($slot['mobile'] / $slot['total'] * 100).' %' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($tenantId === null && $summary['portals'] !== [])
            <h3 class="mb-2 mt-6 text-sm font-semibold">{{ __('Je Portal') }}</h3>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4 font-medium">{{ __('Portal') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Mobil') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Desktop') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Gesamt') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach($summary['portals'] as $portal)
                            <tr>
                                <td class="py-2 pr-4">{{ $portal['name'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($portal['mobile'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($portal['desktop'], 0, ',', '.') }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format($portal['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament::section>
