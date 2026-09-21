{{--
    Beschreibung der Spalte "Thema": Kategorie, darunter nur wenn zutreffend
    "1 Quelle nicht erreichbar" (design/guide-dashboard.md §5.7.2).
    Erwartet: $record (Zeile aus App\Guide\Services\TopicDirectory::topics())
--}}
@php
    $unreachable = (int) ($record['unreachable_sources'] ?? 0);
@endphp
<span class="block">{{ $record['category_name'] ?? __('ohne Kategorie') }}</span>
@if ($unreachable > 0)
    <span class="mt-0.5 flex items-center gap-1 text-content-label text-status-review-fg">
        <x-filament::icon icon="heroicon-m-link-slash" class="size-3.5 shrink-0" aria-hidden="true" />
        {{ \App\Guide\Support\UnreachableSources::countLine($unreachable) }}
    </span>
@endif
