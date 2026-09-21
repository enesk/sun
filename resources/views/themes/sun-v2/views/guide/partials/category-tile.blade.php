{{-- Kategorie-Kachel im Theme sun-v2 (design/guide-frontend.md, §4.4). Erwartet: $tile [name, url, count, updated_at]. --}}
@php
    $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
@endphp
<a href="{{ $tile['url'] }}" class="card-interactive p-5 flex items-center gap-4">
  <div class="min-w-0 flex-1">
    <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $tile['name'] }}</h3>
    <p class="mt-1 text-sm text-zinc-500">
      {{ $tile['count'] }} Ratgeber
      @if($tile['updated_at'])
        <br>Aktualisiert am <time datetime="{{ $tile['updated_at']->copy()->timezone($tz)->toDateString() }}">{{ $tile['updated_at']->copy()->timezone($tz)->format('d.m.Y') }}</time>
      @endif
    </p>
  </div>
  <x-sun.icon name="chevron-right" class="size-5 shrink-0 text-zinc-400" />
</a>
