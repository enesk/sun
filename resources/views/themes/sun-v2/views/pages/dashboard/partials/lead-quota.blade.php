{{--
    Kontingent exklusiver Anfragen im laufenden Monat (#10).
    Daten: LeadQuotaService::summary() ueber OwnerDashboardController::index().
    Ohne Kontingent (Basis) bleibt die Karte leer; dort wirbt die Premium-Karte.
--}}
@php
    $quota = $leadQuota['quota'];
    $used = $leadQuota['used'];
    $percent = $quota ? min(100, (int) round($used / $quota * 100)) : 0;
    $exhausted = $quota !== null && $leadQuota['remaining'] === 0;
    $canUpgrade = ! app(\App\Services\Premium\CompanyEntitlementService::class)
        ->effectiveTier($company)
        ->isAtLeast(\App\Enums\PlanTier::Premium);
@endphp

@if($quota !== 0)
  <section class="card p-5 md:p-6" aria-labelledby="exklusive-anfragen">
    <h2 id="exklusive-anfragen" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.overview.lead_quota.title') }}</h2>

    @if($quota === null)
      <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.overview.lead_quota.unlimited', ['genutzt' => $used]) }}</p>
    @else
      <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.overview.lead_quota.used', ['genutzt' => min($used, $quota), 'kontingent' => $quota]) }}</p>
      <div class="mt-3 h-2 rounded-full bg-zinc-100 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $quota }}" aria-valuenow="{{ min($used, $quota) }}" aria-label="{{ __('portal.owner.overview.lead_quota.title') }}">
        <div @class(['h-full rounded-full', 'bg-brand' => ! $exhausted, 'bg-amber-500' => $exhausted]) style="width: {{ $percent }}%"></div>
      </div>
    @endif

    @if($exhausted)
      <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.overview.lead_quota.exhausted') }}</p>
      @if($canUpgrade)
        <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-3 w-full">{{ __('portal.owner.overview.lead_quota.upsell') }}</a>
      @endif
    @endif
  </section>
@endif
