{{--
    Statistiken im Betriebsbereich, Theme sun-v2 (Vorlage statistiken-elektrikerportal.html).
    Daten: OwnerDashboardController::stats(). Texte: lang/de/portal.php (owner.stats.*).

    Den Tagesverlauf sehen alle. Herkunft, Suchbegriffe und Kontaktklicks nach Typ gibt es mit
    Premium, Basis-Eintraege sehen dort eine als Beispiel gekennzeichnete Vorschau.
    Abweichungen von der Vorlage: "Anfrage gesendet" fehlt bei den Kontaktarten (wird nicht
    gezaehlt), dafuer stehen E-Mail und Karte drin. Kein Preis in der Premium-Karte.
--}}
@extends('layouts.panel')

@php
    $periods = ['7d', '30d', '90d', '12m'];
    $isPremium = (bool) $company->is_premium;
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');

    // Balken: hoechstens 60, laengere Zeitraeume werden zu Gruppen zusammengefasst
    $days = $trend->values();
    $groupSize = max(1, (int) ceil($days->count() / 60));
    $bars = $days->chunk($groupSize)->map(fn ($chunk) => [
        'from' => \Carbon\Carbon::parse($chunk->first()['date']),
        'to' => \Carbon\Carbon::parse($chunk->last()['date']),
        'views' => (int) $chunk->sum('page_views'),
    ])->values();
    $maxBar = max(1, (int) $bars->max('views'));
    $barWidth = $bars->count() > 0 ? 100 / $bars->count() : 100;
    $hasViews = $bars->sum('views') > 0;
    $firstDay = $days->isNotEmpty() ? \Carbon\Carbon::parse($days->first()['date']) : null;
    $lastDay = $days->isNotEmpty() ? \Carbon\Carbon::parse($days->last()['date']) : null;
    $middleDay = $days->isNotEmpty() ? \Carbon\Carbon::parse($days->get(intdiv($days->count(), 2))['date']) : null;

    $change = function (?float $value) {
        if ($value === null) {
            return __('portal.owner.stats.kpi.no_comparison');
        }

        return __('portal.owner.stats.kpi.change', ['wert' => ($value > 0 ? '+' : '').number_format($value, 0, ',', '.')]);
    };

    $kpis = [
        ['icon' => 'eye', 'label' => __('portal.owner.stats.kpi.views'), 'value' => $summary['page_views'], 'sub' => $change($summary['page_views_change'])],
        ['icon' => 'phone', 'label' => __('portal.owner.stats.kpi.contact_clicks'), 'value' => $summary['contact_clicks'], 'sub' => $change($summary['contact_clicks_change'])],
        ['icon' => 'search', 'label' => __('portal.owner.stats.kpi.impressions'), 'value' => $summary['search_impressions'], 'sub' => $change($summary['search_impressions_change'])],
    ];

    // Listen fuer die drei Detailkarten: echte Werte mit Premium, sonst Beispielwerte
    $contactLabels = __('portal.owner.stats.contacts.types');
    $details = [
        'sources' => $isPremium
            ? $referrers->map(fn ($row) => ['label' => $row['domain'], 'value' => $row['count']])->values()->all()
            : collect(__('portal.owner.stats.sources.sample'))->map(fn ($label, $i) => ['label' => $label, 'value' => [54, 31, 11, 4][$i] ?? 1])->all(),
        'queries' => $isPremium
            ? $searchQueries->take(5)->map(fn ($row) => ['label' => $row['query'], 'value' => $row['count']])->values()->all()
            : collect(__('portal.owner.stats.queries.sample', ['stadt' => $company->city?->name ?? '']))->map(fn ($label, $i) => ['label' => trim($label), 'value' => [48, 23, 17, 9][$i] ?? 1])->all(),
        'contacts' => $isPremium
            ? collect($summary['contact_breakdown'])->map(fn ($value, $key) => ['label' => $contactLabels[$key] ?? $key, 'value' => $value])->filter(fn ($row) => $row['value'] > 0)->sortByDesc('value')->values()->all()
            : collect([12, 7, 3, 1])->zip(array_values($contactLabels))->map(fn ($pair) => ['label' => $pair[1], 'value' => $pair[0]])->all(),
    ];
