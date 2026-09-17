{{--
    Lightbox fuer Profilfotos (#14), nur Alpine. Erwartet im umgebenden x-data:
    images (Liste aus {src, alt}), current (Index oder null), close(), step(richtung).
    Alpine kommt mit Livewire; das Profil bindet immer das Bewertungsformular ein.
--}}
<template x-teleport="body">
  <div x-show="current !== null" style="display: none" x-trap.inert.noscroll="current !== null"
       @keydown.escape.window="close()" @keydown.arrow-left.window="current !== null && step(-1)" @keydown.arrow-right.window="current !== null && step(1)"
       class="fixed inset-0 z-50 flex flex-col bg-zinc-900/95 motion-safe:transition-opacity" role="dialog" aria-modal="true" aria-label="{{ __('portal.profile.lightbox.label') }}">
    <div class="flex items-center justify-between gap-3 p-3 text-white">
      <span class="text-sm tabular-nums" x-text="(current ?? 0) + 1 + ' / ' + images.length"></span>
      <button type="button" @click="close()" class="inline-flex size-11 items-center justify-center rounded-full hover:bg-white/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('portal.profile.lightbox.close') }}">
        <x-sun.icon name="x" class="size-6" />
      </button>
    </div>
    <div class="relative flex min-h-0 flex-1 items-center justify-center px-3 pb-6" @click.self="close()">
      <template x-if="current !== null">
        <img :src="images[current].src" :alt="images[current].alt" class="max-h-full max-w-full rounded-xl object-contain">
      </template>
      <template x-if="images.length > 1">
        <div>
          <button type="button" @click="step(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-900 hover:bg-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('portal.profile.lightbox.prev') }}">
            <x-sun.icon name="chevron-right" class="icon rotate-180" />
          </button>
          <button type="button" @click="step(1)" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-zinc-900 hover:bg-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white" aria-label="{{ __('portal.profile.lightbox.next') }}">
            <x-sun.icon name="chevron-right" class="icon" />
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
