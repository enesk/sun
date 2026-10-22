{{--
    Reiter "Fehler melden" der Seite "Ist das dein Betrieb?" (sun-v2).
    Logik: App\Livewire\Portal\SuggestEditForm.

    Je Kategorie ein eigenes Feld, das erst mit dem Haken aufklappt; mehrere
    gleichzeitig (#20). Die Felder sind mit dem aktuellen Stand vorbelegt.

    Die Kategorie ist eine Checkbox mit `wire:model.live="kategorien"` — live,
    weil das Aufklappen serverseitig entschieden wird (die Regeln haengen
    daran, und ein rein im Browser aufgeklapptes Feld waere nicht validiert).
    Der Kasten darunter steht in `@if`, nicht nur versteckt: ein zugeklapptes
    Feld darf nichts einreichen.
--}}
<div>
  {{-- Skripte unbedingt, auch wenn das Widget erst nach einem Livewire-Umlauf erscheint: der Stack 'scripts' wird nur beim Rendern des Layouts geleert (#53). --}}
  <x-turnstile-scripts action="contact" />

  @if($submitted)
    <div class="text-center py-4 flex flex-col items-center gap-3">
      <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
      <h3 class="text-lg font-semibold text-zinc-900">{{ __('portal.suggest_edit.done.heading') }}</h3>
      <p class="text-zinc-500 max-w-sm">{{ __('portal.suggest_edit.done.text') }} <a href="{{ $company->portal_url }}" class="text-brand hover:underline">{{ __('portal.suggest_edit.done.back') }}</a></p>
    </div>
  @else
    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.suggest_edit.heading') }}</h2>
    <p class="mt-1 text-sm text-zinc-500">{{ __('portal.suggest_edit.intro') }}</p>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>

    {{-- Honigtopf mit Zufallsnamen und Mindest-Ausfuellzeit (#22) --}}
    <x-antispam-fields wire />

    <fieldset class="mt-5">
      <legend class="text-sm font-medium text-zinc-700">{{ __('portal.suggest_edit.legend') }}</legend>

      <div class="mt-3 flex flex-col gap-2">
        @foreach(\App\Livewire\Portal\SuggestEditForm::KATEGORIEN as $kategorie)
          @php($offen = in_array($kategorie, $kategorien, true))
          <div @class(['rounded-2xl border transition-colors', 'border-brand bg-brand-50/40' => $offen, 'border-zinc-200' => ! $offen])>
            <label class="flex items-start gap-3 p-4 cursor-pointer">
              <input type="checkbox" value="{{ $kategorie }}" wire:model.live="kategorien"
                     class="mt-0.5 size-5 rounded border-zinc-300 text-brand focus:ring-brand shrink-0">
              <span @class(['text-sm font-medium', 'text-brand-700' => $offen, 'text-zinc-700' => ! $offen])>{{ __('portal.suggest_edit.choices.'.$kategorie) }}</span>
            </label>

            @if($offen)
              <div class="px-4 pb-4 pt-0 border-t border-zinc-200/70 mt-0">
                @if($kategorie === 'address')
                  <p class="mt-3 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.prefilled') }}</p>
                  <div class="mt-2 grid grid-cols-[minmax(0,1fr)_7rem] gap-3">
                    <div>
                      <label for="k-street" class="block text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.street') }}</label>
                      <input id="k-street" wire:model="street" class="input @error('street') border-red-500 @enderror" autocomplete="off">
                    </div>
                    <div>
                      <label for="k-house" class="block text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.house_no') }}</label>
                      <input id="k-house" wire:model="houseNo" class="input @error('houseNo') border-red-500 @enderror" autocomplete="off">
                    </div>
                  </div>
                  <div class="mt-3 grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                    <div>
                      <label for="k-zip" class="block text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.zipcode') }}</label>
                      <input id="k-zip" wire:model="zipcode" inputmode="numeric" class="input @error('zipcode') border-red-500 @enderror" autocomplete="off">
                    </div>
                    <div>
                      <label for="k-city" class="block text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.city') }}</label>
                      <input id="k-city" wire:model="city" class="input @error('city') border-red-500 @enderror" autocomplete="off">
                    </div>
                  </div>
                  @error('street')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                  @error('houseNo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                  @error('zipcode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                  @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'phone')
                  <label for="k-tel" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.tel') }}</label>
                  <input id="k-tel" wire:model="tel" type="tel" inputmode="tel" class="input @error('tel') border-red-500 @enderror" autocomplete="off">
                  <p class="mt-1 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.prefilled') }}</p>
                  @error('tel')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'website')
                  <label for="k-web" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.website') }}</label>
                  <input id="k-web" wire:model="website" type="url" inputmode="url" class="input @error('website') border-red-500 @enderror" autocomplete="off" placeholder="https://">
                  <p class="mt-1 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.prefilled') }}</p>
                  @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'services')
                  <label for="k-desc" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.description') }}</label>
                  <textarea id="k-desc" wire:model="description" rows="4" maxlength="2000" class="input py-3 h-auto @error('description') border-red-500 @enderror"></textarea>
                  <p class="mt-1 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.prefilled') }}</p>
                  @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'hours')
                  <label for="k-hours" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.hours') }}</label>
                  <textarea id="k-hours" wire:model="openingHours" rows="4" maxlength="2000" class="input py-3 h-auto @error('openingHours') border-red-500 @enderror"></textarea>
                  <p class="mt-1 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.hours') }}</p>
                  @error('openingHours')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'duplicate')
                  <label for="k-dup" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.duplicate') }}</label>
                  <input id="k-dup" wire:model="duplicateOf" class="input @error('duplicateOf') border-red-500 @enderror" autocomplete="off">
                  <p class="mt-1 text-xs text-zinc-500">{{ __('portal.suggest_edit.hints.duplicate') }}</p>
                  @error('duplicateOf')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                @elseif($kategorie === 'closed')
                  <p class="mt-3 text-sm text-zinc-500">{{ __('portal.suggest_edit.hints.closed') }}</p>

                @elseif($kategorie === 'other')
                  <label for="k-note" class="block mt-3 text-sm text-zinc-700 mb-1">{{ __('portal.suggest_edit.fields.note') }}</label>
                  <textarea id="k-note" wire:model="note" rows="4" maxlength="2000" class="input py-3 h-auto @error('note') border-red-500 @enderror" placeholder="{{ __('portal.suggest_edit.correction_placeholder') }}"></textarea>
                  @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
              </div>
            @endif
          </div>
        @endforeach
      </div>

      @error('kategorien')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <label for="semail" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.suggest_edit.email_label') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
    <input id="semail" wire:model="reporterEmail" type="email" class="input @error('reporterEmail') border-red-500 @enderror" autocomplete="email">
    @error('reporterEmail')
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @else
      <p class="mt-1 text-sm text-zinc-500">{{ __('portal.suggest_edit.email_hint') }}</p>
    @enderror

    {{-- Turnstile (#22), Modus non_interactive. gate="false": der Knopf
         haengt an wire:click, nicht an einem Submit. --}}
    <x-turnstile action="contact" wire="turnstileToken" field="turnstileToken" size="flexible" gate="false" />

    <div class="mt-6 pt-6 border-t border-zinc-200">
      <button type="button" wire:click="submit" wire:loading.attr="disabled" class="btn-primary w-full sm:w-auto">{{ __('portal.suggest_edit.submit') }}</button>
    </div>
  @endif
</div>