@endphp

@section('title', __('portal.owner.stats.title'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.stats.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.stats.intro') }}</p>
    </div>
    <nav class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 md:mx-0 md:px-0" aria-label="{{ __('portal.owner.stats.period_label') }}">
      @foreach($periods as $key)
        <a href="{{ route('portal.owner.stats', ['period' => $key]) }}"
           class="{{ $period === $key ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
           @if($period === $key) aria-current="page" @endif>{{ __("portal.owner.stats.periods.{$key}") }}</a>
      @endforeach
    </nav>
  </div>

  {{-- Kennzahlen --}}
  <section class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4" aria-label="{{ __('portal.owner.stats.kpi.label') }}">
    @foreach($kpis as $kpi)
      <div class="card p-4 md:p-5">
        <p class="text-sm text-zinc-500 flex items-center gap-2"><x-sun.icon :name="$kpi['icon']" class="size-4 shrink-0" />{{ $kpi['label'] }}</p>
        <p class="mt-2 text-3xl font-bold text-zinc-900">{{ $number($kpi['value']) }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ $kpi['sub'] }}</p>
      </div>
    @endforeach
    <div class="card p-4 md:p-5">
      <p class="text-sm text-zinc-500 flex items-center gap-2"><x-sun.icon name="star" class="size-4 shrink-0 fill-amber-500 text-amber-500" stroke="none" />{{ __('portal.owner.overview.kpi.rating') }}</p>
      <p class="mt-2 text-3xl font-bold text-zinc-900">{{ $company->rating_count > 0 ? number_format((float) $company->rating, 1, ',', '') : '–' }}</p>
      <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('portal.owner.overview.kpi.rating_count', $company->rating_count, ['anzahl' => $company->rating_count]) }}</p>
    </div>
  </section>

  <div class="mt-4 md:mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">
    <div class="space-y-4 md:space-y-6 min-w-0">

      {{-- Aufrufe im Verlauf --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-verlauf">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 id="sec-verlauf" class="text-2xl font-semibold text-zinc-900">{{ $groupSize > 1 ? __('portal.owner.stats.chart.title_grouped', ['tage' => $groupSize]) : __('portal.owner.stats.chart.title') }}</h2>
          @if($firstDay)
            <p class="text-sm text-zinc-500">{{ $firstDay->format('d.m.') }} – {{ $lastDay->format('d.m.Y') }}</p>
          @endif
        </div>
        <div class="mt-4">
          @if($hasViews)
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="w-full h-40 md:h-56" role="img"
                 aria-label="{{ __('portal.owner.stats.chart.aria', ['anzahl' => $number($summary['page_views'])]) }}">
              @foreach($bars as $i => $bar)
                @php $height = $hasViews ? max(1, $bar['views'] / $maxBar * 100) : 1; @endphp
                <rect x="{{ round($i * $barWidth + $barWidth * 0.1, 3) }}" y="{{ round(100 - $height, 3) }}" width="{{ round($barWidth * 0.8, 3) }}" height="{{ round($height, 3) }}" rx="0.6"
                      fill="{{ $hasViews ? 'var(--brand-600)' : 'var(--color-zinc-200, #e4e4e7)' }}">
                  <title>{{ $bar['from']->equalTo($bar['to']) ? $bar['from']->format('d.m.') : $bar['from']->format('d.m.').' – '.$bar['to']->format('d.m.') }}: {{ trans_choice('portal.owner.stats.chart.views', $bar['views'], ['anzahl' => $number($bar['views'])]) }}</title>
                </rect>
              @endforeach
            </svg>
          @else
            <div class="flex min-h-40 md:min-h-56 flex-col items-center justify-center rounded-xl bg-zinc-50 p-5 text-center">
              <p class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.stats.chart.empty_title') }}</p>
              <p class="mt-1 max-w-sm text-base text-zinc-500">{{ __('portal.owner.stats.chart.empty_text') }}</p>
              <a href="{{ route('portal.owner.edit') }}" class="btn-secondary mt-3">{{ __('portal.owner.overview.completion.cta') }}</a>
            </div>
          @endif
        </div>
        @if($firstDay && $hasViews)
          <div class="mt-2 flex justify-between text-xs text-zinc-500" aria-hidden="true"><span>{{ $firstDay->format('d.m.') }}</span><span>{{ $middleDay->format('d.m.') }}</span><span>{{ $lastDay->format('d.m.') }}</span></div>
        @endif
      </section>

      {{-- Detailkarten: Herkunft, Suchbegriffe, Kontaktarten --}}
      @foreach($details as $key => $rows)
        @php $maxRow = max(1, (int) collect($rows)->max('value')); @endphp
        <section class="card {{ $isPremium ? '' : 'border-brand-200' }} p-5 md:p-6" aria-labelledby="sec-{{ $key }}">
          @unless($isPremium)
            <p class="pill-brand mb-3"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</p>
          @endunless
          <h2 id="sec-{{ $key }}" class="text-2xl font-semibold text-zinc-900">{{ __("portal.owner.stats.{$key}.title") }}</h2>
          <p class="mt-1 text-base text-zinc-500">{{ __("portal.owner.stats.{$key}.text") }}</p>

          @if(count($rows) === 0)
            <p class="mt-4 text-base text-zinc-500">{{ __("portal.owner.stats.{$key}.empty") }}</p>
          @else
            <ul class="mt-4 space-y-2 {{ $isPremium ? '' : 'opacity-70' }}" @unless($isPremium) aria-hidden="true" @endunless>
              @foreach($rows as $row)
                <li class="flex items-center gap-3 text-base">
                  <span class="w-2/5 min-w-0 truncate {{ $isPremium ? 'text-zinc-700' : 'text-zinc-500' }}" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                  <span class="flex-1 min-w-8 h-2 rounded-full bg-zinc-100 overflow-hidden"><span class="block h-full rounded-full {{ $isPremium ? 'bg-brand' : 'bg-brand-200' }}" style="width: {{ max(2, round($row['value'] / $maxRow * 100)) }}%"></span></span>
                  <span class="shrink-0 min-w-8 text-right {{ $isPremium ? 'font-medium text-zinc-900' : 'text-zinc-500' }}">{{ $number($row['value']) }}</span>
                </li>
              @endforeach
            </ul>
            @unless($isPremium)
              <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4">
                <p class="text-sm text-zinc-500">{{ __('portal.owner.stats.sample_hint') }}</p>
                <a href="{{ route('portal.owner.premium') }}" class="btn-secondary w-full sm:w-auto"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.unlock') }}</a>
              </div>
            @endunless
          @endif
        </section>
      @endforeach
    </div>

    <aside class="space-y-4 md:space-y-6 lg:sticky lg:top-24">
      @unless($isPremium)
        <section class="rounded-2xl bg-brand-50 p-5 md:p-6" aria-labelledby="sec-premium">
          <p class="pill-brand bg-white"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</p>
          <h2 id="sec-premium" class="mt-3 text-2xl font-semibold text-zinc-900">{{ __('portal.owner.stats.premium.title') }}</h2>
          <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.stats.premium.text') }}</p>
          <a href="{{ route('portal.owner.premium') }}" class="btn-primary mt-4 w-full">{{ __('portal.owner.overview.premium.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('portal.owner.overview.premium.note') }}</p>
        </section>
      @endunless

      <section class="card p-5 md:p-6" aria-labelledby="sec-zaehlen">
        <h2 id="sec-zaehlen" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.stats.counting.title') }}</h2>
        <dl class="mt-2 space-y-2 text-base text-zinc-700">
          @foreach(__('portal.owner.stats.counting.items') as $term => $explanation)
            <div><dt class="inline font-medium text-zinc-900">{{ $term }}:</dt> <dd class="inline">{{ $explanation }}</dd></div>
          @endforeach
        </dl>
      </section>
    </aside>
  </div>
@endsection
