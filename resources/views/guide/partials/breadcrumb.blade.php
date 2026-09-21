{{--
    Brotkrume der Ratgeber-Seiten. Dieselbe Liste speist die BreadcrumbList
    im JSON-LD (GuideStructuredData), deshalb hier kein Microdata.
    Erwartet: $items — Liste [label, url].
--}}
<nav class="ratgeber-crumbs" aria-label="Brotkrume">
    <ol>
        @foreach($items as $crumb)
            <li>
                @if(!$loop->last)
                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    <span aria-hidden="true">&rsaquo;</span>
                @else
                    <span aria-current="page">{{ \Illuminate\Support\Str::limit($crumb['label'], 60) }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
