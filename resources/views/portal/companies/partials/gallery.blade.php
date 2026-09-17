{{--
    Profil-Sektion Fotos (#14). $photos: CompanyController::$profileGallery,
    bereits auf limit(GalleryPhotos) gekuerzt (#13). Lightbox: partials/lightbox.
--}}
@php
    $images = $photos->values()->map(fn ($media, $index) => [
        'src' => $media->getUrl(),
        'alt' => __('portal.profile.gallery.alt', ['nummer' => $index + 1, 'firma' => $company->name]),
    ])->all();
@endphp
<section class="card p-5 md:p-8" aria-labelledby="fotos"
         x-data="{ images: @js($images), current: null, close() { this.current = null }, step(d) { this.current = (this.current + d + this.images.length) % this.images.length } }">
  <h2 id="fotos" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.gallery.heading') }}</h2>
  <ul class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-2 md:gap-3">
    @foreach($photos->values() as $index => $media)
      <li>
        <button type="button" @click="current = {{ $index }}" class="block w-full aspect-[16/9] overflow-hidden rounded-xl bg-zinc-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                aria-label="{{ __('portal.profile.gallery.open', ['nummer' => $index + 1]) }}">
          <img src="{{ $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl() }}" alt="{{ $images[$index]['alt'] }}" class="size-full object-cover motion-safe:transition-transform hover:scale-105" loading="lazy" width="480" height="270">
        </button>
      </li>
    @endforeach
  </ul>
  @include('portal.companies.partials.lightbox')
</section>
