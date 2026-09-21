{{--
    Inhaltsverzeichnis im Theme sun-v2 aus der gesperrten Gliederung. Anker sind
    die festen Abschnitts-IDs (OutlineAnchors). Ohne JavaScript bedienbar.

    Erwartet: $toc — Liste [id, text, level]; $hasFaq; $variant 'inline'
    (in der Artikelkarte, unter xl, geschlossen) | 'sidebar' (Karte rechts, offen).
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
  @if($isSidebar)
    <nav class="card p-5" aria-label="Inhalt">
      <p class="font-semibold text-zinc-900">Auf dieser Seite</p>
      <ol class="mt-2 space-y-1 text-sm">
        @foreach($tocEntries as $entry)
          <li @class(['pl-3' => $entry['level'] > 2])><a href="#{{ $entry['id'] }}" class="block py-1 hover:text-brand">{{ $entry['text'] }}</a></li>
        @endforeach
      </ol>
    </nav>
  @else
    {{-- Liegt in der Artikelkarte: getoente Flaeche statt Karte in der Karte --}}
    <details class="group mt-6 rounded-2xl bg-zinc-50 px-5 xl:hidden">
      <summary class="flex items-center justify-between gap-4 min-h-11 py-3 cursor-pointer list-none font-semibold text-zinc-900">
        <span>Inhalt ({{ $sectionCount }} {{ $sectionCount === 1 ? 'Abschnitt' : 'Abschnitte' }})</span>
        <x-sun.icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition-transform duration-200 group-open:rotate-180" />
      </summary>
      <nav aria-label="Inhalt" class="pb-3">
        <ol class="divide-y divide-zinc-200">
          @foreach($tocEntries as $entry)
            <li @class(['pl-4' => $entry['level'] > 2])><a href="#{{ $entry['id'] }}" class="flex items-start min-h-11 py-2 hover:text-brand">{{ $entry['text'] }}</a></li>
          @endforeach
        </ol>
      </nav>
    </details>
  @endif
@endif
