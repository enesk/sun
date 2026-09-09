{{--
    Seitenspalte der Ratgeber-Detailseite (#83).
    Aufbau nach design/ratgeber-template.md, Abschnitt 4 „Sidebar (ab 1024 px)":
      1. sidebar_top — Anzeige mit reservierter Mindesthoehe aus AdSlot.php
      2. Inhaltsverzeichnis, klebend ab dem Scrollen (top: 88px)
      3. Firmensuche-Kasten der Region, kompakt
      4. sidebar_sticky — klebende Anzeige, erst ab 1024 px

    Punkte 2 bis 4 stehen in einem gemeinsamen klebenden Block. Zwei getrennte
    klebende Elemente mit demselben Versatz wuerden einander ueberdecken.

    Unter 1024 px entfaellt die Spalte vollstaendig (.ratgeber-sidebar).
    Sidebar-Inhalte werden mobil bewusst nicht unter den Artikel gehaengt;
    das Inhaltsverzeichnis steht dort im Artikel an Position 4 des Blueprints.

    Die Listenseiten des Blogs behalten weiterhin _sidebar.blade.php.

    Erwartet: $headings (TocBuilder), $region (ArticleBlockPresenter::region()).
--}}
@php
    $sidebarRegion = $region ?? null;
    $sidebarSearchUrl = $sidebarRegion['url'] ?? route('portal.companies.index');
@endphp

<aside class="ratgeber-sidebar" aria-label="Weitere Informationen zum Artikel">

    {{-- 1. Anzeige oben --}}
    <x-ad-slot position="sidebar_top" />

    <div class="ratgeber-sidebar__sticky">

        {{-- 2. Inhaltsverzeichnis --}}
        @include('ratgeber.partials.toc', [
            'headings' => $headings,
            'tocClass' => 'ratgeber-toc--sidebar',
        ])

        {{-- 3. Firmensuche der Region --}}
        <section class="ratgeber-sidebar__search" aria-labelledby="sidebar-search-heading">
            <h2 id="sidebar-search-heading" class="ratgeber-sidebar__search-title">
                @if($sidebarRegion)
                    Anbieter in {{ $sidebarRegion['name'] }}
                @else
                    Anbieter finden
                @endif
            </h2>
            <p class="ratgeber-sidebar__search-text">
                @if($sidebarRegion && ($sidebarRegion['company_count'] ?? 0))
                    {{ number_format($sidebarRegion['company_count'], 0, ',', '.') }} gelistete Betriebe in der Region vergleichen.
                @else
                    Betriebe aus der Region vergleichen und direkt anfragen.
                @endif
            </p>
            <a href="{{ $sidebarSearchUrl }}" class="ratgeber-sidebar__search-btn">
                Zur Firmensuche
            </a>
        </section>

        {{-- 4. Klebende Anzeige --}}
        <x-ad-slot position="sidebar_sticky" />

    </div>

</aside>
