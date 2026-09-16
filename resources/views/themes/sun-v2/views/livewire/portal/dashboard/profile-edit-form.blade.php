{{--
    Formular "Profil bearbeiten" im Theme sun-v2 (Vorlage profil-bearbeiten-elektrikerportal.html).
    Logik unveraendert in App\Livewire\Portal\Dashboard\ProfileEditForm.

    Abweichungen von der Vorlage:
    - Beschreibung bleibt fuer alle bearbeitbar, wie bisher im Code. Premium-gesperrt sind nur
      Titelbild und Fotos (so prueft es save()).
    - "Leistungen & Oeffnungszeiten" fehlt: dafuer gibt es im Formular keine Felder.
    - Ort ist die vorhandene Stadtsuche (city_id), kein freies Textfeld.
    - "Noch offen" rechnet mit denselben acht Feldern wie die Uebersicht, live aus dem Formular.
--}}
@php
    $ownerIsPremium = (bool) \App\Models\Portal\Company::ownedBy(auth()->id())->value('is_premium');
    $checklist = [
        'name' => filled($name),
        'description' => filled($description),
        'street' => filled($street),
        'tel' => filled($tel),
        'email' => filled($email),
        'website' => filled($website),
        'logo' => filled($currentLogo) || filled($logo),
        'categories' => count($selectedCategories) > 0,
    ];
    $anchors = ['name' => 'name', 'description' => 'description', 'street' => 'street', 'tel' => 'tel', 'email' => 'email', 'website' => 'website', 'logo' => 'logo-upload', 'categories' => 'kategorien'];
    $done = count(array_filter($checklist));
    $fieldClass = fn (string $field) => 'input'.($errors->has($field) ? ' border-red-600' : '');
