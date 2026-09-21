{{-- Kategorie-Kachel (design/guide-frontend.md, §4.4). Erwartet: $tile [name, url, count, updated_at]. --}}
<a href="{{ $tile['url'] }}" class="ratgeber-tile">
    <div class="ratgeber-tile__body">
        <h3 class="ratgeber-tile__name">{{ $tile['name'] }}</h3>
        <p class="ratgeber-tile__meta">
            {{ $tile['count'] }} Ratgeber
            @if($tile['updated_at'])
                <br>Aktualisiert am <time datetime="{{ $tile['updated_at']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->toDateString() }}">{{ $tile['updated_at']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->format('d.m.Y') }}</time>
            @endif
        </p>
    </div>
    <svg class="ratgeber-tile__chevron" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</a>
