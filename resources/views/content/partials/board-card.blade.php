{{--
    Board-Karte (#19), Aufbau nach design/content-dashboard.md, §2:
    Portal · Titel · Statuspille mit Score · Fortschrittszeile · Fusszeile.

    Der Feinschritt aus DraftStatus::progressLabel() steht ausschliesslich in
    der Fortschrittszeile, nie als zweite Pille.
--}}
<button
    type="button"
    wire:key="card-{{ $card['key'] }}"
    wire:click="mountAction('cardDetails', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
    @class([
        'block w-full rounded-content-lg bg-surface-card p-content-3 text-start shadow-content-card',
        'transition hover:shadow-content-raise motion-reduce:transition-none',
    ])
>
    <span class="flex items-center gap-content-2 text-content-label text-text-muted">
        <span
            @class([
                'inline-block h-2 w-2 flex-none rounded-full',
                'content-dot--running' => $card['status'] === \App\Content\Enums\DisplayStatus::GENERATING->value,
            ])
            style="background: var(--color-status-{{ $card['status'] }}-dot)"
            aria-hidden="true"
        ></span>
        <span class="truncate">{{ $card['tenant'] }}</span>
        <span class="ms-auto truncate">{{ $card['region'] }}</span>
    </span>

    <span class="mt-content-1 line-clamp-2 block text-content-table font-semibold text-text-strong">
        {{ $card['title'] }}
    </span>

    <span class="mt-content-2 flex items-center gap-content-2">
        <span class="content-status content-status--{{ $card['status'] }}">{{ $card['status_label'] }}</span>

        @if ($card['score'] !== null)
            <span
                class="ms-auto text-content-table font-semibold"
                @if ($card['score_level'])
                    style="color: var(--color-score-{{ $card['score_level'] }})"
                @endif
                title="{{ __('Qualitätsscore') }}"
            >
                {{ number_format((float) $card['score'], 0, ',', '.') }}
            </span>
        @endif
    </span>

    @if ($card['progress'])
        <span class="content-status-progress">{{ $card['progress'] }}</span>
    @endif

    <span class="mt-content-2 block text-content-label text-text-muted">
        @if ($card['status'] === \App\Content\Enums\DisplayStatus::FAILED->value)
            {{ __('Versuch :count gescheitert', ['count' => $card['attempt']]) }}
        @elseif ($card['status'] === \App\Content\Enums\DisplayStatus::GENERATING->value && ($card['running_since'] ?? null))
            {{-- Die Zeile traegt den laufenden Zustand, nicht die Bewegung
                 des Punktes (#42, §7). --}}
            {{ __('läuft seit :time', ['time' => $card['running_since']]) }}
        @elseif ($card['time'])
            {{ __('geplant für :time Uhr', ['time' => $card['time']]) }}
        @else
            {{ __('ohne Termin') }}
        @endif
    </span>
</button>
