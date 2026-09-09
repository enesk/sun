{{--
    Autorenbox (#27): Redaktionseinheit statt erfundener Person, Verfahren und
    Pruefdatum im Klartext.
    Erwartet: $authorName, $modifiedAt, $logoUrl (optional),
    $authorUrl (optional, verlinkt auf die Autorenseite /autor/<slug>).
--}}
<div class="ratgeber-author">
    <div class="ratgeber-author__mark" aria-hidden="true">
        @if(!empty($logoUrl))
            <img src="{{ $logoUrl }}" alt="" width="56" height="56" loading="lazy" decoding="async">
        @else
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
            </svg>
        @endif
    </div>
    <div class="ratgeber-author__body">
        <p class="ratgeber-author__kicker">Verantwortlich für diesen Beitrag</p>
        <p class="ratgeber-author__name">
            @if(!empty($authorUrl))
                <a href="{{ $authorUrl }}" rel="author">{{ $authorName }}</a>
            @else
                {{ $authorName }}
            @endif
        </p>
        <p class="ratgeber-author__text">
            Recherche und Erstentwurf maschinell, Prüfung und Freigabe redaktionell.
            @if($modifiedAt)
                Zuletzt geprüft am <time datetime="{{ $modifiedAt->toDateString() }}">{{ $modifiedAt->translatedFormat('j. F Y') }}</time>.
            @endif
        </p>
        <a href="{{ route('portal.blog.editorial') }}" class="ratgeber-author__link">So arbeitet unsere Redaktion</a>
    </div>
</div>
