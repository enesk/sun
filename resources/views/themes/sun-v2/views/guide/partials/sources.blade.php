{{--
    Quellenliste im Theme sun-v2 (design/guide-frontend.md, §4.3). Ab dem
    neunten Eintrag hinter <details>.
    Erwartet: $sources — Liste [publisher, title, url, stale_year].
--}}
@if(!empty($sources))
  <div class="mt-4" aria-labelledby="sources-heading" role="region">
    <h2 id="sources-heading" class="font-semibold text-zinc-900">Verwendete Quellen</h2>
    <ol class="mt-2 space-y-1.5 list-decimal pl-5">
      @foreach(array_slice($sources, 0, 8) as $source)
        @include('guide.partials._source-item', ['source' => $source])
      @endforeach
    </ol>
    @if(count($sources) > 8)
      <details class="mt-2">
        <summary class="cursor-pointer text-brand hover:underline min-h-11 flex items-center">Alle Quellen anzeigen ({{ count($sources) }})</summary>
        <ol class="space-y-1.5 list-decimal pl-5" start="9">
          @foreach(array_slice($sources, 8) as $source)
            @include('guide.partials._source-item', ['source' => $source])
          @endforeach
        </ol>
      </details>
    @endif
  </div>
@endif
