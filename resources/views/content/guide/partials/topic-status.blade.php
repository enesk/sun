{{--
    Pille Themenstatus (design/guide-dashboard.md §2.1). Bauform .content-status,
    "Pausiert" mit Pausenzeichen statt Punkt.
    Erwartet: $status (App\Guide\Enums\TopicStatus|string)
--}}
@php
    $status = $status instanceof \App\Guide\Enums\TopicStatus ? $status : \App\Guide\Enums\TopicStatus::from((string) $status);
@endphp
<span class="content-status content-status--{{ $status->pill() }}">{{ $status->label() }}</span>
