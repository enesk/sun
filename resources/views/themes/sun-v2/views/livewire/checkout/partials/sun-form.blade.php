{{--
    Gemeinsames Checkout-Formular der drei Abo-Checkouts in sun-v2.
    Links die Bestellung, rechts Konto (nur Gaeste), Zahlungsart und Bestellen.

    Parameter:
    - $withPayment (bool)   Zahlungsart zeigen (nicht bei der Testphase ohne Zahlungsdaten)
    - $isTrialSkipped (bool) Testphase entfaellt (Umwandlung oder Testphase schon genutzt)
    - $canAddDiscount (bool) Gutscheinfeld zeigen
    Button-Wortlaut nach § 312j BGB: "Zahlungspflichtig bestellen", sobald Zahlungsdaten erfasst werden.
--}}
@php
    $isTrialSkipped = $isTrialSkipped ?? false;
    $withTrial = $plan->has_trial && ! $isTrialSkipped;
    $trialInterval = $withTrial ? $plan->trialInterval()->first() : null;
    $trialLabel = $trialInterval ? trans_choice('portal.owner.checkout.intervals.'.$trialInterval->slug, $plan->trial_interval_count, ['anzahl' => $plan->trial_interval_count]) : '';
    $billingLabel = trans_choice('portal.owner.checkout.billing.'.$plan->interval->slug, $plan->interval_count, ['anzahl' => $plan->interval_count]);
    $priceLabel = \Illuminate\Support\Number::currency($totals->amountDue / 100, $totals->currencyCode, 'de');
    $legal = strtr(e(__('portal.owner.checkout.legal', ['agb' => '[[agb]]', 'datenschutz' => '[[datenschutz]]'])), [
        '[[agb]]' => '<a href="'.e(route('terms-of-service')).'" target="_blank" class="underline hover:text-zinc-900">'.e(__('portal.owner.checkout.terms_link')).'</a>',
        '[[datenschutz]]' => '<a href="'.e(route('portal.datenschutz')).'" target="_blank" class="underline hover:text-zinc-900">'.e(__('portal.owner.checkout.privacy_link')).'</a>',
    ]);
@endphp
<form method="post" wire:submit="checkout" class="lg:grid lg:grid-cols-2 lg:gap-8 items-start">
  @csrf

  @include('livewire.checkout.partials.plan-details', ['canAddDiscount' => $canAddDiscount ?? true, 'isTrialSkipped' => $isTrialSkipped])

  <div class="mt-6 lg:mt-0 space-y-6">
    @guest
      @include('livewire.checkout.partials.login-or-register')
    @endguest

    @if($withPayment)
      @include('livewire.checkout.partials.payment')
    @endif

    <div class="card p-5 md:p-6">
      <p class="text-base text-zinc-700">
        @if(! $withPayment)
          {{ __('portal.owner.checkout.trial_local_note', ['dauer' => $trialLabel]) }}
        @elseif($withTrial)
          {{ __('portal.owner.checkout.renewal_trial', ['dauer' => $trialLabel, 'betrag' => $priceLabel, 'zahlweise' => $billingLabel]) }}
        @else
          {{ __('portal.owner.checkout.renewal', ['betrag' => $priceLabel, 'zahlweise' => $billingLabel]) }}
        @endif
      </p>
      <p class="mt-2 text-sm text-zinc-500">{!! $legal !!}</p>

      <button type="submit" class="btn-primary mt-5 w-full" wire:loading.attr="disabled" @disabled(! $this->isCheckoutButtonEnabled())>
        <span wire:loading.remove wire:target="checkout">{{ $withPayment ? __('portal.owner.checkout.submit_paid') : __('portal.owner.checkout.submit_trial') }}</span>
        <span wire:loading wire:target="checkout">{{ __('portal.owner.checkout.processing') }}</span>
      </button>

      @if($withPayment)
        <p class="mt-3 flex items-center justify-center gap-2 text-sm text-zinc-500"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.checkout.payment.trust') }}</p>
      @endif
    </div>
  </div>
</form>
