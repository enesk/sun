{{--
    Changelog „Was ist neu?“ (design/guide-frontend.md, §4.2). Die drei
    juengsten Eintraege offen, aeltere hinter <details> — ohne JavaScript
    bedienbar. Leer: rendert nichts, auch keine Ueberschrift.

    Erwartet: $changelog — Liste [at (Carbon), text, source: ?[label, url]],
    juengster zuerst, hoechstens 20.
--}}
@if(!empty($changelog))
    @php
        $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
        $visibleEntries = array_slice($changelog, 0, 3);
        $olderEntries = array_slice($changelog, 3);
    @endphp
    <section class="ratgeber-news" aria-labelledby="was-ist-neu">
        <h2 id="was-ist-neu" class="ratgeber-news__heading">Was ist neu?</h2>

        <ul class="ratgeber-news__list" role="list">
            @foreach($visibleEntries as $entry)
                @include('guide.partials._changelog-entry', ['entry' => $entry, 'tz' => $tz])
            @endforeach
        </ul>

        @if($olderEntries !== [])
            <details class="ratgeber-news__more">
                <summary>Frühere Änderungen ({{ count($olderEntries) }})</summary>
                <ul class="ratgeber-news__list" role="list">
                    @foreach($olderEntries as $entry)
                        @include('guide.partials._changelog-entry', ['entry' => $entry, 'tz' => $tz])
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
@endif
