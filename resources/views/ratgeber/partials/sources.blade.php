{{--
    Quellen- und Aktualitaetszeile (#27). Ab dem neunten Eintrag hinter
    <details>, damit die Liste den Textabschluss nicht ueberlagert.
    Erwartet: $sources, $publishedAt, $modifiedAt.
--}}
<section class="ratgeber-sources" @if(!empty($sources)) aria-labelledby="sources-heading" @else aria-label="Aktualität dieses Beitrags" @endif>
    <p class="ratgeber-sources__dates">
        @if($publishedAt)
            Erstellt am <time datetime="{{ $publishedAt->toDateString() }}">{{ $publishedAt->translatedFormat('j. F Y') }}</time>
        @endif
        @if($publishedAt && $modifiedAt)
            <span aria-hidden="true">&middot;</span>
        @endif
        @if($modifiedAt)
            Zuletzt geprüft am <time datetime="{{ $modifiedAt->toDateString() }}">{{ $modifiedAt->translatedFormat('j. F Y') }}</time>
        @endif
    </p>

    @if(!empty($sources))
        <h2 id="sources-heading" class="ratgeber-sources__heading">Verwendete Quellen</h2>
        <ol class="ratgeber-sources__list">
            @foreach(array_slice($sources, 0, 8) as $source)
                @include('ratgeber.partials._source-item', ['source' => $source])
            @endforeach
        </ol>

        @if(count($sources) > 8)
            <details class="ratgeber-sources__more">
                <summary>Alle Quellen anzeigen</summary>
                <ol class="ratgeber-sources__list" start="9">
                    @foreach(array_slice($sources, 8) as $source)
                        @include('ratgeber.partials._source-item', ['source' => $source])
                    @endforeach
                </ol>
            </details>
        @endif
    @endif
</section>
