<li class="py-2">
  <time class="text-zinc-500" datetime="{{ $entry['at']->copy()->timezone($tz)->toDateString() }}">{{ $entry['at']->copy()->timezone($tz)->locale('de')->translatedFormat('j. F Y') }}</time>
  <p class="mt-0.5 text-zinc-700">
    {{ $entry['text'] }}
    @if(!empty($entry['source']))
      (Quelle:
      @if(!empty($entry['source']['url']))<a href="{{ $entry['source']['url'] }}" rel="nofollow noopener" class="underline hover:text-brand">{{ $entry['source']['label'] }}</a>@else{{ $entry['source']['label'] }}@endif)
    @endif
  </p>
</li>
