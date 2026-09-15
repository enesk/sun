{{--
    Übernahme-Reiter der Seite "Ist das dein Betrieb?" (sun-v2).
    Logik: App\Livewire\Portal\ClaimForm (= ClaimModal ohne Dialog).

    Ablauf wie im Backend vorhanden: Schritt 1 Konto (registrieren/anmelden)
    und Übernahme-Antrag, Schritt 2 Nachweis per Dokument direkt hier
    (eingebettet: livewire portal.claim-verification), Schritt 3 Prüfung und
    Freigabe durch das Portal-Team.
--}}
@php
    $errorClass = 'mt-1 text-sm text-red-600';
@endphp
<div>
  @if($claimSuccess || $scenario === 'pending_claim')
    {{-- ===== SCHRITT 2/3: NACHWEIS DIREKT HOCHLADEN ===== --}}
    @if(auth()->check() && \App\Models\Portal\Company::where('user_id', auth()->id())->exists())
      {{-- ClaimVerification sperrt Konten, die schon einen Betrieb verwalten; dann prueft das Team ohne Upload --}}
      <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 2]) }}</p>
      <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.pending.heading') }}</h2>
      <p class="mt-2 text-zinc-700 leading-relaxed">{{ __('portal.claim.pending.text', ['firma' => $company->name]) }}</p>
      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="goToDashboard" class="btn-secondary flex-1">{{ __('portal.claim.pending.dashboard') }}</button>
      </div>
    @else
      <livewire:portal.claim-verification :company="$company" :key="'claim-verification-'.$company->id" />
    @endif

  @elseif($scenario === 'already_claimed')
    <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 1]) }}</p>
    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.confirm.heading') }}</h2>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>
    <p class="mt-4 text-zinc-700 leading-relaxed">{{ __('portal.claim.confirm.already_claimed') }}</p>

  @else
    {{-- ===== SCHRITT 1: BETRIEB BESTÄTIGEN ===== --}}
    <p class="text-sm text-zinc-500">{{ __('portal.claim.step', ['schritt' => 1]) }}</p>
    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.confirm.heading') }}</h2>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>
    <p class="mt-4 text-sm text-zinc-500">{{ __('portal.claim.confirm.not_yours') }} <a href="{{ route('portal.companies.create') }}" class="text-brand hover:underline">{{ __('portal.claim.confirm.create') }}</a></p>

    {{-- Honeypot --}}
    <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

    @if($scenario === 'guest' && $activeTab === 'register')
      <label for="cname" class="block mt-6 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.name_label') }}</label>
      <input id="cname" wire:model="name" class="input @error('name') border-red-500 @enderror" autocomplete="name" required>
      @error('name')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <label for="cemail" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.email_label') }}</label>
      <input id="cemail" wire:model="email" type="email" class="input @error('email') border-red-500 @enderror" autocomplete="email" required>
      @error('email')
        <p class="{{ $errorClass }}">{{ $message }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.claim.register.email_hint') }}</p>
      @enderror

      <label for="cpassword" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.password_label') }}</label>
      <input id="cpassword" wire:model="password" type="password" class="input @error('password') border-red-500 @enderror" autocomplete="new-password" required>
      @error('password')
        <p class="{{ $errorClass }}">{{ $message }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.signup.account.password_hint') }}</p>
      @enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="register" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">{{ __('portal.signup.next') }}</button>
      </div>
      <p class="mt-3 text-sm text-zinc-500">{!! strtr(e(__('portal.claim.register.note', ['datenschutz' => '[[datenschutz]]'])), [
        '[[datenschutz]]' => '<a href="'.e(route('portal.datenschutz')).'" class="underline">'.e(__('portal.claim.register.privacy_link')).'</a>',
      ]) !!}</p>
      <p class="mt-3 text-sm text-zinc-500 text-center">{{ __('portal.claim.register.has_account') }} <button type="button" wire:click="$set('activeTab', 'login')" class="text-brand font-medium hover:underline">{{ __('portal.signup.login') }}</button></p>

    @elseif($scenario === 'guest')
      <label for="lemail" class="block mt-6 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.email_label') }}</label>
      <input id="lemail" wire:model="loginEmail" type="email" class="input @error('loginEmail') border-red-500 @enderror" autocomplete="email" required>
      @error('loginEmail')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <label for="lpassword" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.signup.account.password_label') }}</label>
      <input id="lpassword" wire:model="loginPassword" type="password" class="input @error('loginPassword') border-red-500 @enderror" autocomplete="current-password" required>
      @error('loginPassword')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="login" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">{{ __('portal.claim.login.submit') }}</button>
      </div>
      <p class="mt-3 text-sm text-zinc-500 text-center">{{ __('portal.claim.login.no_account') }} <button type="button" wire:click="$set('activeTab', 'register')" class="text-brand font-medium hover:underline">{{ __('portal.claim.login.register') }}</button></p>

    @else
      {{-- Angemeldet: nur noch bestätigen --}}
      <p class="mt-6 text-zinc-700">{!! strtr(e(__('portal.claim.logged_in.as', ['name' => '[[name]]'])), [
        '[[name]]' => '<span class="font-medium text-zinc-900">'.e(auth()->user()?->name).'</span>',
      ]) !!}</p>
      @if($scenario === 'logged_in_has_company' && $existingCompany)
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.claim.logged_in.has_company', ['firma' => $existingCompany->name]) }}</p>
      @endif
      <label class="mt-5 flex items-start gap-3 cursor-pointer">
        <input type="checkbox" wire:model="confirmOwner" class="mt-1 size-5 rounded border-zinc-300">
        <span class="text-sm text-zinc-700">{{ __('portal.claim.logged_in.confirm_owner', ['firma' => $company->name]) }}</span>
      </label>
      @error('confirmOwner')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="{{ $scenario === 'logged_in_has_company' ? 'claimAdditional' : 'claim' }}" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">{{ __('portal.signup.next') }}</button>
      </div>
    @endif
  @endif
</div>
