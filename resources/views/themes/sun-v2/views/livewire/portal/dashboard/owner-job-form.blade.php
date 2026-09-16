{{--
    Formular "Stellenanzeige erstellen / bearbeiten" im Theme sun-v2, Stil wie profile-edit-form.
    Logik unveraendert in App\Livewire\Portal\Dashboard\OwnerJobForm. Texte: lang/de/portal.php (owner.jobs.form.*).

    Abweichung vom Default-Theme: statt des dreistufigen Assistenten stehen alle drei Abschnitte
    untereinander, gespeichert wird ueber die Aktionsleiste (Desktop unten, mobil fixiert).
    Die clientseitige Sperre von "Weiter" entfaellt damit, geprueft wird wie bisher in save().
--}}
@php
    $fieldClass = fn (string $field) => 'input'.($errors->has($field) ? ' border-red-600' : '');
    $saveLabel = $isEdit ? __('portal.owner.jobs.form.save_edit') : __('portal.owner.jobs.form.save_create');
    $employmentLabels = collect($employmentTypes)->keys()->mapWithKeys(fn ($value) => [$value => __("portal.owner.jobs.employment_types.{$value}")])->all();
@endphp
<div class="pb-24 md:pb-0">
  <form wire:submit="save" id="job-form" class="mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">

    <div class="space-y-4 md:space-y-6 min-w-0">

      {{-- Stelle --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-stelle">
        <h2 id="sec-stelle" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.form.info.title') }}</h2>
        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="title">{{ __('portal.owner.jobs.form.info.job_title') }}</label>
            <input id="title" type="text" wire:model.blur="title" class="{{ $fieldClass('title') }}" maxlength="255"
                   placeholder="{{ __('portal.owner.jobs.form.info.job_title_placeholder') }}" @error('title') aria-describedby="title-error" @enderror>
            @error('title') <p id="title-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div class="grid sm:grid-cols-2 gap-3 md:gap-4">
            <div>
              <label class="block text-sm font-medium text-zinc-700 mb-1" for="employment_type">{{ __('portal.owner.jobs.form.info.employment_type') }}</label>
              <select id="employment_type" wire:model="employment_type" class="{{ $fieldClass('employment_type') }}">
                @foreach($employmentTypes as $value => $label)
                  <option value="{{ $value }}">{{ __("portal.owner.jobs.employment_types.{$value}") }}</option>
                @endforeach
              </select>
              @error('employment_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
              <label class="block text-sm font-medium text-zinc-700 mb-1" for="location">{{ __('portal.owner.jobs.form.info.location') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.jobs.form.optional') }}</span></label>
              <input id="location" type="text" wire:model.blur="location" class="{{ $fieldClass('location') }}" maxlength="255" aria-describedby="location-hint"
                     placeholder="{{ __('portal.owner.jobs.form.info.location_placeholder') }}">
              <p id="location-hint" class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.jobs.form.info.location_hint') }}</p>
              @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="citySearch">{{ __('portal.owner.jobs.form.info.city') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.jobs.form.optional') }}</span></label>
            <div class="relative">
              <input id="citySearch" type="text" wire:model.live.debounce.300ms="citySearch" @focus="open = true" @input="open = true"
                     class="{{ $fieldClass('city_id') }} pr-12" role="combobox" aria-controls="city-suggestions" aria-autocomplete="list" autocomplete="off"
                     :aria-expanded="open && {{ count($citySuggestions) }} > 0 ? 'true' : 'false'"
                     placeholder="{{ __('portal.owner.jobs.form.info.city_placeholder') }}">
              @if($city_id)
                <button type="button" wire:click="clearCity"
                        class="absolute right-0 top-0 inline-flex size-11 items-center justify-center rounded-xl text-zinc-500 hover:text-zinc-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                        aria-label="{{ __('portal.owner.jobs.form.info.city_clear') }}">
                  <x-sun.icon name="x" class="icon" />
                </button>
              @else
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2"><x-sun.icon name="search" class="icon text-zinc-400" /></span>
              @endif
            </div>
            @if(count($citySuggestions) > 0)
              <ul x-show="open" x-cloak id="city-suggestions" role="listbox" class="absolute z-10 mt-1 w-full max-h-60 overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg">
                @foreach($citySuggestions as $city)
                  <li role="option" wire:click="selectCity({{ $city['id'] }})" @click="open = false"
                      class="cursor-pointer px-4 py-2 text-base text-zinc-700 hover:bg-zinc-50">{{ $city['zipcode'] }} {{ $city['name'] }}</li>
                @endforeach
              </ul>
            @endif
            @error('city_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
        </div>
      </section>

      {{-- Beschreibung --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-beschreibung">
        <h2 id="sec-beschreibung" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.form.description.title') }}</h2>
        <div class="mt-4 space-y-4">
          <div x-data="{ count: $wire.description.length }">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="description">{{ __('portal.owner.jobs.form.description.description') }}</label>
            <textarea id="description" rows="6" maxlength="5000" wire:model.blur="description" x-on:input="count = $event.target.value.length"
                      class="{{ $fieldClass('description') }} py-3 leading-relaxed" aria-describedby="description-hint"
                      placeholder="{{ __('portal.owner.jobs.form.description.description_placeholder') }}"></textarea>
            <div class="mt-1 flex justify-between gap-3 text-sm text-zinc-500">
              <p id="description-hint">{{ __('portal.owner.jobs.form.description.description_hint') }}</p>
              <span class="shrink-0" x-text="count.toLocaleString('de-DE') + ' / 5.000'" aria-live="polite"></span>
            </div>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="requirements">{{ __('portal.owner.jobs.form.description.requirements') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.jobs.form.optional') }}</span></label>
            <textarea id="requirements" rows="4" maxlength="3000" wire:model.blur="requirements" class="{{ $fieldClass('requirements') }} py-3 leading-relaxed"
                      placeholder="{{ __('portal.owner.jobs.form.description.requirements_placeholder') }}"></textarea>
            @error('requirements') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="benefits">{{ __('portal.owner.jobs.form.description.benefits') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.jobs.form.optional') }}</span></label>
            <textarea id="benefits" rows="4" maxlength="3000" wire:model.blur="benefits" class="{{ $fieldClass('benefits') }} py-3 leading-relaxed"
                      placeholder="{{ __('portal.owner.jobs.form.description.benefits_placeholder') }}"></textarea>
            @error('benefits') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
        </div>
      </section>

      {{-- Gehalt und Frist --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-gehalt">
        <h2 id="sec-gehalt" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.jobs.form.salary.title') }}</h2>
        <div class="mt-4 space-y-4">
          <div>
            <label class="flex min-h-11 cursor-pointer items-center gap-3">
              <input type="checkbox" wire:model.live="showSalary" class="size-5 shrink-0 rounded border-zinc-300 accent-brand" aria-describedby="salary-hint">
              <span class="text-base font-medium text-zinc-900">{{ __('portal.owner.jobs.form.salary.toggle') }}</span>
            </label>
            <p id="salary-hint" class="ml-8 text-sm text-zinc-500">{{ __('portal.owner.jobs.form.salary.toggle_hint') }}</p>
          </div>

          @if($showSalary)
            <div class="grid sm:grid-cols-3 gap-3 md:gap-4">
              <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1" for="salary_min">{{ __('portal.owner.jobs.form.salary.min') }}</label>
                <input id="salary_min" type="number" wire:model.blur="salary_min" class="{{ $fieldClass('salary_min') }}" min="0" step="100" inputmode="numeric"
                       placeholder="{{ __('portal.owner.jobs.form.salary.min_placeholder') }}">
                @error('salary_min') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1" for="salary_max">{{ __('portal.owner.jobs.form.salary.max') }}</label>
                <input id="salary_max" type="number" wire:model.blur="salary_max" class="{{ $fieldClass('salary_max') }}" min="0" step="100" inputmode="numeric"
                       placeholder="{{ __('portal.owner.jobs.form.salary.max_placeholder') }}">
                @error('salary_max') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1" for="salary_type">{{ __('portal.owner.jobs.form.salary.type') }}</label>
                <select id="salary_type" wire:model="salary_type" class="{{ $fieldClass('salary_type') }}">
                  @foreach($salaryTypes as $value => $label)
                    <option value="{{ $value }}">{{ __("portal.owner.jobs.salary_types.{$value}") }}</option>
                  @endforeach
                </select>
                @error('salary_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
              </div>
            </div>
          @endif

          <div class="sm:max-w-xs">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="application_deadline">{{ __('portal.owner.jobs.form.salary.deadline') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.jobs.form.optional') }}</span></label>
            <input id="application_deadline" type="date" wire:model.blur="application_deadline" class="{{ $fieldClass('application_deadline') }}"
                   min="{{ now()->addDay()->format('Y-m-d') }}" aria-describedby="deadline-hint">
            <p id="deadline-hint" class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.jobs.form.salary.deadline_hint', ['tage' => \App\Models\Portal\Job::EXPIRES_AFTER_DAYS]) }}</p>
            @error('application_deadline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
        </div>
      </section>

      {{-- Aktionsleiste Desktop --}}
      <div class="hidden md:flex items-center justify-between gap-3 pt-2">
        <a href="{{ route('portal.owner.jobs.index') }}" class="btn-ghost">{{ __('portal.owner.jobs.form.cancel') }}</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
          <span wire:loading.remove wire:target="save">{{ $saveLabel }}</span>
          <span wire:loading wire:target="save">{{ __('portal.owner.jobs.form.saving') }}</span>
        </button>
      </div>
    </div>

    {{-- Rechte Spalte: Vorschau --}}
    <aside class="space-y-4 md:space-y-6 lg:sticky lg:top-24">
      <section class="card p-5 md:p-6" aria-labelledby="sec-vorschau">
        <h2 id="sec-vorschau" class="text-lg font-semibold text-zinc-900 flex items-center gap-2"><x-sun.icon name="eye" class="icon text-zinc-500" />{{ __('portal.owner.jobs.form.preview.title') }}</h2>
        <div class="mt-3 rounded-xl border border-zinc-200 p-4">
          <p class="font-semibold text-zinc-900 break-words" x-text="$wire.title || @js(__('portal.owner.jobs.form.preview.no_title'))"></p>
          <p class="mt-2"><span class="pill text-xs" x-text="@js($employmentLabels)[$wire.employment_type] ?? ''"></span></p>
          <template x-if="$wire.location">
            <p class="mt-2 flex items-center gap-1.5 text-sm text-zinc-500"><x-sun.icon name="map-pin" class="size-4 shrink-0" /><span x-text="$wire.location"></span></p>
          </template>
          <p class="mt-2 flex items-center gap-1.5 text-sm text-zinc-500"><x-sun.icon name="clock" class="size-4 shrink-0" />{{ __('portal.owner.jobs.form.preview.visible', ['tage' => \App\Models\Portal\Job::EXPIRES_AFTER_DAYS]) }}</p>
        </div>
        <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.jobs.form.preview.publish_hint', ['tage' => \App\Models\Portal\Job::EXPIRES_AFTER_DAYS]) }}</p>
      </section>
    </aside>
  </form>

  {{-- Aktionsleiste mobil --}}
  <div class="fixed bottom-0 inset-x-0 z-40 flex gap-2 border-t border-zinc-200 bg-white p-3 md:hidden">
    <a href="{{ route('portal.owner.jobs.index') }}" class="btn-ghost flex-1">{{ __('portal.owner.jobs.form.cancel') }}</a>
    <button type="submit" form="job-form" class="btn-primary flex-1" wire:loading.attr="disabled" wire:target="save">{{ __('portal.owner.jobs.form.save_short') }}</button>
  </div>
</div>
