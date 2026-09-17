{{--
    Abo-Details und Kuendigung im Betriebsbereich, Komponente
    App\Livewire\Portal\Company\Dashboard\SubscriptionDetails.
    Ersetzt die Verwaltungsseiten fuer Inhaber. Texte: lang/de/premium.php (subscription.*, plan.*).
--}}
<div class="space-y-4 md:space-y-6">
  @if($message)
    <p class="card p-4 text-base text-emerald-700" role="status">{{ $message }}</p>
  @endif
  @if($error)
    <p class="card p-4 text-base text-red-600" role="alert">{{ $error }}</p>
  @endif

  @if($subscription === null)
    <section class="card p-5 md:p-6">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('premium.subscription.none_title') }}</h2>
      <p class="mt-2 text-base text-zinc-500">{{ __('premium.subscription.none_text') }}</p>
      <a href="{{ route('portal.owner.premium') }}#plan" class="btn-primary mt-4 w-full sm:w-auto">{{ __('premium.subscription.none_cta') }}</a>
    </section>
  @else
    {{-- Details --}}
    <section class="card p-5 md:p-6" aria-labelledby="abo-details">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="abo-details" class="text-2xl font-semibold text-zinc-900">{{ $subscription->plan->name ?? __('premium.subscription.title') }}</h2>
        <span class="{{ $subscription->is_canceled_at_end_of_cycle ? 'pill bg-red-50 text-red-600' : 'pill bg-emerald-50 text-emerald-700' }}">
          {{ $subscription->is_canceled_at_end_of_cycle ? __('premium.subscription.status_canceled') : __('premium.subscription.status_active') }}
        </span>
      </div>

      <dl class="mt-4 divide-y divide-zinc-200 text-base">
        <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
          <dt class="text-sm text-zinc-500">{{ __('premium.subscription.price') }}</dt>
          <dd class="text-zinc-900">
            {{ \Illuminate\Support\Number::currency($subscription->price / 100, in: $subscription->currency->code ?? config('premium.currency', 'EUR'), locale: 'de') }}
            @if($subscription->interval?->name) / {{ __("premium.subscription.intervals.{$subscription->interval->name}") }} @endif
            <span class="text-sm text-zinc-500">· {{ __('premium.subscription.net') }}</span>
          </dd>
        </div>
        @if($company->plan_started_at)
          <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
            <dt class="text-sm text-zinc-500">{{ __('premium.plan.since') }}</dt>
            <dd class="text-zinc-900">{{ $company->plan_started_at->format('d.m.Y') }}</dd>
          </div>
        @endif
        @if($termEndsAt)
          <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
            <dt class="text-sm text-zinc-500">{{ __('premium.subscription.term_ends') }}</dt>
            <dd class="text-zinc-900">{{ $termEndsAt->format('d.m.Y') }}</dd>
          </div>
        @endif
        @if($subscription->ends_at)
          <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
            <dt class="text-sm text-zinc-500">{{ $subscription->is_canceled_at_end_of_cycle ? __('premium.subscription.active_until') : __('premium.plan.renews') }}</dt>
            <dd class="text-zinc-900">{{ $subscription->ends_at->format('d.m.Y') }}</dd>
          </div>
        @endif
        @if($subscription->paymentProvider?->name)
          <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
            <dt class="text-sm text-zinc-500">{{ __('premium.subscription.provider') }}</dt>
            <dd class="text-zinc-900">{{ $subscription->paymentProvider->name }}</dd>
          </div>
        @endif
      </dl>

      @if($subscription->is_canceled_at_end_of_cycle)
        <p class="mt-4 text-base text-zinc-700">{{ __('premium.plan.canceled', ['datum' => $subscription->ends_at?->format('d.m.Y') ?? '']) }}</p>
      @endif

      <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('portal.owner.plan') }}" class="btn-secondary">{{ __('premium.subscription.to_plan') }}</a>
        @if($canDiscard)
          <button type="button" wire:click="discard" wire:loading.attr="disabled" class="btn-primary">{{ __('premium.subscription.discard') }}</button>
        @endif
      </div>
    </section>

    {{-- Kuendigung --}}
    <section class="card p-5 md:p-6" aria-labelledby="abo-kuendigen" id="kuendigen">
      <h2 id="abo-kuendigen" class="text-2xl font-semibold text-zinc-900">{{ __('premium.subscription.cancel_title') }}</h2>
      <p class="mt-1 max-w-prose text-base text-zinc-700">{{ __('premium.subscription.cancel_intro', ['laufzeit' => (int) config('premium.contract.term_months', 12), 'frist' => (int) config('premium.contract.notice_months', 3)]) }}</p>

      @if(! $canCancel)
        <p class="mt-3 text-base text-zinc-500">{{ __('premium.subscription.cancel_unavailable') }}</p>
      @elseif(! $confirming)
        <button type="button" wire:click="startCancel" class="btn-secondary mt-4 w-full border-red-200 text-red-600 hover:bg-red-50 sm:w-auto">{{ __('premium.plan.cancel') }}</button>
      @else
        <form wire:submit="cancel" class="mt-4 space-y-4">
          <div>
            <label for="cancel-reason" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('premium.subscription.reason') }}</label>
            <select id="cancel-reason" wire:model="reason" class="input">
              <option value="">{{ __('premium.subscription.reason_placeholder') }}</option>
              @foreach($reasons as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
          <div>
            <label for="cancel-note" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('premium.subscription.note') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
            <textarea id="cancel-note" rows="3" maxlength="1000" wire:model="note" class="input py-3 leading-relaxed" placeholder="{{ __('premium.subscription.note_placeholder') }}"></textarea>
            @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn-secondary border-red-200 text-red-600 hover:bg-red-50" wire:loading.attr="disabled" wire:target="cancel">
              <span wire:loading.remove wire:target="cancel">{{ __('premium.subscription.cancel_submit') }}</span>
              <span wire:loading wire:target="cancel">{{ __('premium.subscription.cancel_running') }}</span>
            </button>
            <button type="button" wire:click="abortCancel" class="btn-ghost">{{ __('premium.subscription.cancel_abort') }}</button>
          </div>
        </form>
      @endif
    </section>
  @endif
</div>
