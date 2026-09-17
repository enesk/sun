{{--
    Mein Plan (#17), Komponente App\Livewire\Portal\Company\Dashboard\Plan.
    Planwechsel ueber /firmenprofil/premium (PlanCheckout), Rechnungen und
    Zahlungsdaten ueber die SaasyKit-Abo-Verwaltung. Texte: lang/de/premium.php (plan.*).
--}}
@php
    $isFree = $tier === \App\Enums\PlanTier::Free;
    $changeUrl = route('portal.owner.premium').'#plan';
@endphp
<div class="space-y-4 md:space-y-6">
  @if($message)
    <p class="card p-4 text-base text-emerald-700" role="status">{{ $message }}</p>
  @endif
  @if($error)
    <p class="card p-4 text-base text-red-600" role="alert">{{ $error }}</p>
  @endif

  {{-- Aktuelles Paket --}}
  <section class="card p-5 md:p-6 {{ $isFree ? '' : 'border-2 border-brand' }}" aria-labelledby="plan-current">
    <p class="text-sm text-zinc-500">{{ __('premium.plan.current') }}</p>
    <h2 id="plan-current" class="mt-1 text-2xl font-semibold text-zinc-900 flex items-center gap-2">
      @unless($isFree)<x-sun.icon name="sparkles" class="size-5 shrink-0 text-brand" />@endunless
      {{ __("premium.tiers.{$tier->value}") }}
    </h2>

    @if($isFree)
      <p class="mt-2 text-base text-zinc-700">{{ __('premium.plan.free_text') }}</p>
    @else
      <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-base">
        @if($company->plan_started_at)
          <div><dt class="text-sm text-zinc-500">{{ __('premium.plan.since') }}</dt><dd class="font-medium text-zinc-900">{{ $company->plan_started_at->format('d.m.Y') }}</dd></div>
        @endif
        <div>
          <dt class="text-sm text-zinc-500">{{ __('premium.plan.until') }}</dt>
          <dd class="font-medium text-zinc-900">{{ $subscription?->ends_at?->format('d.m.Y') ?? $company->plan_ends_at?->format('d.m.Y') ?? __('premium.plan.unlimited') }}</dd>
        </div>
        @if($nextCharge)
          <div><dt class="text-sm text-zinc-500">{{ __('premium.plan.renews') }}</dt><dd class="font-medium text-zinc-900">{{ $nextCharge['date']->format('d.m.Y') }}</dd></div>
          <div><dt class="text-sm text-zinc-500">{{ __('premium.plan.amount') }}</dt><dd class="font-medium text-zinc-900">{{ money($nextCharge['amount'], $nextCharge['currency'])->format('de_DE') }}</dd></div>
        @endif
      </dl>
      @if($subscription?->is_canceled_at_end_of_cycle)
        <p class="mt-4 text-base text-red-600">{{ __('premium.plan.canceled', ['datum' => $subscription->ends_at?->format('d.m.Y') ?? '']) }}</p>
      @endif
    @endif

    <div class="mt-5 flex flex-wrap gap-2">
      @if($tier !== \App\Enums\PlanTier::Premium)
        <a href="{{ $changeUrl }}" class="btn-primary">{{ __('premium.plan.upgrade') }}</a>
      @endif
      @if($tier === \App\Enums\PlanTier::Premium && $subscription)
        <a href="{{ $changeUrl }}" class="btn-secondary">{{ __('premium.plan.downgrade') }}</a>
      @endif
      @if($subscription)
        <a href="{{ route('portal.owner.subscription') }}" class="btn-secondary">{{ __('premium.subscription.title') }}</a>
      @endif
      <a href="{{ route('portal.premium.pricing') }}" class="btn-ghost">{{ __('premium.plan.compare') }}</a>
      @if($canCancel)
        <button type="button" wire:click="cancel" wire:confirm="{{ __('premium.plan.cancel_confirm') }}" wire:loading.attr="disabled" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ __('premium.plan.cancel') }}</button>
      @endif
    </div>
  </section>

  {{-- Top-Platzierungen --}}
  <section class="card p-5 md:p-6" aria-labelledby="plan-placements">
    <h2 id="plan-placements" class="text-2xl font-semibold text-zinc-900">{{ __('premium.plan.placements_title') }}</h2>
    @if($placements->isEmpty())
      <p class="mt-2 text-base text-zinc-500">{{ __('premium.plan.placements_empty') }}</p>
    @else
      <ul class="mt-4 divide-y divide-zinc-200">
        @foreach($placements as $placement)
          <li class="py-3 flex flex-wrap items-center justify-between gap-2">
            <span class="font-medium text-zinc-900">{{ $placement->category?->name }} · {{ $placement->city?->name }}</span>
            <span class="text-sm text-zinc-500">
              {{ $placement->ends_at
                  ? __('premium.plan.placement_period', ['von' => $placement->starts_at?->format('d.m.Y'), 'bis' => $placement->ends_at->format('d.m.Y')])
                  : __('premium.plan.placement_open', ['von' => $placement->starts_at?->format('d.m.Y')]) }}
            </span>
          </li>
        @endforeach
      </ul>
    @endif
    <div class="mt-4">
      @if($featuredEntitled)
        <a href="{{ $changeUrl }}" class="btn-secondary">{{ __('premium.plan.placements_book') }}</a>
      @else
        <x-premium.locked :feature="\App\Enums\PremiumFeature::FeaturedPlacement" />
      @endif
    </div>
  </section>

  {{-- Enthaltene und gesperrte Funktionen --}}
  <section class="card p-5 md:p-6" aria-labelledby="plan-features">
    <h2 id="plan-features" class="text-2xl font-semibold text-zinc-900">{{ __('premium.plan.features_title') }}</h2>
    <ul class="mt-4 grid sm:grid-cols-2 gap-x-6 gap-y-3">
      @foreach($features as $row)
        <li>
          <p class="flex gap-2 text-base {{ $row['unlocked'] ? 'text-zinc-900' : 'text-zinc-500' }}">
            <x-sun.icon :name="$row['unlocked'] ? 'check' : 'minus'" class="icon shrink-0 mt-0.5 {{ $row['unlocked'] ? 'text-emerald-600' : 'text-zinc-300' }}" />
            {{ __("premium.features.{$row['feature']->value}") }}
          </p>
          @unless($row['unlocked'])
            <x-premium.locked :feature="$row['feature']" class="ml-8 mt-0.5" />
          @endunless
        </li>
      @endforeach
    </ul>
  </section>
</div>
