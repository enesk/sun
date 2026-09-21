{{--
    Stand-Zeile im Theme sun-v2. Datumsvertrag wie guide/partials/status-line
    (design/guide-frontend.md, §4.1): „Aktualisiert“ nur bei inhaltlicher
    Aenderung, „Geprueft“ nur an einem spaeteren Tag, Tage in Europe/Berlin.

    Erwartet: $publishedAt, $updatedAt, $checkedAt, $hasChangelog, $variant 'kompakt' | 'vollstaendig'.
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
  {{-- kompakt: fliesst in die Meta-Zeile des Artikels ein (contents), vollstaendig: eigene Zeile --}}
  <ul class="{{ $variant === 'kompakt' ? 'contents' : 'flex flex-wrap gap-x-4 gap-y-1' }} text-sm text-zinc-500" role="list">
    @foreach($entries as $entry)
      <li>{{ $entry['label'] }} <time datetime="{{ $entry['date']->copy()->timezone($tz)->toDateString() }}">{{ $entry['date']->copy()->timezone($tz)->locale('de')->translatedFormat('j. F Y') }}</time></li>
    @endforeach
    @if($variant === 'kompakt' && ($hasChangelog ?? false))
      <li><a href="#was-ist-neu" class="text-brand hover:underline">Was ist neu?</a></li>
    @endif
  </ul>
@endif
