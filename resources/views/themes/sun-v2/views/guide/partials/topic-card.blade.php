{{--
    Themen-Karte im Theme sun-v2 (design/guide-frontend.md, §4.5): Titel,
    Kurzantwort als Anriss, Datumszeile — nie das Pruefdatum, kein Bild.
    Erwartet: $card [title, url, teaser, date_label, date]; $headingTag 'h2' | 'h3'.
--}}
@php
    $cardTag = $headingTag ?? 'h3';
    $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
@endphp
<a href="{{ $card['url'] }}" class="card-interactive p-5 flex flex-col gap-2">
  <{{ $cardTag }} class="text-lg font-semibold text-zinc-900 leading-snug">{{ $card['title'] }}</{{ $cardTag }}>
  @if($card['teaser'] !== '')
    <p class="text-sm text-zinc-500 line-clamp-3">{{ $card['teaser'] }}</p>
  @endif
  @if($card['date'])
    <p class="text-sm text-zinc-500 mt-auto pt-1">{{ $card['date_label'] }} <time datetime="{{ $card['date']->copy()->timezone($tz)->toDateString() }}">{{ $card['date']->copy()->timezone($tz)->format('d.m.Y') }}</time></p>
  @endif
</a>
