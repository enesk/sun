{{--
    Premium im Betriebsbereich, Theme sun-v2 (Vorlage premium-elektrikerportal.html, 1:1).
    Daten: OwnerDashboardController::premium(). Texte: lang/de/portal.php (owner.premium.*).

    - Zahlweise Monatlich/Jaehrlich ohne JavaScript: zwei Radios, Preis und Checkout-Link
      schalten per :has() um. Buchung ueber Livewire PlanCheckout (#5), das den Betrieb an die Subscription bindet.
    - Preise und Testphase stehen in den Texten (wie im alten View), Stand der Plaene
      premium-monthly 9,90 € / premium-yearly 99 €, je 30 Tage Test.
    - Vorschaukarten nutzen die Daten der Firma (Name, Sterne, Adresse, Kategorien).
    - Mit aktivem Premium steht statt der Verkaufsseite die Abo-Karte.
--}}
@extends('layouts.panel')

@php
    $initials = collect(preg_split('/\s+/', trim((string) $company->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    $ratingLabel = number_format((float) $company->rating, 1, ',', '');
    $address = trim(collect([trim("{$company->street} {$company->house_no}"), trim("{$company->zipcode} ".($company->city?->name ?? ''))])->filter()->join(', '));
    $leistungen = $company->categories->pluck('name')->take(4);
    $features = ['leads' => 'smartphone', 'description' => 'text', 'photos' => 'image', 'hours' => 'clock', 'replies' => 'reply', 'stats' => 'chart'];
@endphp

@section('title', __('portal.owner.premium.title_short'))

@section('content')
  <div class="{{ $company->is_premium ? '' : 'pb-24 md:pb-0' }}">
    <div>
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.premium.title', ['firma' => $company->name]) }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.premium.intro') }}</p>
    </div>

    @if($company->is_premium)
      {{-- Premium aktiv: Abo-Karte --}}
      <section class="mt-6 rounded-2xl bg-brand-50 border-2 border-brand p-5 md:p-6" aria-labelledby="sec-aktiv">
        <p class="pill-brand bg-white"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</p>
        <h2 id="sec-aktiv" class="mt-3 text-2xl font-semibold text-zinc-900">{{ __('portal.owner.premium.active.title') }}</h2>
        <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.premium.active.text') }}</p>

        @if($subscription)
          <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-base">
            <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.premium.active.plan') }}</dt><dd class="font-medium text-zinc-900">{{ $subscription->plan->name ?? __('portal.owner.edit.locked.badge') }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.premium.active.price') }}</dt><dd class="font-medium text-zinc-900">{{ money($subscription->price, $subscription->currency->code ?? 'EUR') }}</dd></div>
            @if($subscription->ends_at)
              <div><dt class="text-sm text-zinc-500">{{ $subscription->is_canceled_at_end_of_cycle ? __('portal.owner.premium.active.until') : __('portal.owner.premium.active.renews') }}</dt><dd class="font-medium text-zinc-900">{{ $subscription->ends_at->format('d.m.Y') }}</dd></div>
            @endif
            <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.premium.active.status') }}</dt><dd class="font-medium {{ $subscription->is_canceled_at_end_of_cycle ? 'text-red-600' : 'text-emerald-700' }}">{{ $subscription->is_canceled_at_end_of_cycle ? __('portal.owner.premium.active.canceled') : __('portal.owner.overview.entry.active') }}</dd></div>
          </dl>
          @if($subscription->is_canceled_at_end_of_cycle)
            <p class="mt-4 text-base text-zinc-700">{{ __('portal.owner.premium.active.canceled_text', ['datum' => $subscription->ends_at?->format('d.m.Y') ?? '']) }}</p>
          @endif
          <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('portal.owner.subscription') }}" class="btn-secondary">{{ __('premium.subscription.title') }}</a>
            <a href="{{ route('portal.owner.plan') }}" class="btn-secondary">{{ __('premium.plan.title') }}</a>
            @if($canDiscardCancellation || $canCancel)
              <a href="{{ route('portal.owner.subscription') }}#kuendigen" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ $canDiscardCancellation ? __('premium.subscription.discard') : __('premium.plan.cancel') }}</a>
            @endif
          </div>
        @endif
      </section>
    @else

    {{-- Hero: das Argument aus den eigenen Daten des Betriebs --}}
    <section class="mt-6 rounded-2xl bg-brand-50 p-5 md:p-8 lg:grid lg:grid-cols-[1fr_20rem] lg:gap-8 items-center" aria-labelledby="sec-hero">
      <div>
        <h2 id="sec-hero" class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900 max-w-prose">{{ __('portal.owner.premium.hero.title') }}</h2>
        <ul class="mt-4 space-y-2 text-base text-zinc-700">
          @if($company->rating_count > 0)
            <li class="flex gap-2"><x-sun.icon name="check" class="icon text-emerald-600 mt-0.5" /><span><span class="font-semibold text-zinc-900">{{ trans_choice('portal.owner.premium.hero.rating', $company->rating_count, ['anzahl' => $company->rating_count, 'wert' => $ratingLabel]) }}</span> {{ __('portal.owner.premium.hero.rating_suffix') }}</span></li>
          @endif
          <li class="flex gap-2"><x-sun.icon name="minus" class="icon text-zinc-400 mt-0.5" /><span>{{ __('portal.owner.premium.hero.empty_before') }} <span class="font-semibold text-zinc-900">{{ __('portal.owner.premium.hero.empty_strong') }}</span>.</span></li>
          <li class="flex gap-2"><x-sun.icon name="minus" class="icon text-zinc-400 mt-0.5" /><span>{{ __('portal.owner.premium.hero.leads_before') }} <span class="font-semibold text-zinc-900">{{ __('portal.owner.premium.hero.leads_strong') }}</span>.</span></li>
        </ul>
        <div class="mt-6">
          <a href="#plan" class="btn-primary w-full sm:w-auto shrink-0 whitespace-nowrap">{{ __('portal.owner.premium.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.premium.hero.note') }}</p>
        </div>
      </div>
      <div class="mt-6 lg:mt-0 card p-5">
        <p class="text-sm text-zinc-500">{{ __('portal.owner.premium.hero.side_label') }}</p>
        <p class="mt-1 text-3xl font-bold text-zinc-900">{{ __('portal.owner.premium.hero.side_title') }}</p>
        <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.premium.hero.side_text') }}</p>
      </div>
    </section>

    {{-- Vorher / Nachher --}}
    <section class="mt-8 md:mt-10" aria-labelledby="sec-vergleich">
      <h2 id="sec-vergleich" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.premium.compare.title') }}</h2>
      <div class="mt-4 grid md:grid-cols-2 gap-4 md:gap-6">
        <div class="card p-5">
          <p class="pill">{{ __('portal.owner.premium.compare.basic_badge') }}</p>
          <div class="mt-4 rounded-xl border border-zinc-200 p-4" aria-hidden="true">
            <div class="flex gap-3 items-center">
              <div class="size-14 shrink-0 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center font-bold">{{ $initials }}</div>
              <div class="min-w-0"><p class="font-semibold text-zinc-900 truncate">{{ $company->name }}</p><p class="flex items-center gap-1 text-sm"><x-sun.stars :rating="$company->rating" /><span class="text-zinc-500 ml-1">{{ $company->rating_count }}</span></p></div>
            </div>
            @if($address !== '')<p class="mt-3 text-sm text-zinc-500">{{ $address }}</p>@endif
            <div class="mt-3 flex gap-2"><span class="btn-secondary text-sm min-h-9 px-3">{{ __('portal.owner.premium.compare.call') }}</span><span class="btn-ghost text-sm min-h-9 px-3">{{ __('portal.owner.premium.compare.website') }}</span></div>
            <div class="mt-4 rounded-xl bg-zinc-50 p-3 text-sm text-zinc-500">{{ __('portal.owner.premium.compare.basic_hint') }}</div>
          </div>
          <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.premium.compare.basic_text') }}</p>
        </div>
        <div class="card border-2 border-brand p-5">
          <p class="pill-brand"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.premium.compare.premium_badge') }}</p>
          <div class="mt-4 rounded-xl border-l-4 border-brand border border-zinc-200 p-4" aria-hidden="true">
            <div class="flex gap-3 items-center">
              <div class="size-14 shrink-0 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center font-bold">{{ $initials }}</div>
              <div class="min-w-0"><p class="font-semibold text-zinc-900">{{ $company->name }} <span class="pill-brand text-xs ml-1">{{ __('portal.owner.edit.locked.badge') }}</span></p><p class="flex flex-wrap items-center gap-1 text-sm"><x-sun.stars :rating="$company->rating" /><span class="text-zinc-500 ml-1">{{ $company->rating_count }}</span> <span class="text-emerald-600 ml-2">{{ __('portal.owner.premium.compare.open_now') }}</span></p></div>
            </div>
            <div class="mt-3 grid grid-cols-4 gap-1.5"><div class="aspect-square rounded-lg bg-brand-100"></div><div class="aspect-square rounded-lg bg-brand-100"></div><div class="aspect-square rounded-lg bg-brand-100"></div><div class="aspect-square rounded-lg bg-brand-100"></div></div>
            @if($leistungen->isNotEmpty())
              <div class="mt-3 flex flex-wrap gap-1.5 text-xs">@foreach($leistungen as $leistung)<span class="pill">{{ $leistung }}</span>@endforeach</div>
            @endif
            <p class="mt-3 text-sm text-zinc-700">{{ \Illuminate\Support\Str::limit(trim(strip_tags(\Illuminate\Support\Str::markdown((string) $company->description))) ?: (__('portal.owner.premium.compare.sample_description', ['firma' => $company->name, 'stadt' => $company->city?->name ?? ''])), 110) }}</p>
            <div class="mt-3 flex gap-2"><span class="btn-primary text-sm min-h-9 px-3">{{ __('portal.profile.request_cta') }}</span><span class="btn-secondary text-sm min-h-9 px-3">{{ __('portal.owner.premium.compare.call') }}</span></div>
          </div>
          <p class="mt-3 text-sm text-zinc-700">{{ __('portal.owner.premium.compare.premium_text') }}</p>
        </div>
      </div>
    </section>

    {{-- Leistungen --}}
    <section class="mt-8 md:mt-10" aria-labelledby="sec-leistungen">
      <h2 id="sec-leistungen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.premium.features.title') }}</h2>
      <ul class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($features as $key => $icon)
          <li class="card p-5">
            <span class="inline-flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-sun.icon :name="$icon" class="size-6 shrink-0" /></span>
            <h3 class="mt-3 text-lg font-semibold text-zinc-900">{{ __("portal.owner.premium.features.items.{$key}.title") }}</h3>
            <p class="mt-1 text-base text-zinc-700">{{ __("portal.owner.premium.features.items.{$key}.text") }}</p>
          </li>
        @endforeach
      </ul>
    </section>

    {{-- Plan & Kauf --}}
    <section id="plan" class="group/billing mt-8 md:mt-10 grid lg:grid-cols-[1fr_22rem] gap-4 md:gap-6 items-start scroll-mt-24">
      <div class="card p-5 md:p-6 overflow-x-auto">
        <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.premium.table.title') }}</h2>
        <table class="mt-4 w-full min-w-[28rem] text-base">
          <thead><tr class="border-b border-zinc-200"><th class="py-2 text-left text-sm font-medium text-zinc-500">{{ __('portal.owner.premium.table.feature') }}</th><th class="py-2 px-3 text-center text-sm font-medium text-zinc-500">{{ __('portal.owner.premium.table.basic') }}</th><th class="py-2 px-3 text-center text-sm font-semibold text-brand-700 bg-brand-50 rounded-t-xl">{{ __('portal.owner.edit.locked.badge') }}</th></tr></thead>
          <tbody>
            @foreach(__('portal.owner.premium.table.rows') as $row)
              <tr class="{{ $loop->last ? '' : 'border-b border-zinc-200' }}">
                <th scope="row" class="py-3 pr-3 text-left font-normal text-zinc-700">{{ $row['label'] }}</th>
                @foreach(['basic' => '', 'premium' => 'bg-brand-50'] as $plan => $cellClass)
                  <td class="py-3 px-3 text-center {{ $cellClass }}">
                    @if($row[$plan] === true)
                      <x-sun.icon name="check" class="icon text-emerald-600 mx-auto" /><span class="sr-only">{{ __('portal.owner.premium.table.yes') }}</span>
                    @elseif($row[$plan] === false)
                      <x-sun.icon name="minus" class="icon text-zinc-300 mx-auto" /><span class="sr-only">{{ __('portal.owner.premium.table.no') }}</span>
                    @else
                      <span class="text-sm {{ $plan === 'premium' ? 'font-medium text-zinc-900' : 'text-zinc-500' }}">{{ $row[$plan] }}</span>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Buchung, Planwechsel und Kuendigung: Livewire PlanCheckout (#5) --}}
      <aside class="lg:sticky lg:top-24">
        @livewire('portal.company.plan-checkout', ['company' => $company])
        {{-- Top-Platzierung als Add-on (#6) --}}
        <div class="mt-4">
          @livewire('portal.company.featured-placement-booking', ['company' => $company])
        </div>
      </aside>
    </section>

    {{-- Einwaende --}}
    <section class="mt-8 md:mt-10 card p-5 md:p-6" aria-labelledby="sec-faq">
      <h2 id="sec-faq" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.premium.faq.title') }}</h2>
      <div class="mt-2 divide-y divide-zinc-200">
        @foreach(__('portal.owner.premium.faq.items') as $item)
          <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none text-lg font-semibold text-zinc-900 min-h-11 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand rounded-xl [&::-webkit-details-marker]:hidden">{{ $item['q'] }}<x-sun.icon name="chevron-right" class="icon text-zinc-400 transition-transform duration-200 group-open:rotate-90" /></summary>
            <p class="mt-2 text-base leading-relaxed text-zinc-700 max-w-prose">{{ $item['a'] }}</p>
          </details>
        @endforeach
      </div>
    </section>

    {{-- Abschluss --}}
    <section class="mt-8 md:mt-10 rounded-2xl bg-brand-50 p-5 md:p-8 text-center" aria-labelledby="sec-abschluss">
      <h2 id="sec-abschluss" class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
        {{ $company->rating_count > 0 ? trans_choice('portal.owner.premium.closing.title', $company->rating_count, ['anzahl' => $company->rating_count, 'sterne' => $ratingLabel]) : __('portal.owner.premium.closing.title_no_reviews') }}
      </h2>
      <a href="#plan" class="btn-primary mt-5">{{ __('portal.owner.premium.cta') }}</a>
      <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.premium.closing.note') }}</p>
    </section>

    {{-- Aktionsleiste mobil --}}
    <div class="fixed bottom-0 inset-x-0 z-40 flex items-center gap-2 border-t border-zinc-200 bg-white p-3 md:hidden">
      <div class="flex-1 px-2"><p class="text-sm font-semibold text-zinc-900">{{ __('portal.owner.premium.bar.title') }}</p><p class="text-xs text-zinc-500">{{ __('portal.owner.premium.bar.note') }}</p></div>
      <a href="#plan" class="btn-primary flex-1">{{ __('portal.owner.premium.bar.cta') }}</a>
    </div>
    @endif
  </div>
@endsection
