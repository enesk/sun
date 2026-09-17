{{--
    Badge "Verifizierter Betrieb" (#11, SUN-PREM-006).
    Sichtbar nur mit companies.verified_at UND Feature verified_badge; nach einem
    Downgrade bleibt verified_at stehen, das Badge verschwindet.
    Theme-neutral (nur Tailwind-Utilities); sun-v2 bindet die Datei per @source ein.
--}}
@props(['company', 'size' => 'md'])
@if($company?->verified_at && app(\App\Services\Premium\CompanyEntitlementService::class)->can($company, \App\Enums\PremiumFeature::VerifiedBadge))
  <span {{ $attributes->class([
      'group relative inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 align-middle',
      'px-2 py-0.5 text-xs' => $size === 'sm',
      'px-2.5 py-1 text-sm' => $size !== 'sm',
  ]) }} tabindex="0">
    <svg class="{{ $size === 'sm' ? 'size-3.5' : 'size-4' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    <span>{{ __('portal.layout.card.verified_business') }}</span>
    <span class="sr-only">: {{ __('portal.layout.card.verified_business_tooltip') }}</span>
    <span role="tooltip" aria-hidden="true" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-zinc-900 px-2.5 py-1.5 text-xs font-normal text-white shadow-lg group-hover:block group-focus:block">{{ __('portal.layout.card.verified_business_tooltip') }}</span>
  </span>
@endif
