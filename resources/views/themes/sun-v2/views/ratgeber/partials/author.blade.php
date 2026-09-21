{{--
    Autorenbox (#27) im Theme sun-v2: Redaktionseinheit statt erfundener Person,
    Verfahren und Pruefdatum im Klartext. Gleiche Daten wie ratgeber/partials/author.
    Erwartet: $authorName, $modifiedAt, $logoUrl (optional), $authorUrl (optional).
--}}
<div class="card p-5 mt-8 flex gap-4">
  @if(!empty($logoUrl))
    <img src="{{ $logoUrl }}" alt="" width="48" height="48" loading="lazy" decoding="async" class="size-12 rounded-xl object-contain shrink-0">
  @else
    <span class="size-12 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0" aria-hidden="true"><x-sun.icon name="shield-check" class="size-6" /></span>
  @endif
  <div class="min-w-0">
    <p class="text-sm text-zinc-500">Verantwortlich für diesen Beitrag</p>
    <p class="font-semibold text-zinc-900">
      @if(!empty($authorUrl))
        <a href="{{ $authorUrl }}" rel="author" class="hover:text-brand">{{ $authorName }}</a>
      @else
        {{ $authorName }}
      @endif
    </p>
    <p class="mt-1 text-sm leading-relaxed">
      Recherche und Erstentwurf maschinell, Prüfung und Freigabe redaktionell.
      @if($modifiedAt)
        Zuletzt geprüft am <time datetime="{{ $modifiedAt->toDateString() }}">{{ $modifiedAt->translatedFormat('j. F Y') }}</time>.
      @endif
    </p>
    <a href="{{ route('portal.blog.editorial') }}" class="mt-2 inline-block text-sm text-brand hover:underline">So arbeitet unsere Redaktion</a>
  </div>
</div>
