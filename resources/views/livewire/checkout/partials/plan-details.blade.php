<div class="md:sticky md:top-20">
    @php
        $canAddDiscount = $canAddDiscount ?? true;
        $isTrialSkipped = $isTrialSkipped ?? false;
        $isTenantPickerEnabled = $isTenantPickerEnabled ?? true;
    @endphp

    <h2 class="checkout-card__title">Plandetails</h2>

    <div class="checkout-plan-card">

        {{-- Plan Header --}}
        <div class="checkout-plan-header">
            <div class="checkout-plan-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>
            </div>
            <div>
                <div class="checkout-plan-name">{{ $plan->product->name }}</div>
                @if ($plan->interval_count > 1)
                    <div class="checkout-plan-interval">{{ $plan->interval_count }} {{ mb_convert_case($plan->interval->name, MB_CASE_TITLE, 'UTF-8') }}</div>
                @else
                    <div class="checkout-plan-interval">{{ mb_convert_case($plan->interval->adverb, MB_CASE_TITLE, 'UTF-8') }}es Abo</div>
                @endif
                @if (!$isTrialSkipped && $plan->has_trial)
                    <span class="checkout-trial-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        {{ $plan->trial_interval_count }} {{ $plan->trialInterval()->firstOrFail()->name }} kostenlos testen
                    </span>
                @endif
            </div>
        </div>

        {{-- Tenant Picker / Seats --}}
        <div class="flex gap-4">
            @inject('tenantCreationService', 'App\Services\TenantCreationService')

            @if ($isTenantPickerEnabled && $tenantCreationService->findUserTenantsForNewSubscription(auth()->user())->count() > 0)
                <livewire:checkout.subscription-tenant-picker />
            @endif

            @if ($plan->type === \App\Constants\PlanType::SEAT_BASED->value)
                <livewire:checkout.subscription-seats :plan="$plan" />
            @endif
        </div>

        {{-- Features --}}
        <div style="font-size: 0.875rem; font-weight: 600; color: var(--portal-primary-dark, #1E3A5F); margin: 1.25rem 0 0.75rem;">
            Das ist enthalten:
        </div>
        <ul class="checkout-features">
            @if ($plan->product->features)
                @foreach($plan->product->features as $feature)
                    <li>
                        <span class="checkout-check-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        {{ $feature['feature'] }}
                    </li>
                @endforeach
            @endif
        </ul>

        {{-- Totals --}}
        <livewire:checkout.subscription-totals :totals="$totals" :plan="$plan" page="{{request()->fullUrl()}}" can-add-discount="{{$canAddDiscount}}" is-trail-skipped="{{$isTrialSkipped}}"/>

    </div>
</div>
