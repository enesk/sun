{{--
    Pille Laufergebnis mit Fortschrittszeile darunter (design/guide-dashboard.md §2.2).
    Erwartet: $run — Laufzeilen aus App\Guide\Services\TopicDirectory::runFields()
    (run_display, run_status, run_date, run_error, run_started_at, run_finished_at),
    optional $topicStatus (string) fuer den Text ohne Lauf,
    optional $replaceSource (bool) fuer den Grund "Quelle ersetzen" (§5.7.5 C);
    ohne Angabe gilt unreachable_sources aus der Themenliste (TopicDirectory).
--}}
@php
    $display = filled($run['run_display'] ?? null) ? \App\Guide\Enums\RunDisplay::from($run['run_display']) : null;
    $tz = config('guide.timezone');
    $time = fn (?string $value): ?string => $value !== null ? \Illuminate\Support\Carbon::parse($value)->timezone($tz)->format('d.m., H:i') : null;
    // Grund steht bei in-arbeit vor der Zeit, damit er bei Kuerzung sichtbar bleibt (§5.7.5 C)
    $reason = ($replaceSource ?? ((int) ($run['unreachable_sources'] ?? 0) > 0))
        ? ' · '.\App\Guide\Support\UnreachableSources::progressReason()
        : '';
    $progress = match ($display) {
        null => null,
        \App\Guide\Enums\RunDisplay::IN_PROGRESS => \App\Guide\Enums\RunStatus::from($run['run_status'])->label().$reason.' · '.__('seit :time', ['time' => $time($run['run_started_at'])]),
        \App\Guide\Enums\RunDisplay::QUEUED => __('angelegt :time', ['time' => $time($run['run_started_at'])]).$reason,
        \App\Guide\Enums\RunDisplay::FAILED => \Illuminate\Support\Str::limit((string) ($run['run_error'] ?? __('ohne Angabe')), 60),
        default => $time($run['run_finished_at'] ?? null) ?? \Illuminate\Support\Carbon::parse($run['run_date'])->format('d.m.Y'),
    };
@endphp
@if ($display === null)
    <span class="text-sm text-text-muted">
        {{ ($topicStatus ?? null) === 'paused' ? __('pausiert') : __('noch kein Lauf') }}
    </span>
@else
    <div>
        <span class="content-status content-status--{{ $display->pill() }}">{{ $display->label() }}</span>
        @if ($progress)
            <span class="content-status-progress" @if ($display === \App\Guide\Enums\RunDisplay::FAILED) title="{{ $run['run_error'] }}" @endif>{{ $progress }}</span>
        @endif
    </div>
@endif
