{{--
    Changelog „Was ist neu?“ im Theme sun-v2 (design/guide-frontend.md, §4.2):
    drei juengste Eintraege offen, aeltere hinter <details>. Leer: nichts.
    Erwartet: $changelog — Liste [at, text, source], juengster zuerst.
--}}
@if(!empty($changelog))
  @php
    $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
    $visibleEntries = array_slice($changelog, 0, 3);
    $olderEntries = array_slice($changelog, 3);
  @endphp
  <section class="mt-4" aria-labelledby="was-ist-neu">
    <h2 id="was-ist-neu" class="font-semibold text-zinc-900 scroll-mt-24">Was ist neu?</h2>
    <ul class="mt-2 divide-y divide-zinc-100" role="list">
      @foreach($visibleEntries as $entry)
        @include('guide.partials._changelog-entry', ['entry' => $entry, 'tz' => $tz])
      @endforeach
    </ul>
    @if($olderEntries !== [])
      <details class="mt-2">
        <summary class="cursor-pointer text-brand hover:underline min-h-11 flex items-center">Frühere Änderungen ({{ count($olderEntries) }})</summary>
        <ul class="divide-y divide-zinc-100" role="list">
          @foreach($olderEntries as $entry)
            @include('guide.partials._changelog-entry', ['entry' => $entry, 'tz' => $tz])
          @endforeach
        </ul>
      </details>
    @endif
  </section>
@endif
