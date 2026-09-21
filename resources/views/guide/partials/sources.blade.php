{{--
    Quellenliste (design/guide-frontend.md, §4.3). Keine Datumszeile mehr —
    die steht in der Stand-Zeile. Ab dem neunten Eintrag hinter <details>.

    Erwartet: $sources — Liste [publisher, title, url, stale_year].
--}}
@if(!empty($sources))
    <div class="ratgeber-sources" aria-labelledby="sources-heading" role="region">
        <h2 id="sources-heading" class="ratgeber-sources__heading">Verwendete Quellen</h2>
        <ol class="ratgeber-sources__list">
            @foreach(array_slice($sources, 0, 8) as $source)
                @include('guide.partials._source-item', ['source' => $source])
            @endforeach
        </ol>

        @if(count($sources) > 8)
            <details class="ratgeber-sources__more">
                <summary>Alle Quellen anzeigen ({{ count($sources) }})</summary>
                <ol class="ratgeber-sources__list" start="9">
                    @foreach(array_slice($sources, 8) as $source)
                        @include('guide.partials._source-item', ['source' => $source])
                    @endforeach
                </ol>
            </details>
        @endif
    </div>
@endif
