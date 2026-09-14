{{-- Seitennavigation der Vorlage: Text-Buttons aussen, Kreise mit Seitenzahlen in der Mitte. --}}
@props(['paginator', 'pages'])
@if($pages !== [])
<nav aria-label="Seiten" class="mt-4 flex flex-wrap items-center justify-center sm:justify-between gap-3">
  @if($paginator->onFirstPage())
    <span class="btn-ghost opacity-40 pointer-events-none" aria-disabled="true">Zurück</span>
  @else
    <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost" rel="prev">Zurück</a>
  @endif
  <ul class="order-first sm:order-none w-full sm:w-auto flex items-center justify-center gap-1">
    @foreach($pages as $page)
      @if($page === null)
        <li class="text-zinc-400 px-1">…</li>
      @elseif($page['current'])
        <li><a href="{{ $page['url'] }}" aria-current="page" class="size-11 rounded-full bg-brand text-white font-semibold flex items-center justify-center">{{ $page['number'] }}</a></li>
      @else
        <li @if($page['desktopOnly']) class="hidden sm:block" @endif><a href="{{ $page['url'] }}" class="size-11 rounded-full hover:bg-zinc-100 text-zinc-700 font-medium flex items-center justify-center">{{ $page['number'] }}</a></li>
      @endif
    @endforeach
  </ul>
  @if($paginator->hasMorePages())
    <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost" rel="next">Weiter</a>
  @else
    <span class="btn-ghost opacity-40 pointer-events-none" aria-disabled="true">Weiter</span>
  @endif
</nav>
@endif
