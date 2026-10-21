{{--
    Aenderungsweg des Self-Service-Dialogs (SUN-RQ #4), Theme sun-v2.
    Eingebunden aus livewire/portal/profile-request-dialog; erwartet die
    Komponente App\Livewire\Portal\ProfileRequestDialog.

    Alles unterhalb von Firma/Kontakt steckt in <details>: ohne das waere das
    Blatt auf dem Telefon mehrere Bildschirme lang, und wer nur die
    Telefonnummer berichtigen will, scrollt an sieben Oeffnungszeiten-Zeilen
    vorbei. <details> braucht dafuer kein Skript und ist mit der Tastatur
    bedienbar.

    Erwartet $dayNames (aus dem Dialog uebergeben) und die Properties der
    Komponente.
--}}

{{-- Firma und Adresse --}}
<fieldset class="flex flex-col gap-3">
  <legend class="text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.basics') }}</legend>

  <div>
    <label for="pr-name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.name') }}</label>
    <input id="pr-name" type="text" wire:model.blur="name" class="input @error('name') border-red-500 @enderror" maxlength="255">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>

  {{-- Strasse und Hausnummer teilen bei 360 px eine Zeile nur dann, wenn die
       Hausnummer eine feste, schmale Spalte bekommt. --}}
  <div class="grid grid-cols-[1fr_5rem] gap-2">
    <div class="min-w-0">
      <label for="pr-street" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.street') }}</label>
      <input id="pr-street" type="text" wire:model.blur="street" class="input @error('street') border-red-500 @enderror" maxlength="255" autocomplete="address-line1">
    </div>
    <div class="min-w-0">
      <label for="pr-houseno" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.house_no') }}</label>
      <input id="pr-houseno" type="text" wire:model.blur="house_no" class="input @error('house_no') border-red-500 @enderror" maxlength="20">
    </div>
  </div>
  @error('street')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
  @error('house_no')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

  <div class="grid grid-cols-[6rem_1fr] gap-2">
    <div class="min-w-0">
      <label for="pr-zipcode" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.zipcode') }}</label>
      <input id="pr-zipcode" type="text" inputmode="numeric" wire:model.blur="zipcode" class="input @error('zipcode') border-red-500 @enderror" maxlength="5" autocomplete="postal-code">
    </div>
    <div class="min-w-0">
      <label for="pr-city" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.city') }}</label>
      <input id="pr-city" type="text" wire:model.blur="city" class="input @error('city') border-red-500 @enderror" maxlength="255" autocomplete="address-level2">
    </div>
  </div>
  @error('zipcode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
  @error('city')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</fieldset>

{{-- Kontakt --}}
<fieldset class="flex flex-col gap-3">
  <legend class="text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.contact') }}</legend>

  <div>
    <label for="pr-tel" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.tel') }}</label>
    <input id="pr-tel" type="tel" wire:model.blur="tel" class="input @error('tel') border-red-500 @enderror" maxlength="50">
    @error('tel')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>

  <div>
    <label for="pr-email" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.email') }}</label>
    <input id="pr-email" type="email" wire:model.blur="email" class="input @error('email') border-red-500 @enderror" maxlength="255">
    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>

  <div>
    <label for="pr-website" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.fields.website') }}</label>
    <input id="pr-website" type="url" wire:model.blur="website" class="input @error('website') border-red-500 @enderror" maxlength="255" placeholder="https://">
    @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>
</fieldset>

{{-- Beschreibung --}}
<details class="rounded-xl border border-zinc-200">
  <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.description') }}</summary>
  <div class="px-4 pb-4">
    <label for="pr-description" class="sr-only">{{ __('portal.profile_request.fields.description') }}</label>
    <textarea id="pr-description" wire:model.blur="description" rows="6" maxlength="5000" class="input py-3 h-auto @error('description') border-red-500 @enderror"></textarea>
    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>
</details>

{{-- Oeffnungszeiten --}}
<details class="rounded-xl border border-zinc-200">
  <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.hours') }}</summary>
  <div class="px-4 pb-4">
    <ul class="divide-y divide-zinc-100">
      @foreach($hours as $index => $row)
        <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3" wire:key="pr-hour-{{ $index }}">
          <span class="w-24 shrink-0 text-sm font-medium text-zinc-900">{{ $dayNames[$row['day']] ?? '' }}</span>

          <label class="flex items-center gap-1.5 text-sm text-zinc-700">
            <input type="checkbox" wire:model.live="hours.{{ $index }}.closed" class="size-4 rounded border-zinc-300 text-brand focus:ring-brand">
            {{ __('portal.profile_request.hours.closed') }}
          </label>

          @unless($row['closed'])
            <div class="flex w-full items-center gap-2">
              <label class="sr-only" for="pr-hour-{{ $index }}-opens">{{ __('portal.profile_request.hours.from') }}</label>
              <input id="pr-hour-{{ $index }}-opens" type="time" wire:model.blur="hours.{{ $index }}.opens" class="input flex-1 min-w-0 tabular-nums @error('hours.'.$index.'.opens') border-red-500 @enderror">
              <span class="text-zinc-400" aria-hidden="true">–</span>
              <label class="sr-only" for="pr-hour-{{ $index }}-closes">{{ __('portal.profile_request.hours.to') }}</label>
              <input id="pr-hour-{{ $index }}-closes" type="time" wire:model.blur="hours.{{ $index }}.closes" class="input flex-1 min-w-0 tabular-nums @error('hours.'.$index.'.closes') border-red-500 @enderror">
            </div>
          @endunless
        </li>
      @endforeach
    </ul>
    <p class="mt-2 text-sm text-zinc-500">{{ __('portal.profile_request.hours.hint') }}</p>
  </div>
</details>

{{-- Leistungen --}}
<details class="rounded-xl border border-zinc-200">
  <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.services') }}</summary>
  <div class="px-4 pb-4 flex flex-col gap-3">
    @forelse($services as $index => $service)
      <div class="rounded-xl bg-zinc-50 p-3 flex flex-col gap-2" wire:key="pr-service-{{ $index }}">
        <div class="grid grid-cols-[1fr_6rem] gap-2">
          <div class="min-w-0">
            <label for="pr-service-{{ $index }}-name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.services.name') }}</label>
            <input id="pr-service-{{ $index }}-name" type="text" wire:model.blur="services.{{ $index }}.name" class="input" maxlength="150">
          </div>
          <div class="min-w-0">
            <label for="pr-service-{{ $index }}-price" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.services.price') }}</label>
            <input id="pr-service-{{ $index }}-price" type="text" inputmode="decimal" wire:model.blur="services.{{ $index }}.price" class="input" maxlength="20" placeholder="89,00">
          </div>
        </div>
        <div>
          <label for="pr-service-{{ $index }}-desc" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.services.description') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
          <textarea id="pr-service-{{ $index }}-desc" wire:model.blur="services.{{ $index }}.description" rows="2" maxlength="500" class="input py-2 h-auto"></textarea>
        </div>
        <button type="button" wire:click="removeService({{ $index }})" class="btn-ghost self-start text-zinc-600 hover:bg-zinc-100">{{ __('portal.profile_request.services.remove') }}</button>
      </div>
    @empty
      <p class="text-sm text-zinc-500">{{ __('portal.profile_request.services.empty') }}</p>
    @endforelse

    <button type="button" wire:click="addService" class="btn-secondary self-start"><x-sun.icon name="plus" class="icon" />{{ __('portal.profile_request.services.add') }}</button>
  </div>
</details>

{{-- Logo und Bilder --}}
<details class="rounded-xl border border-zinc-200">
  <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.sections.images') }}</summary>
  <div class="px-4 pb-4 flex flex-col gap-3">
    <div>
      <label for="pr-logo" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.images.logo') }}</label>
      {{-- Datei-Felder sind bei 360 px nur bedienbar, wenn sie die ganze
           Breite bekommen; `w-full` statt der input-Klasse, damit der
           Browser-Knopf nicht aus dem Rahmen laeuft. --}}
      <input id="pr-logo" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="w-full max-w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900">
      <div wire:loading wire:target="logo" class="mt-1 text-sm text-zinc-500">…</div>
      @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="pr-images" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.profile_request.images.more') }}</label>
      <input id="pr-images" type="file" wire:model="images" multiple accept="image/jpeg,image/png,image/webp" class="w-full max-w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900">
      <div wire:loading wire:target="images" class="mt-1 text-sm text-zinc-500">…</div>
      @error('images')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      @error('images.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

      @if($images !== [])
        <ul class="mt-2 flex flex-col gap-1">
          @foreach($images as $index => $bild)
            <li class="flex items-center justify-between gap-2 text-sm text-zinc-700" wire:key="pr-image-{{ $index }}">
              <span class="min-w-0 truncate">{{ $bild->getClientOriginalName() }}</span>
              <button type="button" wire:click="removeImage({{ $index }})" class="shrink-0 text-zinc-500 hover:text-zinc-900" aria-label="{{ __('portal.profile_request.images.remove') }}"><x-sun.icon name="x" class="size-4" /></button>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    <p class="text-sm text-zinc-500">{{ __('portal.profile_request.images.hint') }}</p>
  </div>
</details>
