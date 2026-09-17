{{--
    Uebersicht des Betriebsbereichs im Theme sun-v2 (Vorlage betriebsbereich-elektrikerportal.html).
    Daten: OwnerDashboardController::index(). Texte: lang/de/portal.php (owner.*).

    "Anfragen" aus der Vorlage fehlt: dafuer gibt es noch keine Seite und keine Zahl.
    Stattdessen zeigt die vierte Kennzahl die Einblendungen in der Suche.
--}}
@extends('layouts.panel')

@php
    $fields = collect($profileCompletion['fields']);
    $supportEmail = $currentTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL);
    $firstName = \Illuminate\Support\Str::before(trim((string) auth()->user()->name), ' ');
    $kpis = [
        ['icon' => 'eye', 'label' => __('portal.owner.overview.kpi.views'), 'value' => number_format($stats['page_views'], 0, ',', '.'), 'sub' => __('portal.owner.overview.kpi.last_30_days')],
        ['icon' => 'search', 'label' => __('portal.owner.overview.kpi.impressions'), 'value' => number_format($stats['search_impressions'], 0, ',', '.'), 'sub' => __('portal.owner.overview.kpi.last_30_days')],
        ['icon' => 'phone', 'label' => __('portal.owner.overview.kpi.contact_clicks'), 'value' => number_format($stats['contact_clicks'], 0, ',', '.'), 'sub' => __('portal.owner.overview.kpi.contact_clicks_sub')],
    ];
@endphp

