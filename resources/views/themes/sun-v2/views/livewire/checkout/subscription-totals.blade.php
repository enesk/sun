{{--
    Summen und Gutschein im Checkout, Theme sun-v2. Komponente: App\Livewire\Checkout\SubscriptionTotals.
    Die Komponente flasht englische Meldungen; angezeigt werden deshalb eigene Texte (owner.checkout.totals.*).
--}}
<div class="mt-5 border-t border-zinc-200 pt-4">
  @php
    $isDiscountCodeAdded = ! empty($addedCode);
    // Betraege in Cent, deutsch formatiert (@money nutzt die App-Locale en)
    $euro = fn ($cents) => \Illuminate\Support\Number::currency(((int) $cents) / 100, $currencyCode, 'de');
  @endphp

  @if ($canAddDiscount)
    <div x-data="{ open: @js($isDiscountCodeAdded || session()->has('error')) }">
      <button type="button" class="btn-ghost -ml-3 px-3 text-brand-700" x-on:click="open = true" x-show="!open">{{ __('portal.owner.checkout.totals.coupon_toggle') }}</button>

      <div x-show="open" x-cloak class="mb-4">
        @if ($isDiscountCodeAdded)
          <div class="flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-base text-emerald-700" role="status"><x-sun.icon name="check" class="icon" />{{ __('portal.owner.checkout.totals.coupon_applied', ['code' => $addedCode]) }}</p>
            <button type="button" wire:click="remove" class="btn-ghost px-3 text-zinc-600 hover:bg-zinc-100">{{ __('portal.owner.checkout.totals.coupon_remove') }}</button>
          </div>
        @else
          <label for="coupon" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.checkout.totals.coupon_label') }}</label>
          <div class="flex gap-2">
            <input id="coupon" type="text" wire:model="code" wire:keydown.enter.prevent="add" @class(['input flex-1 min-w-0', 'border-red-500' => session()->has('error')]) autocomplete="off" @if(session()->has('error')) aria-invalid="true" aria-describedby="coupon-error" @endif>
            <button type="button" wire:click="add" class="btn-secondary shrink-0">{{ __('portal.owner.checkout.totals.coupon_apply') }}</button>
          </div>
          @if (session()->has('error'))
            <p id="coupon-error" class="mt-1 text-sm text-red-600" role="alert">{{ __('portal.owner.checkout.totals.coupon_invalid') }}</p>
          @endif
        @endif
      </div>
    </div>
  @endif

  <dl class="space-y-2 text-base">
    @if ($subtotal > 0)
      <div class="flex justify-between gap-4"><dt class="text-zinc-700">{{ __('portal.owner.checkout.totals.subtotal') }}</dt><dd class="text-zinc-900 tabular-nums">{{ $euro($subtotal) }}</dd></div>
    @endif

    @if ($planPriceType === \App\Constants\PlanPriceType::USAGE_BASED_PER_UNIT->value)
      <div class="flex justify-between gap-4"><dt class="text-zinc-700">{{ __('portal.owner.checkout.totals.per_unit', ['einheit' => $unitMeterName]) }}</dt><dd class="text-zinc-900 tabular-nums">{{ $euro($pricePerUnit) }}</dd></div>
    @elseif (in_array($planPriceType, [\App\Constants\PlanPriceType::USAGE_BASED_TIERED_VOLUME->value, \App\Constants\PlanPriceType::USAGE_BASED_TIERED_GRADUATED->value], true))
      <div>
        <dt class="text-zinc-700">{{ __('portal.owner.checkout.totals.tiers') }}</dt>
        @php $start = 0; @endphp
        @foreach ($tiers as $tier)
          <dd class="flex justify-between gap-4 text-sm text-zinc-500">
            <span>{{ __('portal.owner.checkout.totals.tier_row', ['von' => $start, 'bis' => $tier[\App\Constants\PlanPriceTierConstants::UNTIL_UNIT], 'einheit' => $unitMeterName]) }}</span>
            <span class="tabular-nums">{{ $euro($tier[\App\Constants\PlanPriceTierConstants::PER_UNIT]) }}@if ($tier[\App\Constants\PlanPriceTierConstants::FLAT_FEE] > 0) + {{ $euro($tier[\App\Constants\PlanPriceTierConstants::FLAT_FEE]) }}@endif</span>
          </dd>
          @php $start = intval($tier[\App\Constants\PlanPriceTierConstants::UNTIL_UNIT]) + 1; @endphp
        @endforeach
      </div>
    @endif

    @if ($discountAmount > 0)
      <div class="flex justify-between gap-4"><dt class="text-zinc-700">{{ __('portal.owner.checkout.totals.discount') }}</dt><dd class="text-emerald-700 tabular-nums">−{{ $euro($discountAmount) }}</dd></div>
      <div class="flex justify-between gap-4"><dt class="text-zinc-700">{{ __('portal.owner.checkout.totals.total') }}</dt><dd class="text-zinc-900 tabular-nums">{{ $euro($amountDue) }}</dd></div>
    @endif

    <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-lg font-semibold text-zinc-900">
      <dt>{{ __('portal.owner.checkout.totals.due_now') }}</dt>
      <dd class="tabular-nums">
        @if ($planHasTrial && ! $isTrailSkipped)
          {{ $euro(0) }}
        @else
          {{ $euro($amountDue) }}
        @endif
      </dd>
    </div>
  </dl>

  <p class="mt-1 text-sm text-zinc-500">
    @if ($planHasTrial && ! $isTrailSkipped){{ __('portal.owner.checkout.totals.trial_hint') }} @endif{{ __('portal.owner.checkout.totals.tax') }}
  </p>
</div>
