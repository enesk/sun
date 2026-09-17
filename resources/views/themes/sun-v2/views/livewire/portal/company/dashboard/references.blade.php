{{--
    Projektreferenzen im Betriebsbereich, Theme sun-v2 (#13).
    Logik: App\Livewire\Portal\Company\Dashboard\References. Texte: portal.owner.references.*
    Anzahl ($limit) und Fotos je Referenz ($photosMax) kommen aus dem Entitlement-Service bzw. config/premium.php.
--}}
<section id="referenzen" class="mt-4 md:mt-6 card p-5 md:p-6" aria-labelledby="sec-referenzen">
  <h2 id="sec-referenzen" class="text-2xl font-semibold text-zinc-900 flex flex-wrap items-center gap-2">
    {{ __('portal.owner.references.title') }}
    @if($allowed && $limit !== null)
      <span class="text-base font-normal text-zinc-500">{{ __('portal.owner.references.count', ['anzahl' => $references->count(), 'max' => $limit]) }}</span>
    @endif
    @unless($allowed)
      <span class="pill-brand text-xs"><x-sun.icon name="lock" class="size-3.5 shrink-0" />{{ __('portal.owner.edit.locked.badge') }}</span>
    @endunless
  </h2>

  @unless($allowed)
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.references.locked') }}</p>
    @if($references->isNotEmpty())
      <p class="mt-1 text-sm text-zinc-700">{{ trans_choice('portal.owner.references.stored', $references->count(), ['anzahl' => $references->count()]) }}</p>
    @endif
    <a href="{{ route('portal.owner.premium') }}" class="mt-4 btn-secondary"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.edit.locked.unlock') }}</a>
  @else
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.references.intro') }}</p>
  @endunless

  @if($references->isNotEmpty())
    <ul class="mt-4 space-y-3">
      @foreach($references as $reference)
        <li class="rounded-xl border border-zinc-200 p-4" wire:key="reference-{{ $reference->id }}">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="font-semibold text-zinc-900 break-words">{{ $reference->title }}</p>
              <p class="text-sm text-zinc-500">{{ collect([$reference->location, $reference->year])->filter()->implode(' · ') }}</p>
            </div>
            <div class="flex flex-wrap gap-1">
              @if($allowed)
                <button type="button" wire:click="move({{ $reference->id }}, -1)" class="btn-ghost px-3" @disabled($loop->first) aria-label="{{ __('portal.owner.references.move_up', ['titel' => $reference->title]) }}">↑</button>
                <button type="button" wire:click="move({{ $reference->id }}, 1)" class="btn-ghost px-3" @disabled($loop->last) aria-label="{{ __('portal.owner.references.move_down', ['titel' => $reference->title]) }}">↓</button>
                <button type="button" wire:click="edit({{ $reference->id }})" class="btn-secondary px-4"><x-sun.icon name="pencil" class="icon" />{{ __('portal.owner.references.edit') }}</button>
              @endif
              <button type="button" wire:click="delete({{ $reference->id }})" wire:confirm="{{ __('portal.owner.references.confirm_delete') }}" class="btn-ghost text-zinc-600 hover:bg-zinc-100">{{ __('portal.owner.edit.remove') }}</button>
            </div>
          </div>
          @php $referencePhotos = $reference->getMedia(\App\Models\Portal\CompanyReference::PHOTO_COLLECTION); @endphp
          @if($referencePhotos->isNotEmpty())
            <ul class="mt-3 grid grid-cols-5 gap-2">
              @foreach($referencePhotos as $photo)
                <li class="relative aspect-square overflow-hidden rounded-lg bg-zinc-100" wire:key="reference-photo-{{ $photo->id }}">
                  <img src="{{ $photo->getUrl('thumb') }}" alt="" class="size-full object-cover" loading="lazy">
                  <button type="button" wire:click="removePhoto({{ $reference->id }}, {{ $photo->id }})" wire:confirm="{{ __('portal.owner.edit.gallery.confirm_delete') }}"
                          class="absolute top-0.5 right-0.5 inline-flex size-8 items-center justify-center rounded-full bg-white/90 text-zinc-700 hover:text-red-600"
                          aria-label="{{ __('portal.owner.edit.gallery.delete') }}"><x-sun.icon name="x" class="size-4" /></button>
                </li>
              @endforeach
            </ul>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  @if($allowed)
    @if($showForm)
      <form wire:submit="save" class="mt-4 flex flex-col gap-4 rounded-xl bg-zinc-50 p-4">
        <div>
          <label for="reference-title" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.references.fields.title') }}</label>
          <input id="reference-title" type="text" wire:model="title" class="input" maxlength="255">
          @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
          <label for="reference-description" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.references.fields.description') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
          <textarea id="reference-description" wire:model="description" rows="4" class="input" maxlength="3000"></textarea>
          @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-[1fr_8rem] gap-3">
          <div>
            <label for="reference-location" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.references.fields.location') }} <span class="font-normal text-zinc-500">{{ __('portal.owner.edit.optional') }}</span></label>
            <input id="reference-location" type="text" wire:model="location" class="input" maxlength="255">
            @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
          <div>
            <label for="reference-year" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.references.fields.year') }}</label>
            <input id="reference-year" type="number" inputmode="numeric" wire:model="year" class="input" min="1900" max="{{ date('Y') + 1 }}">
            @error('year')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
        </div>
        @php $photoSlots = $photosMax - ($editing?->getMedia(\App\Models\Portal\CompanyReference::PHOTO_COLLECTION)->count() ?? 0); @endphp
        @if($photoSlots > 0)
          <div>
            <label for="reference-photos" class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-zinc-300 p-4 text-center cursor-pointer hover:border-brand focus-within:ring-2 focus-within:ring-brand">
              <x-sun.icon name="upload" class="icon text-zinc-400" />
              <span class="font-medium text-zinc-900">{{ __('portal.owner.references.fields.photos') }}</span>
              <span class="text-sm text-zinc-500">{{ __('portal.owner.references.photos_hint', ['anzahl' => $photoSlots]) }}</span>
              <input id="reference-photos" type="file" wire:model="photos" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple>
            </label>
            <p wire:loading wire:target="photos" class="mt-1 text-sm text-brand-700">{{ __('portal.owner.edit.uploading') }}</p>
            @if(count($photos) > 0)
              <p class="mt-1 text-sm text-zinc-700">{{ trans_choice('portal.owner.references.photos_selected', count($photos), ['anzahl' => count($photos)]) }}</p>
            @endif
            @error('photos')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('photos.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
          </div>
        @endif
        <div class="flex flex-wrap gap-2">
          <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,photos">{{ __('portal.owner.references.save') }}</button>
          <button type="button" wire:click="cancel" class="btn-ghost">{{ __('portal.owner.references.cancel') }}</button>
        </div>
      </form>
    @elseif($canAdd)
      <button type="button" wire:click="create" class="mt-4 btn-secondary"><x-sun.icon name="plus" class="icon" />{{ __('portal.owner.references.add') }}</button>
    @else
      <p class="mt-4 text-sm text-zinc-500">{{ __('portal.owner.references.limit_reached', ['max' => (int) $limit]) }}</p>
    @endif
  @endif
</section>
