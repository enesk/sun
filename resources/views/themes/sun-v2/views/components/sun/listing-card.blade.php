{{--
    Ergebniskarte der Suche (Vorlage elektrikerportal-suche.html). Premium mit Markenleiste links.
    Plan-Darstellung (#7), nur ueber den CompanyEntitlementService und die Spalte
    featured_slot aus der Listen-Query (Company::withFeaturedSlot), ohne Einzelabfragen:
    - aktive Top-Platzierung: Rahmen in Markenfarbe, Badge "Empfohlen", Logo und Foto
    - Pro/Premium: Foto statt Initialen (Verifiziert-Badge kommt aus x-company.verified-badge)
    - Free: unveraendert. Ohne Galeriefoto bleibt der Platzhalter.
--}}
@props(['company', 'distance' => null])
@php
    $status = \App\Themes\SunV2\OpeningStatus::for($company);
    $rating = number_format((float) $company->rating, 1, ',', '');
    $words = preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $company->name))) ?: [];
    $initials = count($words) > 1
        ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
        : mb_substr($words[0] ?? '?', 0, 2);
    $entitlements = app(\App\Services\Premium\CompanyEntitlementService::class);
    $featured = $company->featured_slot !== null
        && $entitlements->can($company, \App\Enums\PremiumFeature::FeaturedPlacement);
    $paid = $featured || $entitlements->effectiveTier($company)->isAtLeast(\App\Enums\PlanTier::Pro);
    $photoUrl = $paid ? $company->card_photo_url : null;
    $logoUrl = $featured ? $company->logo_thumb_url : null;
    $avatarUrl = $logoUrl ?? ($photoUrl && ! $featured ? $company->card_photo_thumb_url : null);
@endphp
<article @class([
    'card-interactive p-5 flex flex-col lg:flex-row lg:items-start gap-4',
    'border-l-4 border-l-brand' => $company->is_premium && ! $featured,
    'border-brand ring-1 ring-brand' => $featured,
]) data-stats-company="{{ $company->id }}" data-stats-source="listing">
  @if($featured && $photoUrl)
    <div class="relative shrink-0 lg:w-44">
      <img src="{{ $photoUrl }}" alt="{{ __('portal.layout.card.photo_alt', ['firma' => $company->name]) }}" width="640" height="360" class="aspect-video w-full rounded-xl object-cover lg:aspect-[4/3]" loading="lazy">
      <span class="pill-brand absolute left-2 top-2 bg-white shadow-sm">
        <x-sun.icon name="star" class="size-4 shrink-0" />{{ __('portal.layout.card.recommended') }}
      </span>
    </div>
  @endif
  <div class="flex-1 min-w-0 flex flex-col gap-3">
    @if($featured && ! $photoUrl)
      <span class="pill-brand self-start"><x-sun.icon name="star" class="size-4 shrink-0" />{{ __('portal.layout.card.recommended') }}</span>
    @endif
    <div class="flex items-start gap-3">
      @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" width="48" height="48" class="size-12 rounded-2xl border border-zinc-200 bg-white {{ $logoUrl ? 'object-contain p-1' : 'object-cover' }} shrink-0" loading="lazy">
      @else
        <span class="size-12 rounded-2xl bg-brand-50 text-brand-700 font-bold flex items-center justify-center shrink-0" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
      @endif
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2 min-w-0">
            <h2 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ $company->portal_url }}" class="hover:text-brand">{{ $company->name }}</a></h2>
            <x-company.verified-badge :company="$company" size="sm" />
          </div>
          @if($company->is_premium && ! $featured)
            <span class="pill-brand shrink-0">{{ __('portal.layout.card.premium') }}</span>
          @endif
        </div>
        <div class="mt-1">
          @if($company->rating_count > 0)
            <div class="flex items-center gap-1.5">
              <x-sun.stars :rating="$company->rating" />
              <span class="text-sm font-medium text-zinc-700">{{ $rating }}</span><span class="text-sm text-zinc-500">({{ $company->rating_count }})</span>
              <span class="sr-only">{{ trans_choice('portal.layout.card.rating_sr', $company->rating_count, ['wertung' => $rating, 'anzahl' => number_format($company->rating_count, 0, ',', '.')]) }}</span>
            </div>
          @else
            <span class="text-sm text-zinc-500">{{ __('portal.layout.card.no_reviews') }}</span>
          @endif
        </div>
      </div>
    </div>
    <div class="flex-1 min-w-0">
      @if($company->full_address)
        <p class="text-sm text-zinc-500 flex items-start gap-1.5">
          <span class="mt-0.5"><x-sun.icon name="map-pin" class="size-4 shrink-0" stroke-linecap="butt" stroke-linejoin="miter" /></span>
          <span>{{ $company->full_address }}@if($distance !== null)<span class="text-zinc-400"> · {{ __('portal.layout.card.distance', ['km' => number_format($distance, 0, ',', '.')]) }}</span>@endif</span>
        </p>
      @endif
      @if($company->categories->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
          @foreach($company->categories->take(4) as $category)
            <span class="pill">{{ $category->name }}</span>
          @endforeach
        </div>
      @endif
      @if($status)
        <div class="mt-3">
          <p class="text-sm flex items-center gap-2">
            <span class="size-2 rounded-full {{ $status->open ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
            <span class="{{ $status->open ? 'text-emerald-700' : 'text-zinc-500' }}">{{ $status->label }}</span>
          </p>
        </div>
      @endif
    </div>
  </div>
  <div class="flex lg:flex-col gap-2 lg:w-44 shrink-0">
    <x-phone-link :number="$company->tel" class="btn-primary flex-1" fallback-class="flex-1 self-center text-sm text-zinc-700 lg:text-center"><x-sun.icon name="phone" class="icon" />{{ __('portal.layout.card.call') }}</x-phone-link>
    <a href="{{ $company->portal_url }}" class="btn-secondary flex-1">{{ __('portal.layout.card.profile_short') }}</a>
  </div>
</article>