@endphp
<div class="pb-24 md:pb-0">
  @if($saved)
    <p class="card mt-6 p-4 text-base text-emerald-700 flex items-center gap-2" role="status">
      <x-sun.icon name="check" class="icon" />{{ __('portal.owner.edit.saved') }}
    </p>
  @endif

  <form wire:submit="save" id="profil-form" class="mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">

    <div class="space-y-4 md:space-y-6 min-w-0">

      {{-- Betrieb --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-betrieb">
        <h2 id="sec-betrieb" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.company.title') }}</h2>
        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="name">{{ __('portal.owner.edit.company.name') }}</label>
            <input id="name" type="text" wire:model.blur="name" class="{{ $fieldClass('name') }}" aria-describedby="name-hint">
            <p id="name-hint" class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.edit.company.name_hint') }}</p>
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div x-data="{ count: $wire.description.length }">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="description">{{ __('portal.owner.edit.company.description') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
            {{-- Markdown-Editor (js/modules/md-editor.js); wire:ignore haelt Livewire vom Editor fern, das Textfeld bleibt die Quelle.
                 Der Startwert steht im Textfeld, damit der Editor nicht vor Livewire leer startet. --}}
            <div wire:ignore>
              <textarea id="description" rows="8" maxlength="5000" wire:model="description" x-on:input="count = $event.target.value.length"
                        class="{{ $fieldClass('description') }} py-3 leading-relaxed" aria-describedby="description-hint"
                        placeholder="{{ __('portal.owner.edit.company.description_placeholder') }}"
                        data-md-editor="{{ json_encode(__('portal.owner.edit.company.editor')) }}"
                        data-md-icons="{{ \App\Themes\SunV2\Asset::url('images/icons.svg') }}">{{ $description }}</textarea>
            </div>
            <div class="mt-1 flex justify-between gap-3 text-sm text-zinc-500">
              <p id="description-hint">{{ __('portal.owner.edit.company.description_hint') }}</p>
              <span class="shrink-0" x-text="count.toLocaleString('de-DE') + ' / 5.000'"></span>
            </div>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div id="kategorien">
            <p class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.edit.company.categories') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.company.categories_count', ['anzahl' => count($selectedCategories)]) }}</span></p>
            <div class="flex flex-wrap gap-2">
              @foreach($categories as $category)
                @php
                    $isSelected = in_array($category->id, $selectedCategories);
                    $isDisabled = ! $isSelected && count($selectedCategories) >= 5;
                @endphp
                <button type="button" wire:click="toggleCategory({{ $category->id }})" @disabled($isDisabled)
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        class="inline-flex min-h-11 items-center gap-1 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 md:min-h-0 md:py-1 md:px-3 {{ $isSelected ? 'border-brand bg-brand-50 text-brand-700' : ($isDisabled ? 'border-zinc-200 bg-white text-zinc-400 cursor-not-allowed' : 'border-zinc-200 bg-white text-zinc-700 hover:border-brand hover:text-brand') }}">
                  <x-sun.icon :name="$isSelected ? 'check' : 'plus'" class="size-4 shrink-0" />{{ $category->name }}
                </button>
              @endforeach
            </div>
            <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.edit.company.categories_hint') }}</p>
            @error('selectedCategories') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>

          <div>
            <p class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.edit.company.logo') }}</p>
            <div class="flex items-center gap-4">
              <div class="size-20 shrink-0 overflow-hidden rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center text-2xl font-bold">
                @if($logo && $this->logoPreviewUrl)
                  <img src="{{ $this->logoPreviewUrl }}" alt="{{ __('portal.owner.edit.company.logo_preview') }}" class="size-full object-cover">
                @elseif($currentLogo)
                  <img src="{{ $currentLogo }}" alt="{{ __('portal.owner.edit.company.logo_alt', ['firma' => $name]) }}" class="size-full object-cover">
                @else
                  <span aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 2)) }}</span>
                @endif
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap gap-2">
                  <label for="logo-upload" class="btn-secondary cursor-pointer focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2">
                    <x-sun.icon name="upload" class="icon" />{{ __('portal.owner.edit.company.logo_upload') }}
                    <input type="file" id="logo-upload" wire:model="logo" class="sr-only" accept="image/jpeg,image/png,image/webp">
                  </label>
                  @if($currentLogo && ! $logo)
                    <button type="button" wire:click="removeLogo" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ __('portal.owner.edit.remove') }}</button>
                  @endif
                </div>
                <p class="mt-1 text-sm text-zinc-500" wire:loading.remove wire:target="logo">{{ __('portal.owner.edit.company.logo_hint') }}</p>
                <p class="mt-1 text-sm text-brand-700" wire:loading wire:target="logo">{{ __('portal.owner.edit.uploading') }}</p>
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
              </div>
            </div>
          </div>
        </div>
      </section>

      {{-- Adresse --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-adresse">
        <h2 id="sec-adresse" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.address.title') }}</h2>
        <div class="mt-4 grid grid-cols-[1fr_6rem] gap-3 md:gap-4">
          <div class="min-w-0">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="street">{{ __('portal.owner.edit.address.street') }}</label>
            <input id="street" type="text" wire:model.blur="street" class="{{ $fieldClass('street') }}" autocomplete="address-line1">
          </div>
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="house_no">{{ __('portal.owner.edit.address.house_no') }}</label>
            <input id="house_no" type="text" wire:model.blur="house_no" class="{{ $fieldClass('house_no') }}">
          </div>
        </div>
        @error('street') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('house_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="mt-4 grid grid-cols-[7rem_1fr] gap-3 md:gap-4">
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="zipcode">{{ __('portal.owner.edit.address.zipcode') }}</label>
            <input id="zipcode" type="text" wire:model.blur="zipcode" class="{{ $fieldClass('zipcode') }}" inputmode="numeric" maxlength="5" autocomplete="postal-code">
          </div>
          <div class="relative min-w-0" x-data="{ open: false }" @click.outside="open = false">
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="city-search">{{ __('portal.owner.edit.address.city') }}</label>
            <input id="city-search" type="text" wire:model.live.debounce.300ms="citySearch" @focus="open = true" @input="open = true"
                   class="{{ $fieldClass('city_id') }} pr-11" role="combobox" aria-controls="city-listbox" autocomplete="off"
                   :aria-expanded="open && {{ count($citySuggestions) }} > 0 ? 'true' : 'false'"
                   placeholder="{{ __('portal.owner.edit.address.city_placeholder') }}">
            <span class="pointer-events-none absolute right-3 bottom-3">
              @if($city_id)
                <x-sun.icon name="check" class="icon text-emerald-600" />
              @else
                <x-sun.icon name="search" class="icon text-zinc-400" />
              @endif
            </span>
            @if(count($citySuggestions) > 0)
              <ul x-show="open" x-cloak id="city-listbox" role="listbox" class="absolute z-10 mt-1 w-full max-h-60 overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg">
                @foreach($citySuggestions as $suggestion)
                  <li role="option" wire:click="selectCity({{ $suggestion['id'] }})" @click="open = false"
                      class="cursor-pointer px-4 py-2 text-base text-zinc-700 hover:bg-zinc-50">{{ $suggestion['zipcode'] }} {{ $suggestion['name'] }}</li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
        @error('zipcode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('city_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.edit.address.hint') }}</p>
      </section>

      {{-- Kontakt --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-kontakt">
        <h2 id="sec-kontakt" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.contact.title') }}</h2>
        <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.edit.contact.intro') }}</p>
        <div class="mt-4 grid sm:grid-cols-2 gap-3 md:gap-4">
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="tel">{{ __('portal.owner.edit.contact.tel') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
            <input id="tel" type="tel" wire:model.blur="tel" class="{{ $fieldClass('tel') }}" autocomplete="tel">
            @error('tel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
          <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" for="email">{{ __('portal.owner.edit.contact.email') }}</label>
            <input id="email" type="email" wire:model.blur="email" class="{{ $fieldClass('email') }}" placeholder="info@firma.de" autocomplete="email" @error('email') aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
        </div>
        <div class="mt-4">
          <label class="block text-sm font-medium text-zinc-700 mb-1" for="website">{{ __('portal.owner.edit.contact.website') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
          <input id="website" type="url" wire:model.blur="website" class="{{ $fieldClass('website') }}" placeholder="https://www.firma.de" autocomplete="url">
          @error('website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
      </section>

      {{-- Titelbild und Fotos: mit Premium bearbeitbar, sonst sichtbar gesperrt --}}
      @if($ownerIsPremium)
        <section class="card p-5 md:p-6" aria-labelledby="sec-titelbild">
          <h2 id="sec-titelbild" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.cover.title') }}</h2>
          <div class="mt-4 aspect-[3/1] overflow-hidden rounded-xl bg-zinc-100 flex items-center justify-center">
            @if($cover && $this->coverPreviewUrl)
              <img src="{{ $this->coverPreviewUrl }}" alt="{{ __('portal.owner.edit.cover.preview') }}" class="size-full object-cover">
            @elseif($currentCoverUrl)
              <img src="{{ $currentCoverUrl }}" alt="{{ __('portal.owner.edit.cover.alt', ['firma' => $name]) }}" class="size-full object-cover">
            @else
              <span class="px-4 text-center text-sm text-zinc-500">{{ __('portal.owner.edit.cover.empty') }}</span>
            @endif
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <label for="cover-upload" class="btn-secondary cursor-pointer focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2">
              <x-sun.icon name="upload" class="icon" />{{ __('portal.owner.edit.cover.upload') }}
              <input type="file" id="cover-upload" wire:model="cover" class="sr-only" accept="image/jpeg,image/png,image/webp">
            </label>
            @if($currentCoverUrl && ! $cover)
              <button type="button" wire:click="removeCover" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ __('portal.owner.edit.remove') }}</button>
            @endif
          </div>
          <p class="mt-1 text-sm text-zinc-500" wire:loading.remove wire:target="cover">{{ __('portal.owner.edit.cover.hint') }}</p>
          <p class="mt-1 text-sm text-brand-700" wire:loading wire:target="cover">{{ __('portal.owner.edit.uploading') }}</p>
          @error('cover') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <section class="card p-5 md:p-6" aria-labelledby="sec-fotos">
          <h2 id="sec-fotos" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.gallery.title') }} <span class="text-base font-normal text-zinc-500">{{ __('portal.owner.edit.gallery.count', ['anzahl' => count($existingGallery)]) }}</span></h2>
          @if(count($existingGallery) > 0)
            <ul class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-2">
              @foreach($existingGallery as $image)
                <li class="relative aspect-square overflow-hidden rounded-xl bg-zinc-100" wire:key="gallery-{{ $image['id'] }}">
                  <img src="{{ $image['url'] }}" alt="" class="size-full object-cover" loading="lazy">
                  <button type="button" wire:click="removeGalleryImage({{ $image['id'] }})" wire:confirm="{{ __('portal.owner.edit.gallery.confirm_delete') }}"
                          class="absolute top-1 right-1 inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-700 hover:text-red-600 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                          aria-label="{{ __('portal.owner.edit.gallery.delete') }}">
                    <x-sun.icon name="x" class="icon" />
                  </button>
                </li>
              @endforeach
            </ul>
          @endif
          @if(count($existingGallery) < 20)
            <label for="gallery-upload" class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-6 text-center transition-colors hover:border-brand focus-within:ring-2 focus-within:ring-brand">
              <x-sun.icon name="upload" class="icon text-zinc-400" />
              <span class="mt-2 text-base font-medium text-zinc-900">{{ __('portal.owner.edit.gallery.upload') }}</span>
              <span class="text-sm text-zinc-500">{{ __('portal.owner.edit.gallery.hint', ['anzahl' => 20 - count($existingGallery)]) }}</span>
              <input type="file" id="gallery-upload" wire:model="galleryUploads" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
            <p class="mt-1 text-sm text-brand-700" wire:loading wire:target="galleryUploads">{{ __('portal.owner.edit.uploading') }}</p>
          @else
            <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.edit.gallery.full') }}</p>
          @endif
          @error('galleryUploads') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
          @error('galleryUploads.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
      @else
        <section class="card border-brand-200 p-5 md:p-6" aria-labelledby="sec-fotos-gesperrt">
          <p class="pill-brand mb-3"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</p>
          <h2 id="sec-fotos-gesperrt" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.locked.photos_title') }}</h2>
          <div class="mt-4 aspect-[3/1] rounded-xl bg-zinc-100" aria-hidden="true"></div>
          <div class="mt-2 grid grid-cols-3 sm:grid-cols-4 gap-2" aria-hidden="true">
            <div class="aspect-square rounded-xl bg-zinc-100"></div><div class="aspect-square rounded-xl bg-zinc-100"></div><div class="aspect-square rounded-xl bg-zinc-100"></div><div class="aspect-square rounded-xl bg-zinc-100 hidden sm:block"></div>
          </div>
          <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.edit.locked.photos_hint') }}</p>
          @if(count($existingGallery) > 0)
            <p class="mt-1 text-sm text-zinc-700">{{ trans_choice('portal.owner.edit.locked.photos_stored', count($existingGallery), ['anzahl' => count($existingGallery)]) }}</p>
          @endif
          <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-4 w-full sm:w-auto"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.unlock') }}</a>
        </section>
      @endif

      {{-- Aktionsleiste Desktop --}}
      <div class="hidden md:flex items-center justify-between gap-3 pt-2">
        <a href="{{ route('portal.owner.edit') }}" class="btn-ghost">{{ __('portal.owner.edit.discard') }}</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
          <span wire:loading.remove wire:target="save">{{ __('portal.owner.edit.save') }}</span>
          <span wire:loading wire:target="save">{{ __('portal.owner.edit.saving') }}</span>
        </button>
      </div>
    </div>

    {{-- Rechte Spalte --}}
    <aside class="space-y-4 md:space-y-6 lg:sticky lg:top-24">
      @unless($ownerIsPremium)
        <section class="rounded-2xl bg-brand-50 p-5 md:p-6" aria-labelledby="sec-premium">
          <p class="pill-brand bg-white"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</p>
          <h2 id="sec-premium" class="mt-3 text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.premium.title') }}</h2>
          <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.edit.premium.text') }}</p>
          <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-4 w-full">{{ __('portal.owner.overview.premium.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('portal.owner.overview.premium.note') }}</p>
        </section>
      @endunless

      <section class="card p-5 md:p-6" aria-labelledby="sec-offen">
        <h2 id="sec-offen" class="text-lg font-semibold text-zinc-900">{{ $done === count($checklist) ? __('portal.owner.edit.checklist.done') : __('portal.owner.edit.checklist.title') }}</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.edit.checklist.progress', ['erledigt' => $done, 'gesamt' => count($checklist)]) }}</p>
        <ul class="mt-3 space-y-2 text-base">
          @foreach(collect($checklist)->sortDesc() as $key => $filled)
            @if($filled)
              <li class="flex items-center gap-2 text-zinc-500"><x-sun.icon name="check" class="icon text-emerald-600" />{{ __("portal.owner.overview.completion.fields.{$key}.label") }}</li>
            @else
              <li><a href="#{{ $anchors[$key] }}" class="flex min-h-11 items-center gap-2 font-medium text-zinc-900 hover:text-brand md:min-h-0"><x-sun.icon name="circle" class="icon text-zinc-300" />{{ __("portal.owner.overview.completion.fields.{$key}.label") }}</a></li>
            @endif
          @endforeach
        </ul>
      </section>

      <section class="card p-5 md:p-6" aria-labelledby="sec-wissen">
        <h2 id="sec-wissen" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.edit.tips.title') }}</h2>
        <ul class="mt-2 space-y-2 text-base text-zinc-700">
          @foreach(__('portal.owner.edit.tips.items') as $tip)
            <li>{{ $tip }}</li>
          @endforeach
        </ul>
      </section>
    </aside>
  </form>

  {{-- Aktionsleiste mobil --}}
  <div class="fixed bottom-0 inset-x-0 z-40 flex gap-2 border-t border-zinc-200 bg-white p-3 md:hidden">
    <a href="{{ route('portal.owner.edit') }}" class="btn-ghost flex-1">{{ __('portal.owner.edit.discard_short') }}</a>
    <button type="submit" form="profil-form" class="btn-primary flex-1" wire:loading.attr="disabled" wire:target="save">{{ __('portal.owner.edit.save_short') }}</button>
  </div>
</div>
