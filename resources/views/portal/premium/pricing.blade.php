{{--
    Oeffentliche Preisseite /premium (#17), ein Template fuer alle Portale (Theme sun-v2).
    Daten: PremiumPricingController. Plaene, Features, Limits: config/premium.php;
    Preise: Tenant-Konfiguration (TenantPremiumPricing). Texte: lang/de/premium.php.

    - Zahlweise Monatlich/Jaehrlich ohne JavaScript: zwei Radios, die Preise schalten per :has() um.
    - Gebucht wird im Betriebsbereich (PlanCheckout); ohne Betrieb fuehren die Buttons zum Eintragen.
    - Robots/Canonical setzt SeoService::forPricingPage().
--}}
@extends('layouts.sun')

@php
    $portalName = ($currentTenant?->terms ?? \App\Support\Tenancy\TenantTerms::defaults())['portal'];
    $meta = app(\App\Services\Seo\SeoService::class)->pricingMeta($portalName);
    $bookUrl = $company !== null ? route('portal.owner.premium').'#plan' : route('portal.companies.create');
    $formatLimit = fn ($value) => $value === null ? __('premium.limit.unlimited') : __('premium.limit.count', ['anzahl' => number_format($value, 0, ',', '.')]);
@endphp

@section('title', $meta['title'])
@section('meta_description', $meta['description'])

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16 group/billing">
  <x-sun.breadcrumb :items="\App\Support\Breadcrumb::forPricing()" />

  <div class="mt-4 max-w-3xl">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('premium.pricing.title', ['portal' => $portalName]) }}</h1>
    <p class="mt-2 text-lg text-zinc-700">{{ __('premium.pricing.intro') }}</p>
  </div>

  {{-- Zahlweise --}}
  <fieldset class="mt-6 inline-grid grid-cols-2 gap-2 p-1 rounded-xl bg-white border border-zinc-200">
    <legend class="sr-only">{{ __('premium.pricing.billing_label') }}</legend>
    <label class="flex min-h-11 px-5 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold text-zinc-700 hover:bg-zinc-50 has-checked:bg-brand has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-brand">
      <input type="radio" name="billing" id="billing-monthly" value="monthly" class="sr-only" checked>{{ __('premium.pricing.monthly') }}
    </label>
    <label class="flex min-h-11 px-5 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold text-zinc-700 hover:bg-zinc-50 has-checked:bg-brand has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-brand">
      <input type="radio" name="billing" id="billing-yearly" value="yearly" class="sr-only">{{ __('premium.pricing.yearly') }}
    </label>
  </fieldset>

  {{-- Plan-Karten --}}
  <section class="mt-6 grid md:grid-cols-3 gap-4 md:gap-6 items-stretch" aria-label="{{ __('premium.pricing.billing_label') }}">
    @foreach($plans as $plan)
      @php
          $tier = $plan['tier'];
          $recommended = $tier === \App\Enums\PlanTier::Pro;
          $isCurrent = $currentTier === $tier;
      @endphp
      <article class="card p-5 md:p-6 flex flex-col {{ $recommended ? 'border-2 border-brand' : '' }}" aria-labelledby="plan-{{ $tier->value }}">
        <div class="flex items-center justify-between gap-2">
          <h2 id="plan-{{ $tier->value }}" class="text-2xl font-semibold text-zinc-900">{{ __("premium.tiers.{$tier->value}") }}</h2>
          @if($recommended)
            <span class="pill-brand"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('premium.pricing.recommended') }}</span>
          @endif
        </div>
        <p class="mt-1 text-base text-zinc-500">{{ __("premium.tier_taglines.{$tier->value}") }}</p>

        <div class="mt-4 min-h-24">
          @if($tier === \App\Enums\PlanTier::Free)
            <p class="text-3xl font-bold text-zinc-900">{{ __('premium.price.free') }}</p>
          @elseif(! $plan['available'])
            <p class="text-2xl font-semibold text-zinc-500">{{ __('premium.price.unavailable') }}</p>
          @else
            <div class="group-has-[#billing-yearly:checked]/billing:hidden">
              @if($plan['monthly'] !== null)
                <p class="text-3xl font-bold text-zinc-900">{{ money($plan['monthly'], $currency)->format('de_DE') }}<span class="text-base font-medium text-zinc-500"> {{ __('premium.price.per_month') }}</span></p>
              @else
                <p class="text-2xl font-semibold text-zinc-500">{{ __('premium.price.unavailable') }}</p>
              @endif
            </div>
            <div class="hidden group-has-[#billing-yearly:checked]/billing:block">
              @if($plan['yearly'] !== null)
                <p class="text-3xl font-bold text-zinc-900">{{ money($plan['yearly'], $currency)->format('de_DE') }}<span class="text-base font-medium text-zinc-500"> {{ __('premium.price.per_year') }}</span></p>
                @if($plan['free_months'] > 0)
                  <p class="mt-1 text-sm font-semibold text-brand-700">{{ trans_choice('premium.price.free_months', $plan['free_months']) }}</p>
                @endif
              @else
                <p class="text-2xl font-semibold text-zinc-500">{{ __('premium.price.unavailable') }}</p>
              @endif
            </div>
            <p class="text-xs text-zinc-500">{{ __('premium.price.vat') }}</p>
          @endif
        </div>

        <ul class="mt-4 space-y-2 text-base text-zinc-700 flex-1">
          @foreach($features as $row)
            @continue($row['cells'][$tier->value] === false)
            <li class="flex gap-2">
              <x-sun.icon name="check" class="icon text-emerald-600 mt-0.5 shrink-0" />
              <span>
                {{ __("premium.features.{$row['feature']->value}") }}@if(! is_bool($row['cells'][$tier->value])): <span class="font-semibold text-zinc-900">{{ $formatLimit($row['cells'][$tier->value]) }}</span>@endif
              </span>
            </li>
          @endforeach
        </ul>

        <div class="mt-6">
          @if($isCurrent)
            <p class="btn-secondary w-full pointer-events-none">{{ __('premium.pricing.cta_current') }}</p>
            @if($tier !== \App\Enums\PlanTier::Free)
              <a href="{{ route('portal.owner.plan') }}" class="btn-ghost mt-2 w-full">{{ __('premium.pricing.cta_manage') }}</a>
            @endif
          @elseif($tier === \App\Enums\PlanTier::Free)
            @if($company === null)
              <a href="{{ route('portal.companies.create') }}" class="btn-secondary w-full">{{ __('premium.pricing.cta_free') }}</a>
            @endif
          @elseif($plan['available'])
            <a href="{{ $bookUrl }}" class="{{ $recommended ? 'btn-primary' : 'btn-secondary' }} w-full">{{ __('premium.pricing.cta_book', ['plan' => __("premium.tiers.{$tier->value}")]) }}</a>
            @if($trialDays > 0 && $company === null)
              <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('premium.pricing.trial', ['tage' => $trialDays]) }}</p>
            @endif
          @endif
        </div>
      </article>
    @endforeach
  </section>

  {{-- Add-on Top-Platzierung --}}
  <section class="mt-8 md:mt-10 rounded-2xl bg-brand-50 p-5 md:p-8 lg:grid lg:grid-cols-[1fr_22rem] lg:gap-8 items-start" aria-labelledby="sec-addon">
    <div>
      <h2 id="sec-addon" class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('premium.pricing.addon.title') }}</h2>
      <p class="mt-2 text-base text-zinc-700 max-w-prose">{{ __('premium.pricing.addon.text', ['max' => $addon['max']]) }}</p>
      <p class="mt-2 text-sm text-zinc-500">{{ __('premium.pricing.addon.requires') }}</p>
      @if($addon['available'] && $addon['price'] !== null)
        <p class="mt-4 text-3xl font-bold text-zinc-900">{{ money($addon['price'], $currency)->format('de_DE') }}<span class="text-base font-medium text-zinc-500"> {{ __('premium.price.per_month') }}</span></p>
        <p class="text-xs text-zinc-500">{{ __('premium.price.vat') }}</p>
      @else
        <p class="mt-4 text-2xl font-semibold text-zinc-500">{{ __('premium.price.unavailable') }}</p>
      @endif
    </div>

    <div class="mt-6 lg:mt-0 card p-5">
      @if($addon['city'] !== null && $addon['free'] !== null)
        <p class="text-xl font-semibold {{ $addon['free'] > 0 ? 'text-zinc-900' : 'text-red-600' }}">
          {{ trans_choice('premium.pricing.addon.slots', $addon['free'], ['anzahl' => $addon['free'], 'stadt' => $addon['city']->name]) }}
        </p>
        @if($addon['category'] !== null)
          <p class="mt-1 text-sm text-zinc-500">{{ __('premium.pricing.addon.slots_category', ['branche' => $addon['category']->name]) }}</p>
        @endif
      @endif

      @unless($addon['from_profile'])
        <form method="GET" action="{{ route('portal.premium.pricing') }}" class="{{ $addon['city'] !== null ? 'mt-4' : '' }} space-y-3">
          <p class="text-base font-medium text-zinc-900">{{ __('premium.pricing.addon.choose') }}</p>
          <div>
            <label for="addon-city" class="text-sm text-zinc-500">{{ __('premium.pricing.addon.city') }}</label>
            <select id="addon-city" name="stadt" class="input mt-1 w-full" required>
              <option value="">{{ __('premium.pricing.addon.choose_city') }}</option>
              @foreach($addon['city_options'] as $option)
                <option value="{{ $option->slug }}" @selected($addon['city']?->is($option))>{{ $option->name }}</option>
              @endforeach
            </select>
          </div>
          @if($addon['category_options']->count() > 1)
            <div>
              <label for="addon-category" class="text-sm text-zinc-500">{{ __('premium.pricing.addon.category') }}</label>
              <select id="addon-category" name="branche" class="input mt-1 w-full">
                @foreach($addon['category_options'] as $option)
                  <option value="{{ $option->slug }}" @selected($addon['category']?->is($option))>{{ $option->name }}</option>
                @endforeach
              </select>
            </div>
          @endif
          <button type="submit" class="btn-secondary w-full">{{ __('premium.pricing.addon.show') }}</button>
        </form>
      @endunless

      @if($addon['available'])
        <a href="{{ $bookUrl }}" class="btn-primary mt-4 w-full">{{ __('premium.pricing.addon.cta') }}</a>
      @endif
    </div>
  </section>

  {{-- Vergleichstabelle --}}
  <section class="mt-8 md:mt-10 card p-5 md:p-6 overflow-x-auto" aria-labelledby="sec-compare">
    <h2 id="sec-compare" class="text-2xl font-semibold text-zinc-900">{{ __('premium.pricing.compare_title') }}</h2>
    <table class="mt-4 w-full min-w-[32rem] text-base">
      <thead>
        <tr class="border-b border-zinc-200">
          <th scope="col" class="py-2 text-left text-sm font-medium text-zinc-500">{{ __('premium.pricing.compare_feature') }}</th>
          @foreach($plans as $plan)
            <th scope="col" class="py-2 px-3 text-center text-sm {{ $plan['tier'] === \App\Enums\PlanTier::Pro ? 'font-semibold text-brand-700 bg-brand-50 rounded-t-xl' : 'font-medium text-zinc-500' }}">{{ __("premium.tiers.{$plan['tier']->value}") }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($features as $row)
          <tr class="{{ $loop->last ? '' : 'border-b border-zinc-200' }}">
            <th scope="row" class="py-3 pr-3 text-left font-normal text-zinc-700">{{ __("premium.features.{$row['feature']->value}") }}</th>
            @foreach($plans as $plan)
              @php($cell = $row['cells'][$plan['tier']->value])
              <td class="py-3 px-3 text-center {{ $plan['tier'] === \App\Enums\PlanTier::Pro ? 'bg-brand-50' : '' }}">
                @if($cell === true)
                  <x-sun.icon name="check" class="icon text-emerald-600 mx-auto" /><span class="sr-only">{{ __('premium.limit.yes') }}</span>
                @elseif($cell === false)
                  <x-sun.icon name="minus" class="icon text-zinc-300 mx-auto" /><span class="sr-only">{{ __('premium.limit.no') }}</span>
                @else
                  <span class="text-sm font-medium text-zinc-900">{{ $formatLimit($cell) }}</span>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
</div>
@endsection
