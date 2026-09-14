{{--
    Reiter "Fehler melden" der Seite "Ist das dein Betrieb?" (sun-v2).
    Logik unveraendert in App\Livewire\Portal\SuggestEditForm: genau ein Feld
    (address, phone, hours, description, other). Die Pillen der Vorlage sind
    deshalb Einfachauswahl; ihre Beschriftung geht als "reason" mit, damit die
    Verwaltung auch bei "other" sieht, worum es geht.
--}}
@php
    $choices = [
        'Adresse' => 'address',
        'Telefonnummer' => 'phone',
        'Öffnungszeiten' => 'hours',
        'Website' => 'other',
        'Leistungen' => 'description',
        'Betrieb existiert nicht mehr' => 'other',
        'Doppelter Eintrag' => 'other',
        'Sonstiges' => 'other',
    ];
@endphp
<div>
  @if($submitted)
    <div class="text-center py-4 flex flex-col items-center gap-3">
      <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
      <h3 class="text-lg font-semibold text-zinc-900">Danke für den Hinweis</h3>
      <p class="text-zinc-500 max-w-sm">Wir prüfen die Änderung meist innerhalb von 2 Werktagen. <a href="{{ $company->portal_url }}" class="text-brand hover:underline">Zurück zum Eintrag</a></p>
    </div>
  @else
    <h2 class="text-lg font-semibold text-zinc-900">Was stimmt nicht?</h2>
    <p class="mt-1 text-sm text-zinc-500">Du musst nicht zum Betrieb gehören. Wir prüfen den Hinweis und korrigieren den Eintrag.</p>
    <div class="mt-4"><x-sun.claim-company :company="$company" /></div>

    {{-- Honeypot --}}
    <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

    <fieldset class="mt-5">
      <legend class="sr-only">Was ist falsch?</legend>
      <div class="flex flex-wrap gap-2">
        @foreach($choices as $label => $field)
          <label class="cursor-pointer">
            <input type="radio" name="suggest-field" value="{{ $label }}" class="peer sr-only" @checked($reason === $label)
                   wire:click="$set('field', '{{ $field }}'); $set('reason', '{{ $label }}')">
            <span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ $label }}</span>
          </label>
        @endforeach
      </div>
      @error('field')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <label for="korrektur" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">Wie lautet es richtig?</label>
    <textarea id="korrektur" wire:model="suggestedValue" rows="4" maxlength="2000" class="input py-3 h-auto @error('suggestedValue') border-red-500 @enderror" placeholder="z. B. Die Telefonnummer ist seit Mai eine andere, die alte ist abgeschaltet."></textarea>
    @error('suggestedValue')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <label for="semail" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Deine E-Mail <span class="text-zinc-500 font-normal">(optional)</span></label>
    <input id="semail" wire:model="reporterEmail" type="email" class="input @error('reporterEmail') border-red-500 @enderror" autocomplete="email">
    @error('reporterEmail')
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @else
      <p class="mt-1 text-sm text-zinc-500">Nur falls wir eine Rückfrage haben. Wird nicht veröffentlicht.</p>
    @enderror

    <div class="mt-6 pt-6 border-t border-zinc-200">
      <button type="button" wire:click="submit" wire:loading.attr="disabled" class="btn-primary w-full sm:w-auto">Änderung senden</button>
    </div>
  @endif
</div>
