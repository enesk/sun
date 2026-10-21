{{--
    Aenderungsweg des Self-Service-Dialogs (SUN-RQ #4), Theme default (#14).
    Eingebunden aus livewire/portal/profile-request-dialog; erwartet die
    Komponente App\Livewire\Portal\ProfileRequestDialog.

    Alles unterhalb von Firma/Kontakt steckt in <details>: ohne das waere der
    Kasten auf dem Telefon mehrere Bildschirme lang, und wer nur die
    Telefonnummer berichtigen will, scrollt an sieben Oeffnungszeiten-Zeilen
    vorbei. <details> braucht dafuer kein Skript und ist mit der Tastatur
    bedienbar.

    Erwartet $dayNames (aus dem Dialog uebergeben) und die Properties der
    Komponente.
--}}

{{-- Firma und Adresse --}}
<fieldset class="flex flex-col gap-3">
    <legend class="text-sm font-semibold text-base-content mb-1.5">{{ __('portal.profile_request.sections.basics') }}</legend>

    <div>
        <label for="pr-name" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.name') }}</label>
        <input id="pr-name" type="text" wire:model.blur="name" class="input-portal w-full" maxlength="255">
        @error('name')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>

    {{-- Strasse und Hausnummer teilen bei 360 px eine Zeile nur dann, wenn die
         Hausnummer eine feste, schmale Spalte bekommt. --}}
    <div class="grid grid-cols-[1fr_5rem] gap-3">
        <div class="min-w-0">
            <label for="pr-street" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.street') }}</label>
            <input id="pr-street" type="text" wire:model.blur="street" class="input-portal w-full" maxlength="255" autocomplete="address-line1">
        </div>
        <div class="min-w-0">
            <label for="pr-houseno" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.house_no') }}</label>
            <input id="pr-houseno" type="text" wire:model.blur="house_no" class="input-portal w-full" maxlength="20">
        </div>
    </div>
    @error('street')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror
    @error('house_no')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror

    <div class="grid grid-cols-[6rem_1fr] gap-3">
        <div class="min-w-0">
            <label for="pr-zipcode" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.zipcode') }}</label>
            <input id="pr-zipcode" type="text" inputmode="numeric" wire:model.blur="zipcode" class="input-portal w-full" maxlength="5" autocomplete="postal-code">
        </div>
        <div class="min-w-0">
            <label for="pr-city" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.city') }}</label>
            <input id="pr-city" type="text" wire:model.blur="city" class="input-portal w-full" maxlength="255" autocomplete="address-level2">
        </div>
    </div>
    @error('zipcode')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror
    @error('city')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror
</fieldset>

{{-- Kontakt --}}
<fieldset class="flex flex-col gap-3">
    <legend class="text-sm font-semibold text-base-content mb-1.5">{{ __('portal.profile_request.sections.contact') }}</legend>

    <div>
        <label for="pr-tel" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.tel') }}</label>
        <input id="pr-tel" type="tel" wire:model.blur="tel" class="input-portal w-full" maxlength="50">
        @error('tel')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="pr-email" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.email') }}</label>
        <input id="pr-email" type="email" wire:model.blur="email" class="input-portal w-full" maxlength="255">
        @error('email')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="pr-website" class="label-portal mb-1.5">{{ __('portal.profile_request.fields.website') }}</label>
        <input id="pr-website" type="url" wire:model.blur="website" class="input-portal w-full" maxlength="255" placeholder="https://">
        @error('website')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>
</fieldset>

{{-- Beschreibung --}}
<details class="rounded-xl border border-base-content/10">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-base-content">{{ __('portal.profile_request.sections.description') }}</summary>
    <div class="px-4 pb-4">
        <label for="pr-description" class="sr-only">{{ __('portal.profile_request.fields.description') }}</label>
        <textarea id="pr-description" wire:model.blur="description" rows="6" maxlength="5000" class="input-portal w-full min-h-[140px] resize-y"></textarea>
        @error('description')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>
</details>

{{-- Oeffnungszeiten --}}
<details class="rounded-xl border border-base-content/10">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-base-content">{{ __('portal.profile_request.sections.hours') }}</summary>
    <div class="px-4 pb-4">
        <ul class="divide-y divide-base-content/10">
            @foreach($hours as $index => $row)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3" wire:key="pr-hour-{{ $index }}">
                    <span class="w-24 shrink-0 text-sm font-medium text-base-content">{{ $dayNames[$row['day']] ?? '' }}</span>

                    <label class="flex items-center gap-1.5 text-sm text-base-content/70">
                        <input type="checkbox" wire:model.live="hours.{{ $index }}.closed" class="w-4 h-4 rounded border-base-content/20">
                        {{ __('portal.profile_request.hours.closed') }}
                    </label>

                    @unless($row['closed'])
                        <div class="flex w-full items-center gap-2">
                            <label class="sr-only" for="pr-hour-{{ $index }}-opens">{{ __('portal.profile_request.hours.from') }}</label>
                            <input id="pr-hour-{{ $index }}-opens" type="time" wire:model.blur="hours.{{ $index }}.opens" class="input-portal flex-1 min-w-0 tabular-nums">
                            <span class="text-base-content/40" aria-hidden="true">–</span>
                            <label class="sr-only" for="pr-hour-{{ $index }}-closes">{{ __('portal.profile_request.hours.to') }}</label>
                            <input id="pr-hour-{{ $index }}-closes" type="time" wire:model.blur="hours.{{ $index }}.closes" class="input-portal flex-1 min-w-0 tabular-nums">
                        </div>
                        @error('hours.'.$index.'.opens')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror
                        @error('hours.'.$index.'.closes')<p class="text-xs text-red-500" role="alert">{{ $message }}</p>@enderror
                    @endunless
                </li>
            @endforeach
        </ul>
        <p class="text-xs text-base-content/50 mt-2">{{ __('portal.profile_request.hours.hint') }}</p>
    </div>
</details>

{{-- Leistungen --}}
<details class="rounded-xl border border-base-content/10">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-base-content">{{ __('portal.profile_request.sections.services') }}</summary>
    <div class="px-4 pb-4 flex flex-col gap-3">
        @forelse($services as $index => $service)
            <div class="rounded-xl bg-base-200/60 p-3 flex flex-col gap-2" wire:key="pr-service-{{ $index }}">
                <div class="grid grid-cols-[1fr_6rem] gap-3">
                    <div class="min-w-0">
                        <label for="pr-service-{{ $index }}-name" class="label-portal mb-1.5">{{ __('portal.profile_request.services.name') }}</label>
                        <input id="pr-service-{{ $index }}-name" type="text" wire:model.blur="services.{{ $index }}.name" class="input-portal w-full" maxlength="150">
                    </div>
                    <div class="min-w-0">
                        <label for="pr-service-{{ $index }}-price" class="label-portal mb-1.5">{{ __('portal.profile_request.services.price') }}</label>
                        <input id="pr-service-{{ $index }}-price" type="text" inputmode="decimal" wire:model.blur="services.{{ $index }}.price" class="input-portal w-full" maxlength="20" placeholder="89,00">
                    </div>
                </div>
                <div>
                    <label for="pr-service-{{ $index }}-desc" class="label-portal mb-1.5">{{ __('portal.profile_request.services.description') }} <span class="text-base-content/40">{{ __('portal.layout.optional') }}</span></label>
                    <textarea id="pr-service-{{ $index }}-desc" wire:model.blur="services.{{ $index }}.description" rows="2" maxlength="500" class="input-portal w-full resize-y"></textarea>
                </div>
                <button type="button" wire:click="removeService({{ $index }})" class="self-start text-sm text-base-content/50 hover:text-base-content transition-colors">{{ __('portal.profile_request.services.remove') }}</button>
            </div>
        @empty
            <p class="text-sm text-base-content/50">{{ __('portal.profile_request.services.empty') }}</p>
        @endforelse

        <button type="button" wire:click="addService" class="btn-portal-outline self-start px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('portal.profile_request.services.add') }}
        </button>
    </div>
</details>

{{-- Logo und Bilder --}}
<details class="rounded-xl border border-base-content/10">
    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-base-content">{{ __('portal.profile_request.sections.images') }}</summary>
    <div class="px-4 pb-4 flex flex-col gap-3">
        <div>
            <label for="pr-logo" class="label-portal mb-1.5">{{ __('portal.profile_request.images.logo') }}</label>
            {{-- Datei-Felder sind bei 360 px nur bedienbar, wenn sie die ganze
                 Breite bekommen; ohne input-portal, damit der Browser-Knopf
                 nicht aus dem Rahmen laeuft. --}}
            <input id="pr-logo" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="w-full max-w-full text-sm text-base-content/70 file:mr-3 file:rounded-lg file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-base-content">
            <div wire:loading wire:target="logo" class="text-xs text-base-content/50 mt-1">…</div>
            @error('logo')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="pr-images" class="label-portal mb-1.5">{{ __('portal.profile_request.images.more') }}</label>
            <input id="pr-images" type="file" wire:model="images" multiple accept="image/jpeg,image/png,image/webp" class="w-full max-w-full text-sm text-base-content/70 file:mr-3 file:rounded-lg file:border-0 file:bg-base-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-base-content">
            <div wire:loading wire:target="images" class="text-xs text-base-content/50 mt-1">…</div>
            @error('images')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
            @error('images.*')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror

            @if($images !== [])
                <ul class="mt-2 flex flex-col gap-1">
                    @foreach($images as $index => $bild)
                        <li class="flex items-center justify-between gap-2 text-sm text-base-content/70" wire:key="pr-image-{{ $index }}">
                            <span class="min-w-0 truncate">{{ $bild->getClientOriginalName() }}</span>
                            <button type="button" wire:click="removeImage({{ $index }})" class="shrink-0 text-base-content/50 hover:text-base-content transition-colors" aria-label="{{ __('portal.profile_request.images.remove') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <p class="text-xs text-base-content/50">{{ __('portal.profile_request.images.hint') }}</p>
    </div>
</details>
