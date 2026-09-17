{{--
    Profil-Sektion Video (#14). $video: CompanyController::$profileVideo (App\Support\VideoEmbed),
    nur mit Feature video_embed. Klick-zum-Laden: vor dem Klick wird nichts vom Anbieter geladen.
    Vorschaubild ist deshalb ein eigenes Bild des Betriebs (Titelbild, sonst erstes Galeriefoto),
    sonst eine neutrale Flaeche. Ohne JavaScript bleibt der Link zum Anbieter.
--}}
@php
    $poster = $company->getFirstMediaUrl('cover', 'banner') ?: ($posterFallback ?? null);
    $provider = $video->providerLabel();
@endphp
<section class="card p-5 md:p-8" aria-labelledby="video" x-data="{ playing: false }">
  <h2 id="video" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.video.heading') }}</h2>
  <div class="relative mt-4 aspect-video overflow-hidden rounded-xl bg-zinc-900">
    <template x-if="playing">
      <iframe src="{{ $video->embedUrl() }}" title="{{ __('portal.profile.video.title', ['firma' => $company->name]) }}" class="absolute inset-0 size-full"
              allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" loading="lazy"></iframe>
    </template>
    <button type="button" x-show="! playing" @click="playing = true" class="group absolute inset-0 flex size-full items-center justify-center focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand">
      @if($poster)
        <img src="{{ $poster }}" alt="" class="absolute inset-0 size-full object-cover opacity-70" loading="lazy">
      @endif
      <span class="relative inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-5 py-3 font-semibold text-zinc-900 group-hover:bg-brand-50">
        <x-sun.icon name="play" class="icon text-brand" />{{ __('portal.profile.video.play') }}
      </span>
    </button>
  </div>
  <p class="mt-3 text-sm text-zinc-500">
    {{ __('portal.profile.video.consent', ['anbieter' => $provider]) }}
    <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener nofollow" class="text-brand hover:underline">{{ __('portal.profile.video.watch', ['anbieter' => $provider]) }}</a>
  </p>
</section>
