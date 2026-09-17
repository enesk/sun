{{--
    Statistiken im Betriebsbereich, Theme sun-v2 (Vorlage statistiken-elektrikerportal.html).
    Daten: OwnerDashboardController::stats(). Texte: lang/de/portal.php (owner.stats.*).

    Kennzahlen, Verlauf, Ranking und Stadtvergleich kommen seit #16 aus der Livewire-Komponente
    portal.company.dashboard.statistics (company_stats_daily, Feature statistics). Darunter
    bleiben die Detailkarten aus StatisticsService (Zeitraum ?period, Standard 30 Tage).
    Herkunft, Suchbegriffe und Kontaktklicks nach Typ gibt es mit
    Premium, Basis-Eintraege sehen dort eine als Beispiel gekennzeichnete Vorschau.
    Abweichungen von der Vorlage: "Anfrage gesendet" fehlt bei den Kontaktarten (wird nicht
    gezaehlt), dafuer stehen E-Mail und Karte drin. Kein Preis in der Premium-Karte.
--}}
@extends('layouts.panel')

@php
    $isPremium = (bool) $company->is_premium;
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');

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
  </div>

  {{-- Kennzahlen, Verlauf, Ranking und Stadtvergleich (#16) --}}
  <div class="mt-6">
    <livewire:portal.company.dashboard.statistics />
  </div>

  <div class="mt-4 md:mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">
    <div class="space-y-4 md:space-y-6 min-w-0">
      <p class="text-sm text-zinc-500">{{ __('portal.owner.statistics.details_hint', ['zeitraum' => __("portal.owner.stats.periods.{$period}")]) }}</p>

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
