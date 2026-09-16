{{--
    "Trag deinen Betrieb ein" – zwei Schritte in einer Karte (Vorlage elektrikerportal-firma-eintragen (1).html).
    Logik: App\Livewire\Portal\CompanySignup. Validiert erst beim Weiterklicken, Hinweistext statt nur roter Rahmen.
    Alle Texte aus portal.signup.* (#11), Meldungen aus portal.errors.signup.*.
--}}
@php
    $hint = 'mt-1 text-sm';
    $guest = auth()->guest();
@endphp
<form class="card p-5 md:p-8" novalidate wire:submit="next"
      x-data x-on:signup-step.window="$el.scrollIntoView({ block: 'start', behavior: 'smooth' })">

  <!-- Fortschritt (nur fuer Gaeste: Angemeldete tragen nach Schritt 1 direkt ein) -->
  @if($guest)
  <ol class="flex items-center gap-3 text-sm" aria-label="{{ __('portal.signup.progress.label') }}">
    <li class="flex items-center gap-2" @if($step === 1) aria-current="step" @endif><span class="size-7 rounded-full bg-brand text-white font-semibold flex items-center justify-center shrink-0">1</span><span class="font-medium text-zinc-900">{{ __('portal.signup.progress.business') }}</span></li>
    <li class="flex-1 h-px bg-zinc-200" aria-hidden="true"></li>
    <li @class(['flex items-center gap-2', 'text-zinc-500' => $step < 2]) @if($step === 2) aria-current="step" @endif><span @class(['size-7 rounded-full font-semibold flex items-center justify-center shrink-0', 'bg-brand text-white' => $step === 2, 'bg-zinc-100' => $step < 2])>2</span><span @class(['font-medium text-zinc-900' => $step === 2])>{{ __('portal.signup.progress.account') }}</span></li>
  </ol>
  @endif

  {{-- Honeypot --}}
  <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

  @if($step === 1)
    <!-- ===== SCHRITT 1: BETRIEB ===== -->
    <div @class(['mt-6 pt-6 border-t border-zinc-200' => $guest])>
      @unless($guest)
        <p class="mb-5 text-zinc-700">{{ __('portal.signup.account.logged_in_as', ['name' => auth()->user()->name]) }}</p>
      @endunless
      <label for="firma" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.name_label') }}</label>
      <input id="firma" wire:model="firma" class="input @error('firma') border-red-500 @enderror" autocomplete="organization" required>
      @error('firma')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <label for="strasse" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.street_label') }}</label>
      <input id="strasse" wire:model="strasse" class="input @error('strasse') border-red-500 @enderror" autocomplete="street-address" required>
      @error('strasse')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <div class="mt-4 grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
        <div>
          <label for="plz" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.zip_label') }}</label>
          <input id="plz" wire:model="plz" class="input @error('plz') border-red-500 @enderror" inputmode="numeric" maxlength="5" autocomplete="postal-code" required>
        </div>
        <div>
          <label for="ort" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.city_label') }}</label>
          <input id="ort" wire:model="ort" class="input @error('ort') border-red-500 @enderror" autocomplete="address-level2" required>
        </div>
      </div>
      @error('plz')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror
      @error('ort')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <label for="tel" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.phone_label') }}</label>
      <input id="tel" type="tel" wire:model="tel" class="input @error('tel') border-red-500 @enderror" inputmode="tel" autocomplete="tel" required aria-describedby="telHint">
      <p id="telHint" @class([$hint, 'text-red-600' => $errors->has('tel'), 'text-zinc-500' => ! $errors->has('tel')])>{{ $errors->first('tel') ?: __('portal.signup.business.phone_hint') }}</p>

      <label for="web" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.business.website_label') }} <span class="text-zinc-500 font-normal">{{ __('portal.signup.business.optional') }}</span></label>
      <input id="web" type="url" wire:model="web" class="input @error('web') border-red-500 @enderror" placeholder="{{ __('portal.signup.business.website_placeholder') }}" autocomplete="url">
      @error('web')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror
    </div>
  @else
    <!-- ===== SCHRITT 2: KONTO ===== -->
    <div class="mt-6 pt-6 border-t border-zinc-200">
      <div class="flex items-start gap-3 p-4 rounded-2xl bg-zinc-50 border border-zinc-200">
        <span class="size-10 rounded-2xl bg-brand-50 text-brand-700 font-bold text-sm flex items-center justify-center shrink-0" aria-hidden="true">{{ $this->initials() }}</span>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-zinc-900 leading-snug">{{ $firma }}</p>
          <p class="text-sm text-zinc-500">{{ trim($plz.' '.$ort) }}</p>
        </div>
        <button type="button" wire:click="back" class="text-sm text-brand font-medium hover:underline shrink-0">{{ __('portal.signup.account.change') }}</button>
      </div>

      @if($guest)
        <label for="name" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.name_label') }}</label>
        <input id="name" wire:model="name" class="input @error('name') border-red-500 @enderror" autocomplete="name" required>
        @error('name')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

        <label for="email" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.email_label') }}</label>
        <input id="email" type="email" wire:model="email" class="input @error('email') border-red-500 @enderror" autocomplete="email" inputmode="email" required aria-describedby="emailHint">
        <p id="emailHint" @class([$hint, 'text-red-600' => $errors->has('email'), 'text-zinc-500' => ! $errors->has('email')])>
          {{ $errors->first('email') ?: __('portal.signup.account.email_hint') }}
          @if($errors->has('email') && $errors->first('email') === __('portal.errors.signup.email_taken'))<a href="{{ route('login') }}" class="underline">{{ __('portal.signup.login') }}</a>@endif
        </p>

        <label for="pass" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.password_label') }}</label>
        <div class="relative" x-data="{ show: false }">
          <input id="pass" :type="show ? 'text' : 'password'" type="password" wire:model="password" class="input pr-12 @error('password') border-red-500 @enderror" autocomplete="new-password" required aria-describedby="passHint">
          <button type="button" x-on:click="show = !show" :aria-label="show ? @js(__('portal.signup.account.password_hide')) : @js(__('portal.signup.account.password_show'))" class="absolute right-1 top-1/2 -translate-y-1/2 size-10 rounded-lg text-zinc-400 hover:text-zinc-700 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand" aria-label="{{ __('portal.signup.account.password_show') }}"><x-sun.icon name="eye" class="size-5" /></button>
        </div>
        <p id="passHint" @class([$hint, 'text-red-600' => $errors->has('password'), 'text-zinc-500' => ! $errors->has('password')])>{{ $errors->first('password') ?: __('portal.signup.account.password_hint') }}</p>

        <label @class(['mt-5 flex items-start gap-3 cursor-pointer', 'text-red-600' => $errors->has('agb')])>
          <input type="checkbox" wire:model="agb" class="mt-1 size-5 rounded border-zinc-300 text-brand focus:ring-brand" required>
          {{-- Text escapen, erst danach die Links einsetzen: der Satz ist per tenant_texts ueberschreibbar (#17) --}}
          <span @class(['text-sm', 'text-zinc-700' => ! $errors->has('agb')])>{!! strtr(e(__('portal.signup.account.terms', ['agb' => '[[agb]]', 'datenschutz' => '[[datenschutz]]'])), [
            '[[agb]]' => '<a href="'.e(route('terms-of-service')).'" class="underline">'.e(__('portal.signup.account.terms_link')).'</a>',
            '[[datenschutz]]' => '<a href="'.e(route('portal.datenschutz')).'" class="underline">'.e(__('portal.signup.account.privacy_link')).'</a>',
          ]) !!}</span>
        </label>
        @error('agb')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror
      @else
        <p class="mt-5 text-zinc-700">{{ __('portal.signup.account.logged_in_as', ['name' => auth()->user()->name]) }}</p>
      @endif
    </div>
  @endif

  <button type="submit" class="mt-6 btn-primary w-full" wire:loading.attr="disabled" wire:target="next">{{ __($guest ? ($step === 2 ? 'portal.signup.submit_guest' : 'portal.signup.next') : 'portal.signup.submit_user') }}</button>
  <p class="mt-3 text-sm text-zinc-500 text-center">{{ __($step === 2 ? 'portal.signup.note_step_two' : 'portal.signup.note_step_one') }}</p>
  @if($guest)
    <p class="mt-4 pt-4 border-t border-zinc-200 text-sm text-zinc-500 text-center">{{ __('portal.signup.has_account') }} <a href="{{ route('login') }}" class="text-brand font-medium hover:underline">{{ __('portal.signup.login') }}</a></p>
  @endif

  @if($done)
    <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="doneTitle" x-data x-init="document.body.style.overflow = 'hidden'">
      <div class="absolute inset-0 bg-zinc-900/40"></div>
      <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-md p-6 text-center flex flex-col items-center gap-3 shadow-lg" style="padding-bottom:max(1.5rem,env(safe-area-inset-bottom))">
          <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
          @if(auth()->user()?->hasVerifiedEmail())
            <h2 id="doneTitle" class="text-lg font-semibold text-zinc-900">{{ __('portal.signup.done.heading') }}</h2>
            <p class="text-zinc-500">{{ __('portal.signup.done.text') }}</p>
            <a href="{{ route('portal.owner.dashboard') }}" class="btn-primary w-full mt-2">{{ __('portal.signup.done.complete_profile') }}</a>
          @else
            <h2 id="doneTitle" class="text-lg font-semibold text-zinc-900">{{ __('portal.signup.done.verify_heading') }}</h2>
            <p class="text-zinc-500">{{ __('portal.signup.done.verify_text') }}</p>
            <a href="{{ route('portal.owner.dashboard') }}" class="btn-primary w-full mt-2">{{ __('portal.signup.done.complete_profile') }}</a>
            <button type="button" wire:click="resendVerification" wire:loading.attr="disabled" class="btn-ghost w-full" @disabled($resent)>{{ __($resent ? 'portal.signup.done.resent' : 'portal.signup.done.resend') }}</button>
          @endif
        </div>
      </div>
    </div>
  @endif
</form>