@section('title', __('portal.owner.nav.overview'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">
        {{ $firstName !== '' ? __('portal.owner.overview.greeting', ['name' => $firstName]) : __('portal.owner.overview.greeting_anonymous') }}
      </h1>
      <p class="mt-1 text-sm text-zinc-500">
        {{ $company->name }}@if($company->updated_at), {{ __('portal.owner.overview.updated', ['zeit' => $company->updated_at->locale('de')->diffForHumans()]) }}@endif
      </p>
    </div>
    <a href="{{ $company->portal_url }}" class="btn-secondary" target="_blank" rel="noopener">
      <x-sun.icon name="external" class="icon" /> {{ __('portal.owner.overview.view_company') }}
    </a>
  </div>

  {{-- Upsell fuer Free-Betriebe (#17) --}}
  <x-premium.upsell-banner :company="$company" class="mt-6" />

  {{-- Kennzahlen --}}
  <section class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4" aria-label="{{ __('portal.owner.overview.kpi.label') }}">
    @foreach($kpis as $kpi)
      <div class="card p-4 md:p-5">
        <p class="text-sm text-zinc-500 flex items-center gap-2"><x-sun.icon :name="$kpi['icon']" class="size-4 shrink-0" />{{ $kpi['label'] }}</p>
        <p class="mt-2 text-3xl font-bold text-zinc-900">{{ $kpi['value'] }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ $kpi['sub'] }}</p>
      </div>
    @endforeach
    <div class="card p-4 md:p-5">
      <p class="text-sm text-zinc-500 flex items-center gap-2"><x-sun.icon name="star" class="size-4 shrink-0 fill-amber-500 text-amber-500" stroke="none" />{{ __('portal.owner.overview.kpi.rating') }}</p>
      <p class="mt-2 text-3xl font-bold text-zinc-900">{{ $stats['rating_count'] > 0 ? number_format((float) $stats['rating'], 1, ',', '') : '–' }}</p>
      <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('portal.owner.overview.kpi.rating_count', $stats['rating_count'], ['anzahl' => $stats['rating_count']]) }}</p>
    </div>
  </section>

  <div class="mt-4 md:mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">

    <div class="space-y-4 md:space-y-6 min-w-0">

      {{-- Profil vervollstaendigen: Checkliste statt Fortschrittsbalken --}}
      <section class="card p-5 md:p-6" aria-labelledby="profil-fertig">
        @if($profileCompletion['filled'] === $profileCompletion['total'])
          <h2 id="profil-fertig" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.overview.completion.done_title') }}</h2>
          <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.overview.completion.done_text') }}</p>
        @else
          <h2 id="profil-fertig" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.overview.completion.title') }}</h2>
          <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.overview.completion.progress', ['erledigt' => $profileCompletion['filled'], 'gesamt' => $profileCompletion['total']]) }}</p>
          <ul class="mt-5 divide-y divide-zinc-200">
            @foreach($fields->sortByDesc('filled') as $key => $field)
              @if($field['filled'])
                <li class="flex items-center gap-3 py-3 text-zinc-500">
                  <x-sun.icon name="check" class="icon text-emerald-600" /><span class="line-through">{{ __("portal.owner.overview.completion.fields.{$key}.label") }}</span>
                </li>
              @else
                <li>
                  <a href="{{ route('portal.owner.edit') }}" class="flex items-center gap-3 py-3 -mx-2 px-2 rounded-xl hover:bg-zinc-50 text-zinc-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">
                    <x-sun.icon name="circle" class="icon text-zinc-300" />
                    <span class="font-medium">{{ __("portal.owner.overview.completion.fields.{$key}.label") }}</span>
                    <span class="ml-auto hidden sm:inline text-sm text-zinc-500 text-right">{{ __("portal.owner.overview.completion.fields.{$key}.hint") }}</span>
                    <x-sun.icon name="chevron-right" class="icon text-zinc-400 ml-auto sm:ml-0" />
                  </a>
                </li>
              @endif
            @endforeach
          </ul>
          <a href="{{ route('portal.owner.edit') }}" class="btn-primary mt-4 w-full sm:w-auto">{{ __('portal.owner.overview.completion.cta') }}</a>
        @endif
      </section>

      {{-- Neueste Bewertungen --}}
      <section class="card p-5 md:p-6" aria-labelledby="neueste-bewertungen">
        <div class="flex items-center justify-between gap-4">
          <h2 id="neueste-bewertungen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.overview.reviews.title') }}</h2>
          @if($stats['reviews_total'] > 0)
            <a href="{{ route('portal.owner.reviews') }}" class="btn-ghost -mr-3 shrink-0">{{ __('portal.owner.overview.reviews.all', ['anzahl' => $stats['reviews_total']]) }}</a>
          @endif
        </div>

        @if($recentReviews->isEmpty())
          <p class="mt-3 text-base text-zinc-500">{{ __('portal.owner.overview.reviews.empty') }}</p>
        @else
          <ul class="mt-4 divide-y divide-zinc-200">
            @foreach($recentReviews as $review)
              <li class="py-4">
                <div class="flex items-center justify-between gap-3">
                  <p class="font-semibold text-zinc-900 truncate">{{ $review->author_name ?: __('portal.owner.overview.reviews.anonymous') }}</p>
                  <p class="text-sm text-zinc-500 shrink-0">{{ $review->created_at->locale('de')->diffForHumans() }}</p>
                </div>
                <p class="mt-1" role="img" aria-label="{{ __('portal.owner.overview.reviews.stars', ['anzahl' => $review->rating]) }}">
                  <x-sun.stars :rating="$review->rating" />
                </p>
                @if($review->title)
                  <p class="mt-2 text-base font-medium text-zinc-900">{{ $review->title }}</p>
                @endif
                @if($review->body)
                  <p class="mt-1 text-base leading-relaxed text-zinc-700 line-clamp-2">{{ $review->body }}</p>
                @endif
              </li>
            @endforeach
          </ul>
        @endif
      </section>
    </div>

    <div class="space-y-4 md:space-y-6">

      {{-- Premium: einzige getoente Karte. Primaer nur, wenn die Checkliste keinen Primaer-Button mehr hat. --}}
      @unless($company->is_premium)
        <section class="rounded-2xl bg-brand-50 p-5 md:p-6" aria-labelledby="premium">
          <h2 id="premium" class="text-lg font-semibold text-zinc-900 flex items-center gap-2">
            <x-sun.icon name="sparkles" class="icon text-brand" /> {{ __('portal.owner.overview.premium.title') }}
          </h2>
          <ul class="mt-3 space-y-2 text-base text-zinc-700">
            @foreach(__('portal.owner.overview.premium.benefits') as $benefit)
              <li class="flex gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />{{ $benefit }}</li>
            @endforeach
          </ul>
          <a href="{{ route('portal.owner.premium') }}" class="{{ $profileCompletion['filled'] === $profileCompletion['total'] ? 'btn-primary' : 'btn-secondary' }} mt-4 w-full">{{ __('portal.owner.overview.premium.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('portal.owner.overview.premium.note') }}</p>
        </section>
      @endunless

      @include('pages.dashboard.partials.lead-quota', ['leadQuota' => $leadQuota])

      {{-- Dein Eintrag --}}
      <section class="card p-5 md:p-6" aria-labelledby="dein-eintrag">
        <h2 id="dein-eintrag" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.overview.entry.title') }}</h2>
        <dl class="mt-3 divide-y divide-zinc-200 text-base">
          <div class="flex items-center justify-between gap-3 py-3">
            <dt class="text-zinc-500">{{ __('portal.owner.overview.entry.status') }}</dt>
            <dd>
              @if($company->is_active)
                <span class="pill bg-emerald-50 text-emerald-700">{{ __('portal.owner.overview.entry.active') }}</span>
              @else
                <span class="pill bg-red-50 text-red-600">{{ __('portal.owner.overview.entry.inactive') }}</span>
              @endif
            </dd>
          </div>
          <div class="flex items-center justify-between gap-3 py-3">
            <dt class="text-zinc-500">{{ __('portal.owner.overview.entry.plan') }}</dt>
            <dd>
              @if($company->is_premium)
                <span class="pill-brand">{{ __('portal.owner.overview.entry.plan_premium') }}</span>
              @else
                <span class="pill">{{ __('portal.owner.overview.entry.plan_basic') }}</span>
              @endif
            </dd>
          </div>
          <div class="flex items-center justify-between gap-3 py-3">
            <dt class="text-zinc-500">{{ __('portal.owner.overview.entry.verified') }}</dt>
            <dd class="flex items-center gap-1 {{ $company->is_verified ? 'text-emerald-700' : 'text-zinc-500' }}">
              @if($company->is_verified)
                <x-sun.icon name="shield-check" class="size-4 shrink-0" />{{ __('portal.owner.overview.entry.verified_yes') }}
              @else
                {{ __('portal.owner.overview.entry.verified_no') }}
              @endif
            </dd>
          </div>
          @if($company->categories->isNotEmpty())
            <div class="flex items-center justify-between gap-3 py-3">
              <dt class="text-zinc-500">{{ __('portal.owner.overview.entry.categories') }}</dt>
              <dd class="text-zinc-900 text-right truncate">
                {{ $company->categories->pluck('name')->take(2)->join(', ') }}@if($company->categories->count() > 2) <span class="text-zinc-500">+{{ $company->categories->count() - 2 }}</span>@endif
              </dd>
            </div>
          @endif
          <div class="flex items-center justify-between gap-3 py-3">
            <dt class="text-zinc-500">{{ __('portal.owner.overview.entry.created') }}</dt>
            <dd class="text-zinc-900">{{ $company->created_at->format('d.m.Y') }}</dd>
          </div>
        </dl>
      </section>

      {{-- Hilfe: nur mit gepflegter Kontaktadresse des Portals --}}
      @if($supportEmail)
        <section class="card p-5 md:p-6" aria-labelledby="hilfe">
          <h2 id="hilfe" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.overview.help.title') }}</h2>
          <p class="mt-1 text-base text-zinc-700">{{ __('portal.owner.overview.help.text') }}</p>
          <a href="mailto:{{ $supportEmail }}" class="btn-secondary mt-3 w-full">{{ __('portal.owner.overview.help.cta') }}</a>
        </section>
      @endif
    </div>
  </div>
@endsection
