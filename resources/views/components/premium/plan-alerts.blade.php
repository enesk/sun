{{--
    Hinweise oben im Betriebsbereich (#17), eingebunden in layouts.panel:
    - SUN-PREM-004: Zahlung fehlgeschlagen, Grace Period laeuft noch n Tage
    - SUN-PREM-009: Anfragen-Kontingent des Monats erschoepft
    Texte: lang/de/premium.php (alerts.*).
--}}
@props(['company' => null])
@php
    $entitlements = app(\App\Services\Premium\CompanyEntitlementService::class);
    $graceDays = null;
    $quotaExhausted = false;

    if ($company instanceof \App\Models\Portal\Company) {
        if ($entitlements->isInGracePeriod($company) && $company->plan_grace_until !== null) {
            $graceDays = (int) max(0, floor(now()->diffInDays($company->plan_grace_until, false)));
        }

        // rescue(): ohne tenants:migrate fehlt lead_quota_usages, der Hinweis soll dann nicht die Seite kippen
        if ($entitlements->can($company, \App\Enums\PremiumFeature::LeadQuota)) {
            $quota = rescue(fn () => app(\App\Services\Premium\LeadQuotaService::class)->summary($company), null, false);
            $quotaExhausted = $quota !== null && $quota['quota'] !== null && $quota['quota'] > 0 && $quota['remaining'] === 0;
        }
    }
@endphp
@if($graceDays !== null)
  <div class="card mb-4 p-4 border-red-200 bg-red-50 flex flex-wrap items-center gap-3" role="alert">
    <x-sun.icon name="info" class="icon shrink-0 text-red-600" />
    <p class="flex-1 min-w-0 text-base text-red-800">{{ trans_choice('premium.alerts.grace', $graceDays, ['tage' => $graceDays]) }}</p>
    <a href="{{ route('portal.owner.plan') }}" class="btn-secondary text-sm min-h-9 px-3">{{ __('premium.alerts.grace_cta') }}</a>
  </div>
@endif
@if($quotaExhausted)
  <div class="card mb-4 p-4 border-amber-200 bg-amber-50 flex flex-wrap items-center gap-3" role="status">
    <x-sun.icon name="inbox" class="icon shrink-0 text-amber-700" />
    <p class="flex-1 min-w-0 text-base text-amber-900">{{ __('premium.alerts.quota_exhausted') }}</p>
    <a href="{{ route('portal.premium.pricing') }}" class="btn-secondary text-sm min-h-9 px-3">{{ __('premium.alerts.quota_exhausted_cta') }}</a>
  </div>
@endif
