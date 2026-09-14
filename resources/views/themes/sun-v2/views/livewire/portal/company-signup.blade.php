{{--
    "Trag deinen Betrieb ein" – zwei Schritte in einer Karte (Vorlage elektrikerportal-firma-eintragen (1).html).
    Logik: App\Livewire\Portal\CompanySignup. Validiert erst beim Weiterklicken, Hinweistext statt nur roter Rahmen.
--}}
@php
    $hint = 'mt-1 text-sm';
    $guest = auth()->guest();
@endphp
<form class="card p-5 md:p-8" novalidate wire:submit="next"
      x-data x-on:signup-step.window="$el.scrollIntoView({ block: 'start', behavior: 'smooth' })">

  <!-- Fortschritt -->
  <ol class="flex items-center gap-3 text-sm" aria-label="Fortschritt">
    <li class="flex items-center gap-2" @if($step === 1) aria-current="step" @endif><span class="size-7 rounded-full bg-brand text-white font-semibold flex items-center justify-center shrink-0">1</span><span class="font-medium text-zinc-900">Betrieb</span></li>
    <li class="flex-1 h-px bg-zinc-200" aria-hidden="true"></li>
    <li @class(['flex items-center gap-2', 'text-zinc-500' => $step < 2]) @if($step === 2) aria-current="step" @endif><span @class(['size-7 rounded-full font-semibold flex items-center justify-center shrink-0', 'bg-brand text-white' => $step === 2, 'bg-zinc-100' => $step < 2])>2</span><span @class(['font-medium text-zinc-900' => $step === 2])>Konto</span></li>
  </ol>

  {{-- Honeypot --}}
  <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

  @if($step === 1)
    <!-- ===== SCHRITT 1: BETRIEB ===== -->
    <div class="mt-6 pt-6 border-t border-zinc-200">
      <label for="firma" class="block text-sm font-medium text-zinc-700 mb-1">Name des Betriebs</label>
      <input id="firma" wire:model="firma" class="input @error('firma') border-red-500 @enderror" placeholder="z. B. Elektro Beck" autocomplete="organization" required>
      @error('firma')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <label for="strasse" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Straße und Hausnummer</label>
      <input id="strasse" wire:model="strasse" class="input @error('strasse') border-red-500 @enderror" autocomplete="street-address" required>
      @error('strasse')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <div class="mt-4 grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
        <div>
          <label for="plz" class="block text-sm font-medium text-zinc-700 mb-1">PLZ</label>
          <input id="plz" wire:model="plz" class="input @error('plz') border-red-500 @enderror" inputmode="numeric" maxlength="5" autocomplete="postal-code" required>
        </div>
        <div>
          <label for="ort" class="block text-sm font-medium text-zinc-700 mb-1">Ort</label>
          <input id="ort" wire:model="ort" class="input @error('ort') border-red-500 @enderror" autocomplete="address-level2" required>
        </div>
      </div>
      @error('plz')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror
      @error('ort')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

      <label for="tel" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Telefonnummer</label>
      <input id="tel" type="tel" wire:model="tel" class="input @error('tel') border-red-500 @enderror" inputmode="tel" autocomplete="tel" required aria-describedby="telHint">
      <p id="telHint" @class([$hint, 'text-red-600' => $errors->has('tel'), 'text-zinc-500' => ! $errors->has('tel')])>{{ $errors->first('tel') ?: 'Diese Nummer sehen Kund:innen auf deinem Profil.' }}</p>

      <label for="web" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Website <span class="text-zinc-500 font-normal">(optional)</span></label>
      <input id="web" type="url" wire:model="web" class="input @error('web') border-red-500 @enderror" placeholder="https://" autocomplete="url">
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
        <button type="button" wire:click="back" class="text-sm text-brand font-medium hover:underline shrink-0">Ändern</button>
      </div>

      @if($guest)
        <label for="name" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">Dein Name</label>
        <input id="name" wire:model="name" class="input @error('name') border-red-500 @enderror" autocomplete="name" required>
        @error('name')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror

        <label for="email" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">E-Mail</label>
        <input id="email" type="email" wire:model="email" class="input @error('email') border-red-500 @enderror" autocomplete="email" inputmode="email" required aria-describedby="emailHint">
        <p id="emailHint" @class([$hint, 'text-red-600' => $errors->has('email'), 'text-zinc-500' => ! $errors->has('email')])>
          {{ $errors->first('email') ?: 'Damit meldest du dich an und verwaltest deinen Eintrag.' }}
          @if($errors->has('email') && str_contains($errors->first('email'), 'melde dich an'))<a href="{{ route('login') }}" class="underline">Anmelden</a>@endif
        </p>

        <label for="pass" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Passwort</label>
        <div class="relative" x-data="{ show: false }">
          <input id="pass" :type="show ? 'text' : 'password'" type="password" wire:model="password" class="input pr-12 @error('password') border-red-500 @enderror" autocomplete="new-password" required aria-describedby="passHint">
          <button type="button" x-on:click="show = !show" :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'" class="absolute right-1 top-1/2 -translate-y-1/2 size-10 rounded-lg text-zinc-400 hover:text-zinc-700 flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand" aria-label="Passwort anzeigen"><x-sun.icon name="eye" class="size-5" /></button>
        </div>
        <p id="passHint" @class([$hint, 'text-red-600' => $errors->has('password'), 'text-zinc-500' => ! $errors->has('password')])>{{ $errors->first('password') ?: 'Mindestens 8 Zeichen.' }}</p>

        <label @class(['mt-5 flex items-start gap-3 cursor-pointer', 'text-red-600' => $errors->has('agb')])>
          <input type="checkbox" wire:model="agb" class="mt-1 size-5 rounded border-zinc-300 text-brand focus:ring-brand" required>
          <span @class(['text-sm', 'text-zinc-700' => ! $errors->has('agb')])>Ich akzeptiere die <a href="{{ route('terms-of-service') }}" class="underline">AGB</a> und habe die <a href="{{ route('portal.datenschutz') }}" class="underline">Datenschutzhinweise</a> gelesen.</span>
        </label>
        @error('agb')<p class="{{ $hint }} text-red-600">{{ $message }}</p>@enderror
      @else
        <p class="mt-5 text-zinc-700">Angemeldet als <span class="font-medium text-zinc-900">{{ auth()->user()->name }}</span>. Der Eintrag kommt in dein bestehendes Konto.</p>
      @endif
    </div>
  @endif

  <button type="submit" class="mt-6 btn-primary w-full" wire:loading.attr="disabled" wire:target="next">{{ $step === 2 ? ($guest ? 'Konto anlegen' : 'Eintrag anlegen') : 'Weiter' }}</button>
  <p class="mt-3 text-sm text-zinc-500 text-center">{{ $step === 2 ? 'Kostenlos, keine Kreditkarte nötig.' : 'Kostenlos, ohne Laufzeit.' }}</p>
  @if($guest)
    <p class="mt-4 pt-4 border-t border-zinc-200 text-sm text-zinc-500 text-center">Schon ein Konto? <a href="{{ route('login') }}" class="text-brand font-medium hover:underline">Anmelden</a></p>
  @endif

  @if($done)
    <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="doneTitle" x-data x-init="document.body.style.overflow = 'hidden'">
      <div class="absolute inset-0 bg-zinc-900/40"></div>
      <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-md p-6 text-center flex flex-col items-center gap-3 shadow-lg" style="padding-bottom:max(1.5rem,env(safe-area-inset-bottom))">
          <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
          @if(auth()->user()?->hasVerifiedEmail())
            <h2 id="doneTitle" class="text-lg font-semibold text-zinc-900">Eintrag angelegt</h2>
            <p class="text-zinc-500">Wir prüfen den Eintrag und schalten ihn meist innerhalb eines Werktags frei.</p>
            <a href="{{ route('portal.owner.dashboard') }}" class="btn-primary w-full mt-2">Profil vervollständigen</a>
          @else
            <h2 id="doneTitle" class="text-lg font-semibold text-zinc-900">Bestätige deine E-Mail</h2>
            <p class="text-zinc-500">Wir haben dir einen Link geschickt. Danach prüfen wir den Eintrag und schalten ihn meist innerhalb eines Werktags frei.</p>
            <a href="{{ route('portal.owner.dashboard') }}" class="btn-primary w-full mt-2">Profil vervollständigen</a>
            <button type="button" wire:click="resendVerification" wire:loading.attr="disabled" class="btn-ghost w-full" @disabled($resent)>{{ $resent ? 'E-Mail ist unterwegs' : 'E-Mail noch mal senden' }}</button>
          @endif
        </div>
      </div>
    </div>
  @endif
</form>
