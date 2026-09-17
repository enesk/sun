{{--
    Galerie im Betriebsbereich, Theme sun-v2 (#13, Drag-Sort #14).
    Kein eigenes Livewire-Objekt: wird in livewire/portal/dashboard/profile-edit-form eingebunden
    und nutzt dessen Zustand ($existingGallery, $galleryLimit, $gallerySlots, reorderGallery()).
    Sortieren per Ziehen (Alpine, HTML5 Drag & Drop) und per Pfeilknoepfen (Touch, Tastatur).
    Fotos ueber dem Limit bleiben gespeichert und sind als "Nicht sichtbar" markiert.
--}}
@php
    $galleryIds = array_map(fn (array $image): int => (int) $image['id'], $existingGallery);
    $swapOrder = function (int $from, int $to) use ($galleryIds): array {
        $order = $galleryIds;
        [$order[$from], $order[$to]] = [$order[$to], $order[$from]];

        return $order;
    };
@endphp
<section class="card p-5 md:p-6" aria-labelledby="sec-fotos">
  <h2 id="sec-fotos" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.edit.gallery.title') }} <span class="text-base font-normal text-zinc-500">{{ $galleryLimit === null ? count($existingGallery) : __('portal.owner.edit.gallery.count', ['anzahl' => count($existingGallery), 'max' => $galleryLimit]) }}</span></h2>
  @if(count($existingGallery) > 0)
    @if(count($existingGallery) > 1)
      <p class="mt-1 text-sm text-zinc-500">{{ $galleryLimit === null ? __('portal.owner.edit.gallery.sort_hint') : __('portal.owner.edit.gallery.sort_hint_limit', ['max' => $galleryLimit]) }}</p>
    @endif
    <ul class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2"
        x-data="{
          dragging: null,
          over: null,
          drop(targetId) {
            if (this.dragging === null || this.dragging === targetId) { this.dragging = this.over = null; return; }
            const ids = [...this.$el.querySelectorAll('[data-gallery-id]')].map((item) => Number(item.dataset.galleryId));
            const from = ids.indexOf(this.dragging);
            ids.splice(from, 1);
            ids.splice(ids.indexOf(targetId) + (from <= ids.indexOf(targetId) ? 1 : 0), 0, this.dragging);
            this.dragging = this.over = null;
            this.$wire.reorderGallery(ids);
          },
        }">
      @foreach($existingGallery as $index => $image)
        <li class="relative aspect-square overflow-hidden rounded-xl bg-zinc-100 {{ count($existingGallery) > 1 ? 'cursor-grab active:cursor-grabbing' : '' }}"
            wire:key="gallery-{{ $image['id'] }}" data-gallery-id="{{ $image['id'] }}"
            @if(count($existingGallery) > 1)
              draggable="true"
              @dragstart="dragging = {{ $image['id'] }}; $event.dataTransfer.effectAllowed = 'move'"
              @dragend="dragging = over = null"
              @dragover.prevent="over = {{ $image['id'] }}"
              @dragleave="over === {{ $image['id'] }} && (over = null)"
              @drop.prevent="drop({{ $image['id'] }})"
              :class="{ 'opacity-50': dragging === {{ $image['id'] }}, 'ring-2 ring-brand ring-offset-2': over === {{ $image['id'] }} && dragging !== {{ $image['id'] }} }"
            @endif>
          <img src="{{ $image['url'] }}" alt="{{ __('portal.owner.edit.gallery.position', ['nummer' => $index + 1]) }}" class="pointer-events-none size-full object-cover {{ $image['visible'] ? '' : 'opacity-40' }}" loading="lazy">
          @if($image['visible'])
            <span class="pill absolute top-1 left-1 bg-white text-xs tabular-nums">{{ $index + 1 }}</span>
          @else
            <span class="pill absolute top-1 left-1 bg-white text-xs"><x-sun.icon name="lock" class="size-3.5 shrink-0" />{{ __('portal.owner.edit.gallery.hidden') }}</span>
          @endif
          <button type="button" wire:click="removeGalleryImage({{ $image['id'] }})" wire:confirm="{{ __('portal.owner.edit.gallery.confirm_delete') }}"
                  class="absolute top-1 right-1 inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-700 hover:text-red-600 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                  aria-label="{{ __('portal.owner.edit.gallery.delete') }}">
            <x-sun.icon name="x" class="icon" />
          </button>
          @if(count($existingGallery) > 1)
            <div class="absolute bottom-1 inset-x-1 flex justify-between">
              @if(! $loop->first)
                <button type="button" wire:click="reorderGallery({{ json_encode($swapOrder($index, $index - 1)) }})"
                        class="inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-700 hover:text-brand focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                        aria-label="{{ __('portal.owner.edit.gallery.move_left', ['nummer' => $index + 1]) }}">
                  <x-sun.icon name="chevron-right" class="icon rotate-180" />
                </button>
              @else
                <span></span>
              @endif
              @unless($loop->last)
                <button type="button" wire:click="reorderGallery({{ json_encode($swapOrder($index, $index + 1)) }})"
                        class="inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-700 hover:text-brand focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
                        aria-label="{{ __('portal.owner.edit.gallery.move_right', ['nummer' => $index + 1]) }}">
                  <x-sun.icon name="chevron-right" class="icon" />
                </button>
              @endunless
            </div>
          @endif
        </li>
      @endforeach
    </ul>
    @if(collect($existingGallery)->contains('visible', false))
      <p class="mt-3 text-sm text-zinc-700">{{ __('portal.owner.edit.gallery.hidden_hint', ['max' => (int) $galleryLimit]) }} <a href="{{ route('portal.owner.premium') }}" class="font-medium text-brand hover:underline">{{ __('portal.owner.edit.locked.unlock') }}</a></p>
    @endif
  @endif
  @if($gallerySlots === null || $gallerySlots > 0)
    <label for="gallery-upload" class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-6 text-center transition-colors hover:border-brand focus-within:ring-2 focus-within:ring-brand">
      <x-sun.icon name="upload" class="icon text-zinc-400" />
      <span class="mt-2 text-base font-medium text-zinc-900">{{ __('portal.owner.edit.gallery.upload') }}</span>
      @if($gallerySlots !== null)
        <span class="text-sm text-zinc-500">{{ __('portal.owner.edit.gallery.hint', ['anzahl' => $gallerySlots]) }}</span>
      @endif
      <input type="file" id="gallery-upload" wire:model="galleryUploads" class="sr-only" accept="image/jpeg,image/png,image/webp" @if($gallerySlots === null || $gallerySlots > 1) multiple @endif>
    </label>
    <p class="mt-1 text-sm text-brand-700" wire:loading wire:target="galleryUploads">{{ __('portal.owner.edit.uploading') }}</p>
  @else
    <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.edit.gallery.full', ['max' => (int) $galleryLimit]) }}</p>
    <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-3 w-full sm:w-auto"><x-sun.icon name="lock" class="size-4 shrink-0" />{{ __('portal.owner.edit.gallery.more_cta') }}</a>
  @endif
  @error('galleryUploads') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
  @error('galleryUploads.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</section>
