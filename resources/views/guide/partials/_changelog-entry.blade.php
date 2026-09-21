<li class="ratgeber-news__item">
    <time class="ratgeber-news__date" datetime="{{ $entry['at']->copy()->timezone($tz)->toDateString() }}">{{ $entry['at']->copy()->timezone($tz)->locale('de')->translatedFormat('j. F Y') }}</time>
    <p class="ratgeber-news__text">
        {{ $entry['text'] }}
        @if(!empty($entry['source']))
            (Quelle:
            @if(!empty($entry['source']['url']))<a href="{{ $entry['source']['url'] }}" rel="nofollow noopener">{{ $entry['source']['label'] }}</a>@else{{ $entry['source']['label'] }}@endif)
        @endif
    </p>
</li>
