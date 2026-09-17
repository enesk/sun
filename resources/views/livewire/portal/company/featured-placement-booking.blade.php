{{--
    Top-Platzierung im Betriebsbereich (#6), Komponente App\Livewire\Portal\Company\FeaturedPlacementBooking.
    Je Branche des Betriebs in seiner Stadt ein eigenes Add-on; Preis aus der Tenant-Konfiguration
    (featured_monthly). Den Slot vergibt der Webhook nach erfolgreicher Zahlung.
--}}
<div class="rounded-2xl bg-white border border-zinc-200 p-5 md:p-6">
  <p class="text-lg font-semibold text-zinc-900">{{ __('Top-Platzierung') }}</p>
  <p class="mt-1 text-sm text-zinc-600">
    {{ __('Ihr Betrieb steht in Ihrer Stadt und Branche ganz oben. Pro Stadt und Branche gibt es höchstens :anzahl Plätze.', ['anzahl' => $maxSlots]) }}
  </p>
  @if($priceCents !== null)
    <p class="mt-2 text-2xl font-bold text-zinc-900">{{ money($priceCents, $currency) }}<span class="text-base font-medium text-zinc-500"> {{ __('/ Monat je Platzierung') }}</span></p>
    <p class="text-xs text-zinc-500">{{ __('premium.price.vat') }}</p>
  @endif

  @if($message)
    <p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ $message }}</p>
  @endif
  @if($error)
    <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $error }}</p>
  @endif

  @if($active->isNotEmpty())
    <ul class="mt-4 space-y-2">
      @foreach($active as $row)
        <li class="rounded-xl bg-brand-50 border border-brand p-3 text-sm text-zinc-700">
          <p class="font-semibold text-zinc-900">
            {{ __(':branche in :stadt – Platz :slot', ['branche' => $row['placement']->category?->name, 'stadt' => $row['placement']->city?->name, 'slot' => $row['placement']->slot]) }}
          </p>
          @if($row['subscription']?->is_canceled_at_end_of_cycle)
            <p>{{ __('Gekündigt zum :datum.', ['datum' => $row['subscription']->ends_at?->format('d.m.Y')]) }}</p>
          @elseif($row['subscription'])
            <button type="button" wire:click="cancel({{ $row['placement']->id }})" wire:confirm="{{ __('Top-Platzierung wirklich zum Ende der Laufzeit kündigen?') }}" class="btn-ghost mt-1 text-zinc-600 hover:bg-zinc-100">{{ __('Top-Platzierung kündigen') }}</button>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  @if(! $available)
    <p class="mt-4 text-sm text-zinc-500">{{ __('Derzeit nicht buchbar.') }}</p>
  @elseif(! $entitled)
    <p class="mt-4 text-sm text-zinc-600">{{ __('Die Top-Platzierung ist mit dem Premium-Paket buchbar.') }}</p>
  @elseif($company->city === null)
    <p class="mt-4 text-sm text-zinc-600">{{ __('Bitte hinterlegen Sie zuerst die Stadt Ihres Betriebs.') }}</p>
  @elseif($options->isEmpty() && $active->isEmpty())
    <p class="mt-4 text-sm text-zinc-600">{{ __('Bitte ordnen Sie Ihrem Betrieb zuerst eine Branche zu.') }}</p>
  @else
    <ul class="mt-4 space-y-2">
      @foreach($options as $option)
        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 p-3">
          <div class="text-sm">
            <p class="font-semibold text-zinc-900">{{ __(':branche in :stadt', ['branche' => $option['category']->name, 'stadt' => $company->city->name]) }}</p>
            <p class="text-zinc-500">{{ trans_choice('{0} Alle Plätze vergeben|{1} :count Platz frei|[2,*] :count Plätze frei', $option['free']) }}</p>
          </div>
          @if($option['free'] > 0)
            <button type="button" wire:click="book({{ $option['category']->id }})" wire:loading.attr="disabled" class="btn-primary">{{ __('Buchen') }}</button>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</div>
