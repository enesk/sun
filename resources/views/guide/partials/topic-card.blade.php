{{--
    Themen-Karte (design/guide-frontend.md, §4.5): Titel, Kurzantwort als
    Anriss, Datumszeile — nie das Pruefdatum, kein Bild.
    Erwartet: $card [title, url, teaser, date_label, date]; $headingTag 'h2' | 'h3'.
--}}
@php($cardTag = $headingTag ?? 'h3')
<article class="ratgeber-card">
    <{{ $cardTag }} class="ratgeber-card__title"><a href="{{ $card['url'] }}" class="ratgeber-card__link">{{ $card['title'] }}</a></{{ $cardTag }}>
    @if($card['teaser'] !== '')
        <p class="ratgeber-card__teaser">{{ $card['teaser'] }}</p>
    @endif
    @if($card['date'])
        <p class="ratgeber-card__date">{{ $card['date_label'] }} <time datetime="{{ $card['date']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->toDateString() }}">{{ $card['date']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->format('d.m.Y') }}</time></p>
    @endif
</article>
