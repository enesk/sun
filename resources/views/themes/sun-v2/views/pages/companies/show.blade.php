{{--
    Firmenprofil im Theme sun-v2 (Vorlage sun-v2--profil.html).
    Daten: CompanyController::renderCompanyShow() plus $profile aus
    App\Themes\SunV2\ProfileViewComposer.

    Der Anfrage-Button (data-open-lead, Text portal.profile.request_cta) oeffnet den
    Anfrage-Dialog partials/sun/lead-dialog, angebunden an die Funnel-Runtime-API des
    Leadsystems. Alle festen Texte kommen aus lang/de/portal.php (profile.*, layout.*).
--}}
@extends('layouts.sun')

@php
    $reviews = $company->approvedReviews;
    $moreParagraphs = count($profile['paragraphs']) > 1;
    $ratingLabel = number_format((float) $company->rating, 1, ',', '');
    $reviewsCount = trans_choice('portal.layout.card.reviews_count', $company->rating_count, ['anzahl' => number_format($company->rating_count, 0, ',', '.')]);
    $requestCta = __('portal.profile.request_cta');
@endphp

@section('title', ($company->city ? __('portal.profile.meta_title', ['firma' => $company->name, 'stadt' => $company->city->name]) : $company->name).' | '.($currentTenant->name ?? config('app.name')))
@section('meta_description', \Illuminate\Support\Str::limit((string) $company->description, 160))
@section('canonical', $company->portal_url)
@section('og_type', 'business.business')
@section('body_class', 'pb-24 lg:pb-0')

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">

  <x-sun.breadcrumb :items="$profile['breadcrumb']" />

  <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem] xl:grid-cols-[1fr_22rem] lg:items-start">

    <!-- ================= HAUPTSPALTE ================= -->
    <div class="flex flex-col gap-6 min-w-0">

      <!-- Kopf-Karte -->
      <section class="card p-5 md:p-8" aria-labelledby="firma">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 md:gap-6">
          <span class="size-20 md:size-24 rounded-2xl bg-brand-50 text-brand-700 font-bold text-2xl md:text-3xl flex items-center justify-center shrink-0" aria-hidden="true">{{ $profile['initials'] }}</span>
          <div class="min-w-0 flex-1">
            @if($company->is_premium || $company->is_verified)
              <div class="flex flex-wrap items-center gap-2">
                @if($company->is_premium)
                  <span class="pill-brand">{{ __('portal.layout.card.premium') }}</span>
                @endif
                @if($company->is_verified)
                  <span class="pill-brand"><x-sun.icon name="check" class="size-4" />{{ __('portal.layout.card.verified') }}</span>
                @endif
              </div>
            @endif
            <h1 id="firma" class="mt-2 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $company->name }}</h1>
            @if($profile['cityLabel'])
              <p class="mt-1 text-zinc-500">{{ __('portal.profile.subtitle', ['stadt' => $company->city->name]) }}</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
              @if($company->rating_count > 0)
                <a href="#bewertungen" class="flex flex-wrap items-center gap-1.5 hover:underline">
                  <x-sun.stars :rating="$company->rating" size="size-5" />
                  <span class="font-medium text-zinc-900">{{ $ratingLabel }}</span>
                  <span class="text-sm text-zinc-500">({{ $reviewsCount }})</span>
                </a>
              @else
                <span class="text-sm text-zinc-500">{{ __('portal.layout.card.no_reviews') }}</span>
              @endif
              @if($profile['status'])
                <p class="text-sm flex items-center gap-2">
                  <span class="size-2 rounded-full {{ $profile['status']->open ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                  <span class="{{ $profile['status']->open ? 'text-emerald-700' : 'text-zinc-500' }}">{{ $profile['status']->label }}</span>
                </p>
              @endif
            </div>
          </div>
        </div>

        @if($profile['facts'] !== [])
          <!-- Schnellfakten -->
          <dl class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-zinc-200">
            @foreach($profile['facts'] as $fact)
              <div><dt class="text-sm text-zinc-500">{{ $fact['label'] }}</dt><dd class="font-semibold text-zinc-900">{{ $fact['value'] }}</dd></div>
            @endforeach
          </dl>
        @endif
      </section>

      <!-- Ad-Slot unter dem Kopf (nur Mobile/Tablet, Desktop hat die rechte Spalte) -->
      @if(\App\View\Components\AdSlot::hasSlotsForPosition('after_company_card'))
        <div class="rounded-2xl bg-zinc-100 overflow-hidden lg:hidden" style="min-height:250px">
          <span class="block text-xs text-zinc-400 px-3 pt-2">{{ __('portal.layout.ad_label') }}</span>
          <x-ad-slot position="after_company_card" />
        </div>
      @endif

      <!-- Leistungen -->
      @if($company->categories->isNotEmpty())
        <section class="card p-5 md:p-8" aria-labelledby="leistungen">
          <h2 id="leistungen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.services.heading') }}</h2>
          <ul class="mt-4 grid sm:grid-cols-2 gap-x-6 gap-y-3">
            @foreach($company->categories as $category)
              <li class="flex items-start gap-2"><span class="mt-0.5"><x-sun.icon name="check" class="icon text-brand" /></span><span>{{ $category->name }}</span></li>
            @endforeach
          </ul>
          <div class="mt-5 pt-5 border-t border-zinc-200 flex flex-wrap items-center gap-3">
            <p class="text-sm text-zinc-500 flex-1 min-w-48">{{ __('portal.profile.services.cta_text', ['firma' => $company->name]) }}</p>
            <button type="button" class="btn-secondary" data-open-lead>{{ $requestCta }}</button>
          </div>
        </section>
      @endif

      <!-- Über die Firma -->
      @if($profile['paragraphs'] !== [])
        <section class="card p-5 md:p-8" aria-labelledby="ueber">
          <h2 id="ueber" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.about.heading', ['firma' => $company->name]) }}</h2>
          <div class="mt-4 max-w-prose text-base leading-relaxed flex flex-col gap-4" id="ueberText">
            @foreach($profile['paragraphs'] as $paragraph)
              <p @class(['hidden' => ! $loop->first]) @if(! $loop->first) data-more @endif>@if($paragraph['title'])<span class="block font-semibold text-zinc-900">{{ $paragraph['title'] }}</span>@endif{!! nl2br(e($paragraph['text'])) !!}</p>
            @endforeach
          </div>
          @if($moreParagraphs)
            <button type="button" class="mt-3 text-brand font-medium hover:underline" data-toggle-more aria-controls="ueberText" aria-expanded="false" data-label-more="{{ __('portal.profile.about.read_more') }}" data-label-less="{{ __('portal.profile.about.read_less') }}">{{ __('portal.profile.about.read_more') }}</button>
          @endif
          <p class="mt-5 pt-5 border-t border-zinc-200 text-sm text-zinc-500">{{ __('portal.profile.about.suggest_edit_text') }} <a href="{{ route('companies.suggest-edit', $company->slug) }}#aendern" class="text-brand hover:underline">{{ __('portal.profile.about.suggest_edit') }}</a></p>
        </section>
      @endif

      <!-- Öffnungszeiten + Adresse -->
      @if($profile['hours'] !== [] || $company->full_address)
        <section class="card p-5 md:p-8 grid gap-8 md:grid-cols-2" aria-labelledby="zeiten">
          <div>
            <h2 id="zeiten" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.hours.heading') }}</h2>
            @if($profile['hours'] !== [])
              <ul class="mt-4 divide-y divide-zinc-100">
                @foreach($profile['hours'] as $day)
                  @if($day['today'])
                    <li class="flex items-start gap-4 py-2.5 font-semibold text-zinc-900">
                      <span class="w-28 shrink-0">{{ $day['day'] }}</span>
                      <span class="tabular-nums whitespace-nowrap flex-1">
                        @forelse($day['slots'] as $slot)<span class="block">{{ $slot }}</span>@empty<span class="block text-zinc-500">{{ __('portal.layout.opening.closed') }}</span>@endforelse
                      </span>
                      <span class="pill-brand text-xs px-2 py-0.5 font-medium">{{ __('portal.layout.opening.today') }}</span>
                    </li>
                  @else
                    <li class="flex items-start gap-4 py-2.5">
                      <span class="w-28 shrink-0 flex items-center gap-2">{{ $day['day'] }}</span>
                      <span class="tabular-nums whitespace-nowrap">
                        @forelse($day['slots'] as $slot)<span class="block">{{ $slot }}</span>@empty<span class="block text-zinc-500">{{ __('portal.layout.opening.closed') }}</span>@endforelse
                      </span>
                    </li>
                  @endif
                @endforeach
              </ul>
            @else
              <p class="mt-4 text-zinc-500">{{ __('portal.empty.hours.text') }}</p>
            @endif
          </div>
          @if($company->full_address)
            <div>
              <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.location.heading') }}</h2>
              <a href="{{ $profile['mapsUrl'] }}" rel="noopener" target="_blank" class="mt-4 block card-interactive overflow-hidden" aria-label="{{ __('portal.profile.location.map_label') }}">
                <div class="aspect-[4/3] bg-zinc-100 relative">
                  <x-sun.icon name="map-lines-alt" class="absolute inset-0 size-full text-zinc-200" viewBox="0 0 400 300" />
                  <span class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full text-brand"><x-sun.icon name="map-pin" class="size-10 drop-shadow" stroke-linecap="butt" stroke-linejoin="miter" /></span>
                </div>
              </a>
              <address class="not-italic mt-3 text-zinc-700 leading-relaxed">{{ trim("{$company->street} {$company->house_no}") }}<br>{{ trim("{$company->zipcode} {$company->city?->name}") }}</address>
              <a href="{{ $profile['mapsUrl'] }}" rel="noopener" target="_blank" class="mt-1 inline-block text-brand font-medium hover:underline">{{ __('portal.profile.location.route') }}</a>
            </div>
          @endif
        </section>
      @endif

      <!-- Bewertungen -->
      <section class="card p-5 md:p-8" aria-labelledby="bewertungen">
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end sm:justify-between gap-3">
          <h2 id="bewertungen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.reviews.heading') }}</h2>
          <livewire:portal.submit-review-form :company="$company" />
        </div>

        @if($company->rating_count > 0)
          <div class="mt-5 flex items-center gap-5 p-5 rounded-2xl bg-zinc-50">
            <div class="text-center">
              <p class="text-4xl font-bold tracking-tight text-zinc-900">{{ $ratingLabel }}</p>
              <div class="mt-1 flex justify-center"><x-sun.stars :rating="$company->rating" /></div>
              <p class="mt-1 text-sm text-zinc-500">{{ $reviewsCount }}</p>
            </div>
            @if($profile['distribution'] !== [])
              @php $maxCount = max(1, max($profile['distribution'])); @endphp
              <dl class="flex-1 space-y-1.5 text-sm">
                @foreach($profile['distribution'] as $stars => $count)
                  <div class="flex items-center gap-2"><dt class="w-3 text-zinc-500 tabular-nums">{{ $stars }}</dt><dd class="flex-1 h-2 rounded-full bg-zinc-200 overflow-hidden"><span class="block h-full bg-amber-500" style="width:{{ round($count / $maxCount * 100) }}%"></span></dd><dd class="w-5 text-right text-zinc-500 tabular-nums">{{ $count }}</dd></div>
                @endforeach
              </dl>
            @endif
          </div>
        @endif

        @if($reviews->isNotEmpty())
          <ul class="mt-6 divide-y divide-zinc-200">
            @foreach($reviews as $review)
              <li class="py-5 first:pt-0 last:pb-0">
                <div class="flex items-center gap-3">
                  <span class="size-10 rounded-full bg-zinc-100 text-zinc-700 font-semibold flex items-center justify-center shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->author_name ?: 'A', 0, 1)) }}</span>
                  <div class="min-w-0">
                    <p class="font-medium text-zinc-900 leading-snug">{{ $review->author_name ?: __('portal.profile.reviews.anonymous') }}</p>
                    <div class="flex items-center gap-2 text-sm text-zinc-500"><x-sun.stars :rating="$review->rating" /><span>{{ $review->created_at->locale('de')->translatedFormat('F Y') }}</span></div>
                  </div>
                </div>
                @if($review->title)
                  <p class="mt-3 font-medium text-zinc-900">{{ $review->title }}</p>
                @endif
                @if($review->body)
                  <p class="mt-3 text-base leading-relaxed text-zinc-700">{{ $review->body }}</p>
                @endif
                @if($review->owner_response)
                  <div class="mt-3 border-l-4 border-l-brand pl-4">
                    <p class="text-sm font-medium text-zinc-900">{{ __('portal.profile.reviews.owner_response', ['firma' => $company->name]) }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-700">{{ $review->owner_response }}</p>
                  </div>
                @endif
                <livewire:reviews.report-review-button :review-id="$review->id" :key="'report-review-'.$review->id" />
              </li>
            @endforeach
          </ul>
        @else
          <p class="mt-5 text-zinc-500">{{ __('portal.empty.reviews.text', ['firma' => $company->name]) }}</p>
        @endif
      </section>

      <!-- Eintrag uebernehmen -->
      @if(! $company->user_id)
        <section class="card p-5 md:p-8 bg-brand-50 border-brand-100">
          <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.profile.claim.heading') }}</h2>
          <p class="mt-1 text-zinc-700">{{ __('portal.profile.claim.text') }}</p>
          <a href="{{ route('companies.suggest-edit', $company->slug) }}" class="mt-4 btn-secondary">{{ __('portal.profile.claim.button') }}</a>
        </section>
      @endif

    </div>

    <!-- ================= RECHTE SPALTE (Desktop) ================= -->
    <aside class="hidden lg:flex flex-col gap-4 lg:sticky lg:top-20">
      <div class="card p-5">
        <p class="text-sm text-zinc-500">{{ __('portal.profile.sidebar.lead_text', ['firma' => $company->name]) }}</p>
        <button type="button" class="mt-3 btn-primary w-full" data-open-lead><x-sun.icon name="mail" class="icon" />{{ $requestCta }}</button>
        <x-phone-link :number="$company->tel" class="mt-2 btn-secondary w-full" fallback-class="mt-2 block text-center text-sm font-medium text-zinc-700"><x-sun.icon name="phone" class="icon" />{{ $profile['phoneDisplay'] }}</x-phone-link>
        @if($company->website)
          <a href="{{ $company->website }}" rel="nofollow noopener" target="_blank" class="mt-2 btn-ghost w-full"><x-sun.icon name="globe" class="icon" stroke-linecap="butt" stroke-linejoin="miter" />{{ __('portal.profile.sidebar.website') }}</a>
        @endif
        <p class="mt-3 text-sm text-zinc-500 text-center">{{ __('portal.profile.sidebar.free_note') }}</p>
        @if($company->full_address)
          <address class="not-italic mt-4 pt-4 border-t border-zinc-200 text-sm text-zinc-500 leading-relaxed">{{ trim("{$company->street} {$company->house_no}") }}<br>{{ trim("{$company->zipcode} {$company->city?->name}") }}<br><a href="{{ $profile['mapsUrl'] }}" class="text-brand hover:underline" target="_blank" rel="noopener">{{ __('portal.profile.location.show_on_map') }}</a></address>
        @endif
      </div>
      @if(\App\View\Components\AdSlot::hasSlotsForPosition('listing_detail_sidebar'))
        <div class="rounded-2xl bg-zinc-100 overflow-hidden" style="min-height:600px">
          <span class="block text-xs text-zinc-400 px-3 pt-2">{{ __('portal.layout.ad_label') }}</span>
          <x-ad-slot position="listing_detail_sidebar" />
        </div>
      @endif
    </aside>
  </div>

  <!-- Weitere Eintraege am Ort -->
  @if($profile['nearby']->isNotEmpty())
    <section class="mt-12 md:mt-16">
      <x-sun.section-heading :title="__('portal.profile.nearby.heading', ['stadt' => $company->city->name])" :href="$profile['cityUrl']" :link="__('portal.profile.nearby.all', ['anzahl' => number_format($profile['nearbyCount'], 0, ',', '.')])" />
      <div class="flex xl:grid xl:grid-cols-3 gap-4 overflow-x-auto xl:overflow-visible -mx-4 px-4 xl:mx-0 xl:px-0 pb-2 xl:pb-0 scroll-snap">
        @foreach($profile['nearby'] as $other)
          <article class="card-interactive p-5 flex flex-col gap-3 min-w-[85%] sm:min-w-[60%] md:min-w-[45%] xl:min-w-0">
            <div class="flex items-start gap-3">
              <span class="size-12 rounded-2xl bg-brand-50 text-brand-700 font-bold flex items-center justify-center shrink-0" aria-hidden="true">{{ \App\Themes\SunV2\ProfileViewComposer::initials($other->name) }}</span>
              <div class="min-w-0">
                <h3 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ $other->portal_url }}" class="hover:text-brand">{{ $other->name }}</a></h3>
                @if($other->rating_count > 0)
                  <div class="flex items-center gap-1.5 mt-1"><x-sun.stars :rating="$other->rating" /><span class="text-sm font-medium text-zinc-700">{{ number_format((float) $other->rating, 1, ',', '') }}</span><span class="text-sm text-zinc-500">({{ $other->rating_count }})</span></div>
                @else
                  <div class="mt-1"><span class="text-sm text-zinc-500">{{ __('portal.layout.card.no_reviews') }}</span></div>
                @endif
              </div>
            </div>
            @if($other->full_address)
              <p class="text-sm text-zinc-500 flex items-start gap-1.5"><span class="mt-0.5"><x-sun.icon name="map-pin" class="size-4 shrink-0" stroke-linecap="butt" stroke-linejoin="miter" /></span><span>{{ $other->full_address }}</span></p>
            @endif
            @if($other->categories->isNotEmpty())
              <div class="flex flex-wrap gap-2">
                @foreach($other->categories->take(3) as $category)
                  <span class="pill">{{ $category->name }}</span>
                @endforeach
              </div>
            @endif
            <a href="{{ $other->portal_url }}" class="btn-secondary mt-auto">{{ __('portal.layout.card.profile') }}</a>
          </article>
        @endforeach
      </div>
    </section>
  @endif

</div>

<!-- ================= MOBILE: STICKY BOTTOM BAR ================= -->
<div class="fixed bottom-0 inset-x-0 z-30 bg-white border-t border-zinc-200 p-3 flex gap-2 lg:hidden" style="padding-bottom:max(.75rem,env(safe-area-inset-bottom))">
  <x-phone-link :number="$company->tel" :fallback="false" class="btn-secondary size-11 min-h-11 px-0 shrink-0" aria-label="{{ __('portal.layout.card.call_label', ['telefon' => $profile['phoneDisplay']]) }}"><x-sun.icon name="phone" class="icon" /></x-phone-link>
  <button type="button" class="btn-primary flex-1 whitespace-nowrap" data-open-lead>{{ $requestCta }}</button>
</div>

@include('partials.sun.lead-dialog', ['company' => $company])
@endsection
