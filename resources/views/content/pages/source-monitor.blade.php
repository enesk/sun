{{--
    Quellen-Monitor (#20), design/content-dashboard.md, §6.

    Kachelraster je Connector: Zustand, letzter erfolgreicher Abruf, Zahl der
    gelieferten Signale, Fehler im Klartext und der manuelle Abruf. Darunter
    die letzten Laeufe je Portal.

    Die Statusfarben werden hier mit eigenen Beschriftungen weiterverwendet —
    ein Connector hat keinen DisplayStatus, aber eine achte Farbe kommt nicht
    dazu.
--}}
@php
    $monitor = $this->getMonitorData();
    $connectors = $monitor['connectors'];
    $summary = $monitor['summary'];
    $notConfigured = $monitor['not_configured'];
@endphp

<x-filament-panels::page>
    @include('content.partials.settings-tabs', ['active' => 'quellen'])

    {{-- Abgeschaltete zaehlen nicht als "braucht Aufmerksamkeit", sondern
         werden getrennt genannt (§7a). --}}
    <p class="mb-content-4 text-content-body text-text-base">
        @if ($summary['total'] === 0)
            {{ __('Es ist keine Quelle registriert.') }}
        @elseif ($summary['active'] === 0)
            {{ __('Es ist keine Quelle aktiv.') }}
        @elseif ($summary['impaired'] === 0)
            {{ __('Alle :count aktiven Quellen aktuell.', ['count' => $summary['active']]) }}
            @if ($summary['last_run'])
                <span class="content-asof">{{ __('Letzter Abruf: :time', ['time' => $summary['last_run']]) }}</span>
            @endif
        @else
            {{ trans_choice(
                '{1}Eine von :total aktiven Quellen braucht Aufmerksamkeit.|[2,*]:count von :total aktiven Quellen brauchen Aufmerksamkeit.',
                $summary['impaired'],
                ['count' => $summary['impaired'], 'total' => $summary['active']],
            ) }}
        @endif

        @if ($summary['disabled'] > 0)
            {{ trans_choice(
                '{1}Eine Quelle ist abgeschaltet.|[2,*]:count Quellen sind abgeschaltet.',
                $summary['disabled'],
                ['count' => $summary['disabled']],
            ) }}
        @endif
    </p>

    @if ($summary['total'] > 0 && $summary['active'] === 0)
        <p
            class="mb-content-4 rounded-content-md p-content-3 text-content-body"
            style="background: var(--color-status-failed-bg); color: var(--color-status-failed-fg)"
        >
            {{ __('Keine Quelle ist aktiv. Ohne Rohsignale findet die Pipeline keine Themen.') }}
        </p>
    @endif

    <div class="grid gap-content-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($connectors as $connector)
            <article
                wire:key="connector-{{ $connector['key'] }}"
                class="flex flex-col gap-content-2 rounded-content-lg bg-surface-card p-content-4 shadow-content-card"
            >
                <header class="flex items-start justify-between gap-content-2 {{ $connector['is_enabled'] ? '' : 'opacity-70' }}">
                    <h3 class="text-content-h3 font-semibold text-text-strong">{{ $connector['label'] }}</h3>
                    <span class="content-status content-status--{{ $connector['display_status'] }} shrink-0">
                        {{ $connector['status_label'] }}
                    </span>
                </header>

                {{--
                    Konfigurationszeile (§7a): Rhythmus und Gewicht ohne Klick.
                    Abweichende Werte stehen kraeftiger und tragen die Marke
                    "abweichend" — eine Herkunftsangabe, kein Zustand, deshalb
                    ohne Statusfarbton.
                --}}
                <p class="flex flex-wrap items-center gap-x-content-2 gap-y-content-1 text-content-label text-text-muted">
                    <span class="{{ $connector['frequency_deviates'] ? 'text-text-strong' : '' }}">
                        {{ $connector['frequency'] }}
                    </span>
                    <span aria-hidden="true">·</span>
                    <span class="{{ $connector['weight_deviates'] ? 'text-text-strong' : '' }}">
                        {{ __('Gewicht :weight %', ['weight' => $connector['weight']]) }}
                    </span>
                    @if ($connector['deviates'])
                        {{--
                            Herkunftsangabe, kein Zustand — deshalb kein
                            Statusfarbton. Bauform liegt als .content-origin-pill
                            im Theme (#64), damit "abweichend" und "nicht
                            änderbar" nicht zweimal nachgebaut werden.
                        --}}
                        <span class="content-origin-pill">
                            {{ __('abweichend') }}
                        </span>
                    @endif
                </p>

                <dl class="grid grid-cols-2 gap-content-2 text-content-label {{ $connector['is_enabled'] ? '' : 'opacity-70' }}">
                    <div>
                        <dt class="text-text-muted">{{ __('Letzter Erfolg') }}</dt>
                        <dd class="text-text-base">{{ $connector['last_success'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ __('Signale gesamt') }}</dt>
                        <dd class="text-text-base">{{ number_format($connector['items'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ __('Abrufe heute') }}</dt>
                        <dd class="text-text-base">{{ number_format($connector['requests_today'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ __('Fehler in Folge') }}</dt>
                        <dd class="text-text-base">{{ $connector['consecutive_failures'] }}</dd>
                    </div>
                </dl>

                @if ($connector['last_error'])
                    {{-- Die Auswirkung im Klartext, nicht nur ein roter Punkt. --}}
                    <p
                        class="rounded-content-md border-s-[3px] p-content-2 text-content-label"
                        style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot); color: var(--color-status-failed-fg)"
                    >
                        {{ \Illuminate\Support\Str::limit($connector['last_error'], 220) }}
                        @if ($connector['last_failure_at'])
                            <span class="block">{{ __('seit :time', ['time' => $connector['last_failure_at']]) }}</span>
                        @endif
                    </p>
                @endif

                @if ($connector['circuit_open_until'])
                    <p class="text-content-label" style="color: var(--color-status-review-fg)">
                        {{ __('Gesperrt bis :time', ['time' => $connector['circuit_open_until']]) }}
                    </p>
                @endif

                <details class="text-content-label">
                    <summary class="cursor-pointer text-text-muted">{{ __('Letzte Läufe je Portal') }}</summary>
                    <ul class="mt-content-2 space-y-content-1">
                        @forelse ($connector['runs'] as $run)
                            <li class="flex items-center justify-between gap-content-2">
                                <span class="text-text-base">{{ $run['tenant'] }}</span>
                                <span class="text-text-muted">{{ $run['ago'] ?? __('nie') }}</span>
                            </li>
                        @empty
                            <li class="text-text-muted">{{ __('Kein Portal sichtbar.') }}</li>
                        @endforelse
                    </ul>
                </details>

                <div class="mt-auto flex flex-wrap items-center gap-content-2 pt-content-2">
                    @if ($connector['is_enabled'])
                        <button
                            type="button"
                            wire:click="mountAction('runNow', {{ \Illuminate\Support\Js::from(['connector' => $connector['key']]) }})"
                            class="h-9 rounded-content-md border border-content-600 px-content-3 text-content-table font-medium text-content-700"
                        >
                            {{ __('Jetzt ausführen') }}
                        </button>
                    @else
                        {{-- Kein manueller Abruf: er wuerde den Schalter wertlos machen (§7a). --}}
                        <p class="w-full text-content-label text-text-muted">
                            @if ($connector['disabled_at'] && $connector['disabled_by'])
                                {{ __('Abgeschaltet am :date von :user', ['date' => $connector['disabled_at'], 'user' => $connector['disabled_by']]) }}
                            @elseif ($connector['disabled_at'])
                                {{ __('Abgeschaltet am :date', ['date' => $connector['disabled_at']]) }}
                            @else
                                {{ __('Abgeschaltet') }}
                            @endif
                            @if ($connector['disabled_reason'])
                                — {{ __('Grund: :reason', ['reason' => $connector['disabled_reason']]) }}
                            @endif
                        </p>
                    @endif

                    <button
                        type="button"
                        wire:click="mountAction('configure', {{ \Illuminate\Support\Js::from(['connector' => $connector['key']]) }})"
                        class="h-9 rounded-content-md border border-line-soft px-content-3 text-content-table font-medium text-text-base"
                    >
                        {{ __('Einstellen') }}
                    </button>
                </div>
            </article>
        @empty
            <p class="text-content-body text-text-muted">
                {{ __('Es ist keine Quelle registriert. Connectoren werden im ContentServiceProvider eingetragen.') }}
            </p>
        @endforelse
    </div>

    @if ($notConfigured !== [])
        {{--
            Unter dem Raster, nicht dazwischen: sonst sucht man morgen im
            aktiven Raster nach etwas, das nie laeuft (§7a). Keine Schaltflaeche —
            der Schalter liegt in der Serverkonfiguration.
        --}}
        <section class="mt-content-6">
            <h2 class="mb-content-2 text-content-h3 font-semibold text-text-strong">{{ __('Nicht eingerichtet') }}</h2>
            <ul class="space-y-content-2">
                @foreach ($notConfigured as $row)
                    {{-- Auf surface-sunken traegt text-muted nur 4,45:1,
                         deshalb durchgehend text-base (#64). --}}
                    <li class="rounded-content-md bg-surface-sunken p-content-3">
                        <p class="text-content-body text-text-base">{{ $row['label'] }}</p>
                        <p class="text-content-label text-text-base">
                            {{ __('In der Serverkonfiguration abgeschaltet (:env)', ['env' => $row['env']]) }}
                        </p>
                        <p
                            class="text-content-label"
                            @if ($row['credentials']['state'] === 'missing')
                                style="color: var(--color-status-failed-fg)"
                            @endif
                        >
                            <span @class(['text-text-base' => $row['credentials']['state'] !== 'missing'])>
                                {{ $row['credentials']['text'] }}
                            </span>
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
