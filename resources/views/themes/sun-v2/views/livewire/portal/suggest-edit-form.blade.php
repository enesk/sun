{{--
    Reiter "Fehler melden" der Seite "Ist das dein Betrieb?" (sun-v2).
    Logik unveraendert in App\Livewire\Portal\SuggestEditForm: genau ein Feld
    (address, phone, hours, description, other). Die Pillen der Vorlage sind
    deshalb Einfachauswahl; ihre Beschriftung geht als "reason" mit, damit die
    Verwaltung auch bei "other" sieht, worum es geht.
--}}
@php
    $choices = [
        __('portal.suggest_edit.choices.address') => 'address',
        __('portal.suggest_edit.choices.phone') => 'phone',
        __('portal.suggest_edit.choices.hours') => 'hours',
        __('portal.suggest_edit.choices.website') => 'other',
        __('portal.suggest_edit.choices.services') => 'description',
        __('portal.suggest_edit.choices.closed') => 'other',
        __('portal.suggest_edit.choices.duplicate') => 'other',
        __('portal.suggest_edit.choices.other') => 'other',
    ];
@endphp
<div>
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

    {{-- Honeypot --}}
    <div class="sr-only" aria-hidden="true"><input type="text" wire:model="website_url" tabindex="-1" autocomplete="off"></div>

    <fieldset class="mt-5">
      <legend class="sr-only">{{ __('portal.suggest_edit.legend') }}</legend>
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

    <label for="korrektur" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.suggest_edit.correction_label') }}</label>
    <textarea id="korrektur" wire:model="suggestedValue" rows="4" maxlength="2000" class="input py-3 h-auto @error('suggestedValue') border-red-500 @enderror" placeholder="{{ __('portal.suggest_edit.correction_placeholder') }}"></textarea>
    @error('suggestedValue')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <label for="semail" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.suggest_edit.email_label') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
    <input id="semail" wire:model="reporterEmail" type="email" class="input @error('reporterEmail') border-red-500 @enderror" autocomplete="email">
    @error('reporterEmail')
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @else
      <p class="mt-1 text-sm text-zinc-500">{{ __('portal.suggest_edit.email_hint') }}</p>
    @enderror

    <div class="mt-6 pt-6 border-t border-zinc-200">
      <button type="button" wire:click="submit" wire:loading.attr="disabled" class="btn-primary w-full sm:w-auto">{{ __('portal.suggest_edit.submit') }}</button>
    </div>
  @endif
</div>
