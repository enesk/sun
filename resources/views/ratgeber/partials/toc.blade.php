{{--
    Inhaltsverzeichnis, serverseitig aus den H2/H3 des Artikels erzeugt.
    Laeuft ohne JavaScript: <details open> statt Alpine-Toggle.
    Erwartet: $headings — Liste aus TocBuilder.
    Optional: $tocClass — zusaetzliche Klasse, damit dieselbe Liste einmal im
    Artikel (mobil) und einmal in der Seitenspalte (ab 1024 px) stehen kann (#83).
--}}
@if(count($headings ?? []) >= 3)
    <details class="ratgeber-toc {{ $tocClass ?? '' }}" open>
        <summary class="ratgeber-toc__summary">
            <span>Inhaltsverzeichnis</span>
            <svg class="ratgeber-toc__chevron" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </summary>
        <nav aria-label="Inhaltsverzeichnis">
            <ol class="ratgeber-toc__list">
                @foreach($headings as $heading)
                    <li class="ratgeber-toc__item ratgeber-toc__item--h{{ $heading['level'] }}">
                        <a href="#{{ $heading['id'] }}" class="ratgeber-toc__link">{{ $heading['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
    </details>
@endif
