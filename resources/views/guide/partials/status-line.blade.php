{{--
    Stand-Zeile (design/guide-frontend.md, §4.1). Setzt den Datumsvertrag um:
    „Aktualisiert“ nur bei inhaltlicher Aenderung, „Geprueft“ nur, wenn die
    Pruefung an einem spaeteren Tag lag. Tage kalendarisch in Europe/Berlin.

    Erwartet: $publishedAt, $updatedAt (null = nie inhaltlich geaendert),
    $checkedAt, $hasChangelog (bool), $variant 'kompakt' | 'vollstaendig'.
--}}
@php
    $tz = \App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE;
    $day = fn ($date) => $date?->copy()->timezone($tz)->startOfDay();
    $variant = $variant ?? 'kompakt';
    $lastContent = $updatedAt ?? $publishedAt;
    $showChecked = $checkedAt && $lastContent && $day($checkedAt)->greaterThan($day($lastContent));

    $entries = [];
    if ($publishedAt && ($variant === 'vollstaendig' || ! $updatedAt)) {
        $entries[] = ['label' => 'Veröffentlicht am', 'date' => $publishedAt];
    }
    if ($updatedAt) {
        $entries[] = ['label' => 'Aktualisiert am', 'date' => $updatedAt];
    }
    if ($showChecked) {
        $entries[] = ['label' => 'Geprüft am', 'date' => $checkedAt];
    }
@endphp
@if($entries !== [])
    <ul class="ratgeber-stand" role="list">
        @foreach($entries as $entry)
            <li>{{ $entry['label'] }} <time datetime="{{ $entry['date']->copy()->timezone($tz)->toDateString() }}">{{ $entry['date']->copy()->timezone($tz)->locale('de')->translatedFormat('j. F Y') }}</time></li>
        @endforeach
        @if($variant === 'kompakt' && ($hasChangelog ?? false))
            <li><a href="#was-ist-neu" class="ratgeber-stand__link">Was ist neu?</a></li>
        @endif
    </ul>
@endif
