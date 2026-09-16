{{--
    Bestelluebersicht im Checkout, Theme sun-v2: Plan, Abrechnung, Testphase, enthaltene
    Leistungen (Produkt-Features aus der Datenbank) und die Summen (Livewire subscription-totals).
    Parameter: $canAddDiscount, $isTrialSkipped, $isTenantPickerEnabled (wie im Default-Partial).
--}}
@php
    $canAddDiscount = $canAddDiscount ?? true;
    $isTrialSkipped = $isTrialSkipped ?? false;
    $isTenantPickerEnabled = $isTenantPickerEnabled ?? true;
    $trialInterval = ! $isTrialSkipped && $plan->has_trial ? $plan->trialInterval()->first() : null;
@endphp
<section class="card p-5 md:p-6 lg:sticky lg:top-24" aria-labelledby="checkout-summary">
  <h2 id="checkout-summary" class="text-sm text-zinc-500">{{ __('portal.owner.checkout.summary.title') }}</h2>

  <div class="mt-3 flex items-start gap-3">
    <span class="size-11 shrink-0 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center"><x-sun.icon name="sparkles" class="size-5" /></span>
    <div class="min-w-0">
      <p class="text-lg font-semibold text-zinc-900">{{ $plan->product->name }}</p>
      <p class="text-sm text-zinc-500">{{ __('portal.owner.checkout.summary.billing', ['zahlweise' => trans_choice('portal.owner.checkout.billing.'.$plan->interval->slug, $plan->interval_count, ['anzahl' => $plan->interval_count])]) }}</p>
      @if($trialInterval)
        <p class="pill-brand mt-2"><x-sun.icon name="check" class="size-4 shrink-0" />{{ __('portal.owner.checkout.summary.trial_badge', ['dauer' => trans_choice('portal.owner.checkout.intervals.'.$trialInterval->slug, $plan->trial_interval_count, ['anzahl' => $plan->trial_interval_count])]) }}</p>
      @endif
    </div>
  </div>

  @inject('tenantCreationService', 'App\Services\TenantCreationService')
  @if (($isTenantPickerEnabled && ! tenant() && $tenantCreationService->findUserTenantsForNewSubscription(auth()->user())->count() > 0) || $plan->type === \App\Constants\PlanType::SEAT_BASED->value)
    <div class="mt-4 flex flex-wrap gap-4">
      @if ($isTenantPickerEnabled && ! tenant() && $tenantCreationService->findUserTenantsForNewSubscription(auth()->user())->count() > 0)
        <livewire:checkout.subscription-tenant-picker />
      @endif
      @if ($plan->type === \App\Constants\PlanType::SEAT_BASED->value)
        <livewire:checkout.subscription-seats :plan="$plan" />
      @endif
    </div>
  @endif

  @if(! empty($plan->product->features))
    <h3 class="mt-5 text-base font-semibold text-zinc-900">{{ __('portal.owner.checkout.summary.included') }}</h3>
    <ul class="mt-2 space-y-2 text-base text-zinc-700">
      @foreach($plan->product->features as $feature)
        <li class="flex gap-2"><x-sun.icon name="check" class="icon text-emerald-600 mt-0.5" /><span>{{ $feature['feature'] }}</span></li>
      @endforeach
    </ul>
  @endif

  <livewire:checkout.subscription-totals :totals="$totals" :plan="$plan" page="{{ request()->fullUrl() }}" can-add-discount="{{ $canAddDiscount }}" is-trail-skipped="{{ $isTrialSkipped }}" />
</section>
