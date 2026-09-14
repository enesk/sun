{{--
    Local Hub oberhalb der Firmenliste (#12): Einleitung und Stadtteile.
    Daten: CityContentResolver::forCity() — Text ist bereits aufgeloest, hier wird nichts ersetzt.
    Sichtbar sind hoechstens drei Saetze (CityContentResolver::splitIntro()), der Rest liegt in
    einem nativen <details>. Stadtteil-Chips sind bewusst keine Links (keine Stadtteilseiten).
    Gestaltet fuer das Theme sun-v2 (Klassen .pill, x-sun.icon).
--}}
@props(['city', 'content' => null])

@php
    $intro = filled($content['intro_html'] ?? null) ? \App\Services\Content\CityContentResolver::splitIntro($content['intro_html']) : null;
    $districts = $content['districts'] ?? [];
    $prose = 'max-w-prose text-base leading-relaxed text-zinc-700 [&_p+p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-brand [&_a]:underline [&_strong]:font-semibold [&_strong]:text-zinc-900';
@endphp

@if($intro !== null || $districts !== [])
  <section {{ $attributes->merge(['class' => 'mt-5']) }} aria-label="Über {{ $city->name }}">
    @if($intro !== null)
      <div class="{{ $prose }}">{!! $intro['lead'] !!}</div>
      @if($intro['more'] !== null)
        <details class="group max-w-prose">
          <summary class="mt-2 inline-flex items-center gap-1 min-h-11 md:min-h-0 cursor-pointer list-none text-sm font-medium text-brand hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded">
            <span class="group-open:hidden">Mehr anzeigen</span>
            <span class="hidden group-open:inline">Weniger anzeigen</span>
            <x-sun.icon name="chevron-down" class="size-4 transition-transform duration-150 group-open:rotate-180" />
          </summary>
          <div class="mt-3 {{ $prose }}">{!! $intro['more'] !!}</div>
        </details>
      @endif
    @endif

    @if($districts !== [])
      <div class="mt-4">
        <h2 class="text-sm font-semibold text-zinc-900">Stadtteile in {{ $city->name }}</h2>
        <ul class="mt-2 flex flex-wrap gap-2">
          @foreach($districts as $district)
            <li class="pill">{{ $district }}</li>
          @endforeach
        </ul>
      </div>
    @endif
  </section>
@endif
