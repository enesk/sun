{{--
    Inhaltsverzeichnis aus der gesperrten Gliederung (outline_json). Die Anker
    sind die festen Abschnitts-IDs der Gliederung (OutlineAnchors), nie ein
    Slug der Ueberschrift. Ohne JavaScript bedienbar (<details>).

    Erwartet: $toc — Liste [id, text, level]; $hasFaq (bool);
    $variant 'inline' (Artikel, unter 1024 px, geschlossen) | 'sidebar'
    (Seitenspalte ab 1024 px, offen). Beide Fassungen kommen aus diesem Partial.
--}}
@php
    $tocEntries = $toc ?? [];
    if ($hasFaq ?? false) {
        $tocEntries[] = ['id' => 'faq', 'text' => 'Häufige Fragen', 'level' => 2];
    }
    $sectionCount = count(array_filter($tocEntries, fn (array $entry): bool => $entry['level'] === 2));
    $isSidebar = ($variant ?? 'inline') === 'sidebar';
@endphp
@if(count($tocEntries) >= 2)
    <details class="ratgeber-toc {{ $isSidebar ? 'ratgeber-toc--sidebar' : 'ratgeber-toc--inline' }}" @if($isSidebar) open @endif>
        <summary class="ratgeber-toc__summary">
            <span>Inhalt ({{ $sectionCount }} {{ $sectionCount === 1 ? 'Abschnitt' : 'Abschnitte' }})</span>
            <svg class="ratgeber-toc__chevron" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </summary>
        <nav aria-label="Inhalt">
            <ol class="ratgeber-toc__list">
                @foreach($tocEntries as $entry)
                    <li class="ratgeber-toc__item ratgeber-toc__item--h{{ $entry['level'] }}">
                        <a href="#{{ $entry['id'] }}" class="ratgeber-toc__link">{{ $entry['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
    </details>
@endif
