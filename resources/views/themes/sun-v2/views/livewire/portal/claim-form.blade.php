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
      <p class="text-sm text-zinc-500">Schritt 2 von 3</p>
      <h2 class="text-lg font-semibold text-zinc-900">Übernahme beantragt</h2>
      <p class="mt-2 text-zinc-700 leading-relaxed">Du verwaltest bereits einen Betrieb. Wir prüfen die zusätzliche Übernahme von {{ $company->name }} und melden uns per E-Mail.</p>
      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="goToDashboard" class="btn-secondary flex-1">Zur Verwaltung</button>
      </div>
    @else
      <livewire:portal.claim-verification :company="$company" :key="'claim-verification-'.$company->id" />
    @endif

  @elseif($scenario === 'already_claimed')
    <p class="text-sm text-zinc-500">Schritt 1 von 3</p>
    <h2 class="text-lg font-semibold text-zinc-900">Betrieb bestätigen</h2>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>
    <p class="mt-4 text-zinc-700 leading-relaxed">Dieser Eintrag wurde bereits von jemand anderem übernommen. Gehört er dir, schreib uns über „Fehler melden“ – wir klären das.</p>

  @else
    {{-- ===== SCHRITT 1: BETRIEB BESTÄTIGEN ===== --}}
    <p class="text-sm text-zinc-500">Schritt 1 von 3</p>
    <h2 class="text-lg font-semibold text-zinc-900">Betrieb bestätigen</h2>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>
    <p class="mt-4 text-sm text-zinc-500">Nicht dein Betrieb? <a href="{{ route('portal.companies.create') }}" class="text-brand hover:underline">Neuen Eintrag anlegen</a></p>

    {{-- Honeypot --}}
    <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

    @if($scenario === 'guest' && $activeTab === 'register')
      <label for="cname" class="block mt-6 text-sm font-medium text-zinc-700 mb-1">Dein Name</label>
      <input id="cname" wire:model="name" class="input @error('name') border-red-500 @enderror" autocomplete="name" required>
      @error('name')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <label for="cemail" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">E-Mail</label>
      <input id="cemail" wire:model="email" type="email" class="input @error('email') border-red-500 @enderror" autocomplete="email" required>
      @error('email')
        <p class="{{ $errorClass }}">{{ $message }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">Damit meldest du dich später an. Am besten eine Adresse mit Firmen-Domain.</p>
      @enderror

      <label for="cpassword" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Passwort</label>
      <input id="cpassword" wire:model="password" type="password" class="input @error('password') border-red-500 @enderror" autocomplete="new-password" required>
      @error('password')
        <p class="{{ $errorClass }}">{{ $message }}</p>
      @else
        <p class="mt-1 text-sm text-zinc-500">Mindestens 8 Zeichen.</p>
      @enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="register" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">Weiter</button>
      </div>
      <p class="mt-3 text-sm text-zinc-500">Kostenlos, ohne Kreditkarte. Hinweise zum <a href="{{ route('portal.datenschutz') }}" class="underline">Datenschutz</a>.</p>
      <p class="mt-3 text-sm text-zinc-500 text-center">Schon registriert? <button type="button" wire:click="$set('activeTab', 'login')" class="text-brand font-medium hover:underline">Anmelden</button></p>

    @elseif($scenario === 'guest')
      <label for="lemail" class="block mt-6 text-sm font-medium text-zinc-700 mb-1">E-Mail</label>
      <input id="lemail" wire:model="loginEmail" type="email" class="input @error('loginEmail') border-red-500 @enderror" autocomplete="email" required>
      @error('loginEmail')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <label for="lpassword" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Passwort</label>
      <input id="lpassword" wire:model="loginPassword" type="password" class="input @error('loginPassword') border-red-500 @enderror" autocomplete="current-password" required>
      @error('loginPassword')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="login" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">Anmelden und weiter</button>
      </div>
      <p class="mt-3 text-sm text-zinc-500 text-center">Noch kein Konto? <button type="button" wire:click="$set('activeTab', 'register')" class="text-brand font-medium hover:underline">Jetzt kostenlos registrieren</button></p>

    @else
      {{-- Angemeldet: nur noch bestätigen --}}
      <p class="mt-6 text-zinc-700">Angemeldet als <span class="font-medium text-zinc-900">{{ auth()->user()?->name }}</span>.</p>
      @if($scenario === 'logged_in_has_company' && $existingCompany)
        <p class="mt-1 text-sm text-zinc-500">Du verwaltest bereits {{ $existingCompany->name }}. Dieser Eintrag kommt zusätzlich dazu.</p>
      @endif
      <label class="mt-5 flex items-start gap-3 cursor-pointer">
        <input type="checkbox" wire:model="confirmOwner" class="mt-1 size-5 rounded border-zinc-300">
        <span class="text-sm text-zinc-700">Ich gehöre zu {{ $company->name }} und darf den Eintrag verwalten.</span>
      </label>
      @error('confirmOwner')<p class="{{ $errorClass }}">{{ $message }}</p>@enderror

      <div class="mt-6 pt-6 border-t border-zinc-200 flex gap-2">
        <button type="button" wire:click="{{ $scenario === 'logged_in_has_company' ? 'claimAdditional' : 'claim' }}" wire:loading.attr="disabled" class="btn-primary flex-1 whitespace-nowrap">Weiter</button>
      </div>
    @endif
  @endif
</div>
