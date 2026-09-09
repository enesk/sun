<li class="ratgeber-sources__item">
    @if($source['publisher'])
        <span class="ratgeber-sources__publisher">{{ $source['publisher'] }}:</span>
    @endif
    @if($source['url'])
        <a href="{{ $source['url'] }}" rel="nofollow noopener" target="_blank">
            {{ $source['title'] }}
            <svg class="ratgeber-sources__external" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5M15 3h6v6M10 14L20.5 3.5"/>
            </svg>
        </a>
    @else
        {{ $source['title'] }}
    @endif
    @if($source['stale_year'])
        <span class="ratgeber-sources__stand">Stand {{ $source['stale_year'] }}</span>
    @elseif($source['published_at'])
        <span class="ratgeber-sources__stand">{{ $source['published_at']->translatedFormat('j. F Y') }}</span>
    @endif
</li>
