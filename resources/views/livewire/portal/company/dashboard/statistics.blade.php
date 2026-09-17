{{--
    Statistik-Dashboard, Default-/Starter-Theme (#16). Eingebunden ist die Komponente
    derzeit nur im Theme sun-v2 (mit Chart); diese Fassung ist der Fallback ohne Chart,
    der Verlauf steht als Tabelle da.
    Logik: App\Livewire\Portal\Company\Dashboard\Statistics. Texte: portal.owner.statistics.*
--}}
<div class="dash-card dash-card-padded">
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach(\App\Livewire\Portal\Company\Dashboard\Statistics::PERIODS as $period)
            <button type="button" wire:click="setPeriod({{ $period }})" class="dash-btn dash-btn-sm {{ $days === $period ? 'dash-btn-primary' : 'dash-btn-secondary' }}">
                {{ __('portal.owner.statistics.period', ['tage' => $period]) }}
            </button>
        @endforeach
    </div>

    @unless($allowed)
        <p class="text-sm mb-4">
            <span class="dash-badge dash-badge-premium">{{ __('portal.owner.statistics.locked.badge') }}</span>
            {{ __('portal.owner.statistics.locked.text') }}
            <a href="{{ route('portal.owner.premium') }}" class="dash-btn dash-btn-primary dash-btn-sm">{{ __('portal.owner.statistics.locked.cta') }}</a>
        </p>
    @endunless

    <div @unless($allowed) style="filter: blur(4px)" aria-hidden="true" inert @endunless>
        <table class="w-full text-sm mb-4">
            <tbody>
                @foreach($metrics as $key)
                    <tr>
                        <td>{{ __("portal.owner.statistics.metrics.{$key}") }}</td>
                        <td class="text-right font-semibold">{{ number_format($totals[$key], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $average ? __('portal.owner.statistics.average.value', ['wert' => number_format($average[$key], 1, ',', '.')]) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($ranking)
            <p class="text-sm mb-4">{{ __('portal.owner.statistics.ranking.position', ['platz' => $ranking['position']]) }} – {{ __('portal.owner.statistics.ranking.text', ['anzahl' => $ranking['total'], 'stadt' => $ranking['city']]) }}</p>
        @endif

        <details class="text-sm">
            <summary>{{ __('portal.owner.statistics.chart.table') }}</summary>
            <table class="w-full">
                @foreach($daily as $day)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }}</td><td class="text-right">{{ $day[$metric] }}</td></tr>
                @endforeach
            </table>
        </details>
    </div>
</div>
