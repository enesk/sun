{{--
    Statistik-Dashboard im Betriebsbereich, Theme sun-v2 (#16).
    Logik: App\Livewire\Portal\Company\Dashboard\Statistics. Texte: portal.owner.statistics.*

    Ohne Feature statistics: Beispielwerte verschwommen und aria-hidden, darueber
    der Freischalt-Hinweis (SUN-PREM-016). Das Chart zeichnet das Modul
    js/modules/stats-chart.js (Chart.js, lokal gebundelt) aus data-stats-chart;
    bei Livewire-Updates kommen die Werte ueber das Event stats-chart.
--}}
@php
    $number = fn ($value) => number_format((float) $value, 0, ',', '.');
    $decimal = fn ($value) => number_format((float) $value, $value < 10 ? 1 : 0, ',', '.');
    $icons = [
        'profile_views' => 'eye',
        'phone_clicks' => 'phone',
        'website_clicks' => 'globe',
        'quote_requests' => 'inbox',
        'list_impressions' => 'list',
    ];
@endphp
<div>
  <nav class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 md:mx-0 md:px-0" aria-label="{{ __('portal.owner.stats.period_label') }}">
    @foreach(\App\Livewire\Portal\Company\Dashboard\Statistics::PERIODS as $period)
      <button type="button" wire:click="setPeriod({{ $period }})"
              class="{{ $days === $period ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
              @if($days === $period) aria-current="true" @endif>{{ __('portal.owner.statistics.period', ['tage' => $period]) }}</button>
    @endforeach
  </nav>
  <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.statistics.range', ['von' => $from->format('d.m.Y'), 'bis' => $to->format('d.m.Y')]) }}</p>

  <div class="relative mt-4" wire:loading.class="opacity-60">
    @unless($allowed)
      <div class="absolute inset-0 z-10 flex items-start justify-center p-4 pt-16 md:pt-24">
        <section class="card w-full max-w-md p-5 md:p-6 text-center shadow-lg" aria-labelledby="sec-stats-locked">
          <p class="pill-brand"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.statistics.locked.badge') }}</p>
          <h2 id="sec-stats-locked" class="mt-3 text-2xl font-semibold text-zinc-900">{{ __('portal.owner.statistics.locked.title') }}</h2>
          <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.statistics.locked.text') }}</p>
          <a href="{{ route('portal.owner.premium') }}" class="btn-primary mt-4 w-full"><x-sun.icon name="lock" class="icon" />{{ __('portal.owner.statistics.locked.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.stats.sample_hint') }}</p>
        </section>
      </div>
    @endunless

    <div @unless($allowed) class="blur-sm select-none pointer-events-none" aria-hidden="true" inert @endunless>
      {{-- Kennzahlen --}}
      <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4" aria-label="{{ __('portal.owner.stats.kpi.label') }}">
        @foreach($metrics as $key)
          @php $change = $changes[$key]; @endphp
          <div class="card p-4 md:p-5 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
            <p class="text-sm text-zinc-500 flex items-center gap-2"><x-sun.icon :name="$icons[$key]" class="size-4 shrink-0" />{{ __("portal.owner.statistics.metrics.{$key}") }}</p>
            <p class="mt-2 text-3xl font-bold text-zinc-900">{{ $number($totals[$key]) }}</p>
            <p class="mt-1 text-sm text-zinc-500">
              @if($change === null)
                {{ __('portal.owner.stats.kpi.no_comparison') }}
              @else
                {{ __('portal.owner.stats.kpi.change', ['wert' => ($change > 0 ? '+' : '').number_format($change, 0, ',', '.')]) }}
              @endif
            </p>
          </div>
        @endforeach
      </section>

      {{-- Verlauf --}}
      <section class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-stats-chart">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 id="sec-stats-chart" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.statistics.chart.title', ['kennzahl' => $chart['label']]) }}</h2>
          <label class="flex items-center gap-2 text-sm text-zinc-500">
            <span class="sr-only md:not-sr-only">{{ __('portal.owner.statistics.chart.metric_label') }}</span>
            <select class="input min-h-11 w-auto" wire:change="setMetric($event.target.value)">
              @foreach($metrics as $key)
                <option value="{{ $key }}" @selected($metric === $key)>{{ __("portal.owner.statistics.metrics.{$key}") }}</option>
              @endforeach
            </select>
          </label>
        </div>
        <div class="mt-4 relative h-56 md:h-72" wire:ignore>
          <canvas data-stats-chart="{{ json_encode($chart) }}" role="img" aria-label="{{ __('portal.owner.statistics.chart.aria', ['kennzahl' => $chart['label'], 'tage' => $days]) }}"></canvas>
        </div>
        <details class="mt-3 text-sm text-zinc-500">
          <summary class="cursor-pointer min-h-11 inline-flex items-center">{{ __('portal.owner.statistics.chart.table') }}</summary>
          <div class="mt-2 max-h-64 overflow-y-auto">
            <table class="w-full text-left">
              <thead><tr><th class="py-1 font-medium">{{ __('portal.owner.statistics.chart.date') }}</th><th class="py-1 font-medium text-right">{{ $chart['label'] }}</th></tr></thead>
              <tbody>
                @foreach($daily as $day)
                  <tr class="border-t border-zinc-200"><td class="py-1">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d.m.Y') }}</td><td class="py-1 text-right text-zinc-900">{{ $number($day[$metric]) }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </details>
      </section>

      <div class="mt-4 md:mt-6 grid md:grid-cols-2 gap-4 md:gap-6">
        {{-- Ranking --}}
        <section class="card p-5 md:p-6" aria-labelledby="sec-stats-ranking">
          <h2 id="sec-stats-ranking" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.statistics.ranking.title') }}</h2>
          @if($ranking)
            <p class="mt-2 text-3xl font-bold text-zinc-900">{{ __('portal.owner.statistics.ranking.position', ['platz' => $ranking['position']]) }}</p>
            <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.statistics.ranking.text', ['anzahl' => $ranking['total'], 'stadt' => $ranking['city']]) }}</p>
          @else
            <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.statistics.ranking.empty') }}</p>
          @endif
        </section>

        {{-- Vergleich Stadt --}}
        <section class="card p-5 md:p-6" aria-labelledby="sec-stats-average">
          <h2 id="sec-stats-average" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.statistics.average.title') }}</h2>
          @if($average)
            <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.statistics.average.text') }}</p>
            <table class="mt-3 w-full text-base">
              <thead class="sr-only"><tr><th>{{ __('portal.owner.statistics.average.metric') }}</th><th>{{ __('portal.owner.statistics.average.you') }}</th><th>{{ __('portal.owner.statistics.average.city') }}</th></tr></thead>
              <tbody>
                @foreach($metrics as $key)
                  <tr class="border-t border-zinc-200 first:border-t-0">
                    <td class="py-2 text-zinc-700">{{ __("portal.owner.statistics.metrics.{$key}") }}</td>
                    <td class="py-2 text-right font-medium text-zinc-900">{{ $number($totals[$key]) }}</td>
                    <td class="py-2 pl-3 text-right text-zinc-500 whitespace-nowrap">{{ __('portal.owner.statistics.average.value', ['wert' => $decimal($average[$key])]) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.statistics.average.empty') }}</p>
          @endif
        </section>
      </div>
    </div>
  </div>
</div>
