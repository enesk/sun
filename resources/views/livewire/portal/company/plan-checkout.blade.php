{{--
    Plan-Buchung im Betriebsbereich (#5), Komponente App\Livewire\Portal\Company\PlanCheckout.
    Preise aus der Tenant-Konfiguration (TenantPremiumPricing); der Jahresrabatt wird
    als Freimonate gegenueber 12 x Monatspreis angezeigt.
--}}
<div class="rounded-2xl bg-brand-50 border-2 border-brand p-5 md:p-6">
  <fieldset class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-white border border-zinc-200">
    <legend class="sr-only">{{ __('Zahlweise') }}</legend>
    <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold text-zinc-700 hover:bg-zinc-50 has-checked:bg-brand has-checked:text-white">
      <input type="radio" wire:model.live="billing" value="monthly" class="sr-only">{{ __('Monatlich') }}
    </label>
    <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold text-zinc-700 hover:bg-zinc-50 has-checked:bg-brand has-checked:text-white">
      <input type="radio" wire:model.live="billing" value="yearly" class="sr-only">{{ __('Jährlich') }}
    </label>
  </fieldset>

  @if($message)
    <p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ $message }}</p>
  @endif
  @if($error)
    <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $error }}</p>
  @endif

  <div class="mt-4 space-y-4">
    @foreach($tiers as $tier => $plan)
      @php($slug = "{$tier}-{$billing}")
      <div class="rounded-xl bg-white border border-zinc-200 p-4">
        <p class="text-lg font-semibold text-zinc-900">{{ $plan['label'] }}</p>

        @if($billing === 'yearly' && $plan['yearly'] !== null)
          <p class="mt-1 text-3xl font-bold text-zinc-900">{{ money($plan['yearly'], $currency) }}<span class="text-base font-medium text-zinc-500"> {{ __('/ Jahr') }}</span></p>
          @if($plan['free_months'] > 0)
            <p class="mt-1 text-sm font-semibold text-brand-700">{{ trans_choice('{1} :count Monat gratis|[2,*] :count Monate gratis', $plan['free_months']) }}</p>
            @if($plan['monthly'] !== null)
              <p class="text-sm text-zinc-500">{{ __('statt :preis bei monatlicher Zahlung', ['preis' => money($plan['monthly'] * 12, $currency)]) }}</p>
            @endif
          @endif
        @elseif($billing === 'monthly' && $plan['monthly'] !== null)
          <p class="mt-1 text-3xl font-bold text-zinc-900">{{ money($plan['monthly'], $currency) }}<span class="text-base font-medium text-zinc-500"> {{ __('/ Monat') }}</span></p>
        @endif
        <p class="text-xs text-zinc-500">{{ __('inkl. MwSt.') }}</p>

        @if($currentSlug === $slug)
          <p class="btn-secondary mt-3 w-full pointer-events-none">{{ __('Aktuelles Paket') }}</p>
        @elseif($plan['available'])
          <button type="button" wire:click="checkout('{{ $tier }}')" wire:loading.attr="disabled" class="btn-primary mt-3 w-full">
            {{ $current ? __('Zu :plan wechseln', ['plan' => $plan['label']]) : __(':plan buchen', ['plan' => $plan['label']]) }}
          </button>
        @else
          <p class="mt-3 text-sm text-zinc-500">{{ __('Derzeit nicht buchbar.') }}</p>
        @endif
      </div>
    @endforeach
  </div>

  @if($current)
    <div class="mt-4 text-sm text-zinc-600">
      @if($current->is_canceled_at_end_of_cycle)
        <p>{{ __('Gekündigt zum :datum.', ['datum' => $current->ends_at?->format('d.m.Y')]) }}</p>
      @else
        <p>{{ __('Paketwechsel werden anteilig verrechnet. Eine Kündigung wirkt zum Ende der Laufzeit.') }}</p>
        <button type="button" wire:click="cancel" wire:confirm="{{ __('Abo wirklich zum Ende der Laufzeit kündigen? Ihre Inhalte bleiben erhalten.') }}" class="btn-ghost mt-2 text-zinc-600 hover:bg-zinc-100">{{ __('Abo kündigen') }}</button>
      @endif
    </div>
  @endif
</div>
