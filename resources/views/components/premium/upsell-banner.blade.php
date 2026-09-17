{{--
    Upsell-Banner fuer Free-Betriebe im Betriebsbereich (#17), dezent und
    einmal pro Session schliessbar (POST, merkt sich die Session – ohne JavaScript).
    Texte: lang/de/premium.php (upsell_banner.*).

    <x-premium.upsell-banner :company="$company" />
--}}
@props(['company'])
@php
    $show = $company instanceof \App\Models\Portal\Company
        && app(\App\Services\Premium\CompanyEntitlementService::class)->effectiveTier($company) === \App\Enums\PlanTier::Free
        && ! session(\App\Http\Controllers\Portal\OwnerDashboardController::UPSELL_BANNER_SESSION_KEY, false);
@endphp
@if($show)
  <aside {{ $attributes->class('rounded-2xl border border-brand-100 bg-brand-50 p-4 md:p-5') }} aria-labelledby="upsell-banner-title">
    <div class="flex items-start gap-3">
      <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-brand"><x-sun.icon name="sparkles" class="size-5" /></span>
      <div class="min-w-0 flex-1">
        <p id="upsell-banner-title" class="text-base font-semibold text-zinc-900">{{ __('premium.upsell_banner.title') }}</p>
        <ul class="mt-1 space-y-1 text-sm text-zinc-700">
          <li class="flex gap-2"><x-sun.icon name="info" class="size-4 shrink-0 mt-0.5 text-zinc-400" />{{ __('premium.upsell_banner.competitors') }}</li>
          <li class="flex gap-2"><x-sun.icon name="info" class="size-4 shrink-0 mt-0.5 text-zinc-400" />{{ __('premium.upsell_banner.marketplace') }}</li>
        </ul>
        <a href="{{ route('portal.premium.pricing') }}" class="btn-secondary mt-3 text-sm min-h-9 px-3">{{ __('premium.upsell_banner.cta') }}</a>
      </div>
      <form method="POST" action="{{ route('portal.owner.upsell-banner.dismiss') }}" class="shrink-0">
        @csrf
        <button type="submit" class="btn-ghost min-h-11 px-3 text-zinc-500 hover:bg-white" aria-label="{{ __('premium.upsell_banner.dismiss') }}">
          <x-sun.icon name="x" class="icon" />
        </button>
      </form>
    </div>
  </aside>
@endif
