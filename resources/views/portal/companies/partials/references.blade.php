{{--
    Profil-Sektion Referenzen (#14). $references: CompanyController::$profileReferences,
    nur mit Feature references und auf das Limit gekuerzt (#13).
    Grid mit Karten; Klick aufs Foto oeffnet die Lightbox mit allen Fotos der Referenz.
--}}
@php
    $photoCollection = \App\Models\Portal\CompanyReference::PHOTO_COLLECTION;
@endphp
<section class="card p-5 md:p-8" aria-labelledby="referenzen"
         x-data="{ images: [], current: null, open(images) { this.images = images; this.current = 0 }, close() { this.current = null }, step(d) { this.current = (this.current + d + this.images.length) % this.images.length } }">
  <h2 id="referenzen" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.references.heading') }}</h2>
  <ul class="mt-4 grid gap-4 sm:grid-cols-2">
    @foreach($references as $reference)
      @php
          $photos = $reference->getMedia($photoCollection)->values();
          $images = $photos->map(fn ($media, $index) => [
              'src' => $media->getUrl(),
              'alt' => __('portal.profile.references.alt', ['titel' => $reference->title, 'nummer' => $index + 1]),
          ])->all();
          $meta = collect([$reference->location, $reference->year])->filter()->implode(', ');
      @endphp
      <li class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200">
        @if($photos->isNotEmpty())
          @php $cover = $photos->first(); @endphp
          <button type="button" @click="open(@js($images))" class="relative block aspect-[16/9] w-full bg-zinc-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand"
                  aria-label="{{ __('portal.profile.references.open', ['titel' => $reference->title]) }}">
            <img src="{{ $cover->hasGeneratedConversion('card') ? $cover->getUrl('card') : $cover->getUrl() }}" alt="{{ $images[0]['alt'] }}" class="size-full object-cover" loading="lazy" width="480" height="270">
            @if($photos->count() > 1)
              <span class="pill absolute bottom-2 right-2 bg-white text-xs"><x-sun.icon name="image" class="size-4" />{{ trans_choice('portal.profile.references.photos', $photos->count(), ['anzahl' => $photos->count()]) }}</span>
            @endif
          </button>
        @endif
        <div class="flex flex-1 flex-col gap-1 p-4">
          <h3 class="text-lg font-semibold text-zinc-900 break-words">{{ $reference->title }}</h3>
          @if($meta !== '')
            <p class="text-sm text-zinc-500 flex items-center gap-1.5"><x-sun.icon name="map-pin" class="size-4 shrink-0" stroke-linecap="butt" stroke-linejoin="miter" />{{ $meta }}</p>
          @endif
          @if($reference->description)
            <p class="mt-1 text-base leading-relaxed text-zinc-700">{!! nl2br(e($reference->description)) !!}</p>
          @endif
        </div>
      </li>
    @endforeach
  </ul>
  @include('portal.companies.partials.lightbox')
</section>
