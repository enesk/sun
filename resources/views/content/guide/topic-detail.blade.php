{{--
    Thema-Detail (#15, design/guide-dashboard.md §5.3).
    Kopf: Themenstatus und letzter Lauf als getrennte Pillen, Metazeile.
    Darunter Band "nicht erreichbar" (§5.7.3), solange eine Quelle mit
    broken_at noch einen aktuellen Fakt belegt.
    Titelbild-Vorschau (#20), sobald der Artikel eines hat.
    Reiter: Gliederung (Gliederungs-Editor), Fakten, Quellen, Läufe.
    Solange der letzte Lauf wartet oder arbeitet, pollt die Seite alle 5 s.
--}}
@php
    $detail = $this->detail();
    $tabs = [
        'gliederung' => __('Gliederung'),
        'fakten' => __('Fakten').' ('.count($detail['facts']).')',
        'quellen' => __('Quellen').' ('.count($detail['sources']).')',
        'laeufe' => __('Läufe').' ('.count($detail['runs']).')',
    ];
@endphp
<x-filament-panels::page>
    <div @if ($this->isRunActive()) wire:poll.5s.visible="refreshRuns" @endif class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-start gap-2">
                @include('content.guide.partials.topic-status', ['status' => $detail['status']])
                @include('content.guide.partials.run-status', ['run' => $detail['run'], 'topicStatus' => $detail['status'], 'replaceSource' => $detail['unreachable'] !== []])
            </div>
            <p class="text-sm text-text-muted">{{ $this->metaLine() }}</p>
        </div>

        @if ($detail['hero'])
            <figure class="flex items-center gap-4 rounded-xl border border-line-strong bg-surface-card p-3">
                <img src="{{ $detail['hero']['src'] }}"
                     width="{{ $detail['hero']['width'] }}"
                     height="{{ $detail['hero']['height'] }}"
                     alt="{{ $detail['hero']['alt'] }}"
                     class="h-auto w-40 shrink-0 rounded-lg"
                     loading="lazy">
                <figcaption class="text-sm text-text-base">
                    <span class="block text-xs uppercase tracking-wide text-text-muted">{{ __('Titelbild') }}</span>
                    {{ $detail['hero']['alt'] }}
                </figcaption>
            </figure>
        @elseif ($detail['has_article_detail'])
            <p class="text-sm text-text-muted">{{ __('Noch kein Titelbild. Es entsteht nach der ersten Veröffentlichung oder über „Aktionen“.') }}</p>
        @endif

        @if ($detail['unreachable'] !== [])
            <div class="content-refresh-strip content-refresh-strip--marked flex-col items-start" role="note">
                <p>
                    {{ \App\Guide\Support\UnreachableSources::headline($detail['unreachable']) }}
                    {{ $this->unreachableNextStep() }}
                </p>
                @if (count($detail['unreachable']) > 1)
                    <details class="w-full text-sm">
                        <summary class="cursor-pointer">{{ __('Betroffene Quellen') }}</summary>
                        <ul class="mt-2 flex flex-col gap-1">
                            @foreach ($detail['unreachable'] as $source)
                                <li class="break-all">
                                    {{ collect([
                                        $source['publisher'],
                                        $source['url'],
                                        $source['code'] !== null ? __('Statuscode :code', ['code' => $source['code']]) : null,
                                        $source['since'] !== null ? __('seit :date', ['date' => $source['since']]) : null,
                                    ])->filter()->implode(' · ') }}
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <x-filament::tabs :label="__('Bereiche des Themas')">
            @foreach ($tabs as $value => $label)
                <x-filament::tabs.item
                    :active="$tab === $value"
                    wire:click="$set('tab', '{{ $value }}')"
                >
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @if ($tab === 'gliederung')
            @livewire('content.guide.outline-editor', [
                'tenantId' => $this->tenantId,
                'topicId' => $this->topicId,
            ], key('outline-'.$this->tenantId.'-'.$this->topicId))
        @elseif ($tab === 'fakten')
            <section class="rounded-xl border border-line-strong bg-surface-card">
                @if ($detail['facts'] === [])
                    <p class="p-6 text-sm text-text-base">
                        {{ __('Noch keine Fakten. Das Fakten-Set entsteht mit der ersten Recherche.') }}
                    </p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-line-soft text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Fakt') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Wert') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Gültig ab') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Zuletzt bestätigt') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Quelle') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ($detail['facts'] as $fact)
                                <tr>
                                    <td class="px-4 py-3 text-text-strong">{{ $fact['label'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-text-base">{{ $fact['value'] }} {{ $fact['unit'] }}</td>
                                    <td class="px-4 py-3 text-text-base">{{ $fact['valid_from'] ?? '–' }}</td>
                                    <td class="px-4 py-3 text-text-base">{{ $fact['last_seen_at'] ?? '–' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($fact['source'] && $fact['source']['unreachable'])
                                            <span class="block text-text-base">{{ \Illuminate\Support\Str::limit($fact['source']['title'], 60) }}</span>
                                            <span class="content-status content-status--review mt-1 before:hidden">
                                                <x-filament::icon icon="heroicon-m-link-slash" class="size-3.5 shrink-0" aria-hidden="true" />
                                                {{ __('nicht erreichbar') }}
                                            </span>
                                        @elseif ($fact['source'])
                                            <a href="{{ $fact['source']['url'] }}" target="_blank" rel="noopener noreferrer" class="text-content-700 underline underline-offset-2">
                                                {{ \Illuminate\Support\Str::limit($fact['source']['title'], 60) }}
                                            </a>
                                        @else
                                            <span class="text-text-muted">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($detail['facts_history'] > 0)
                        <p class="border-t border-line-soft px-4 py-3 text-xs text-text-muted">
                            {{ trans_choice('{1} Dazu ein früherer, inzwischen ersetzter Wert.|[2,*] Dazu :count frühere, inzwischen ersetzte Werte.', $detail['facts_history'], ['count' => $detail['facts_history']]) }}
                        </p>
                    @endif
                @endif
            </section>
        @elseif ($tab === 'quellen')
            <section class="rounded-xl border border-line-strong bg-surface-card">
                @if ($detail['sources'] === [])
                    <p class="p-6 text-sm text-text-base">
                        {{ __('Noch keine Quellen. Sie entstehen mit der ersten Recherche.') }}
                    </p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($detail['sources'] as $source)
                            <li class="flex flex-col gap-1 px-4 py-3 text-sm">
                                @if ($source['unreachable'])
                                    {{-- URL als Text, nicht verlinkt (§5.7.3) --}}
                                    <span class="font-medium text-text-strong">{{ \Illuminate\Support\Str::limit($source['title'], 120) }}</span>
                                    <span class="break-all text-xs text-text-base">{{ $source['url'] }}</span>
                                    <div>
                                        @include('content.guide.partials.unreachable-source', [
                                            'code' => $source['link_status_code'],
                                            'checkedAt' => $source['link_checked_at'],
                                        ])
                                    </div>
                                @else
                                    <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="font-medium text-content-700 underline underline-offset-2">
                                        {{ \Illuminate\Support\Str::limit($source['title'], 120) }}
                                    </a>
                                @endif
                                <span class="text-xs text-text-muted">
                                    {{ collect([
                                        $source['publisher'],
                                        $source['trust'],
                                        $source['published_at'] ? __('vom :date', ['date' => $source['published_at']]) : null,
                                        $source['retrieved_at'] ? __('abgerufen :date', ['date' => $source['retrieved_at']]) : null,
                                    ])->filter()->implode(' · ') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @else
            <section class="rounded-xl border border-line-strong bg-surface-card">
                @if ($detail['runs'] === [])
                    <p class="p-6 text-sm text-text-base">
                        {{ __('Noch kein Lauf. Das Thema läuft im nächsten Tageslauf mit, sobald es fällig ist, oder über „Jetzt ausführen“.') }}
                    </p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($detail['runs'] as $run)
                            <li>
                                <details class="group">
                                    <summary class="grid cursor-pointer grid-cols-1 items-center gap-2 px-4 py-3 text-sm md:grid-cols-[10rem_8rem_1fr_6rem_6rem]">
                                        <span class="tabular-nums text-text-strong">{{ $run['date'] }}</span>
                                        <span class="text-text-base">{{ $run['mode'] }}</span>
                                        <span>@include('content.guide.partials.run-status', ['run' => $run, 'replaceSource' => false])</span>
                                        <span class="tabular-nums text-text-base">{{ $run['duration'] ?? '–' }}</span>
                                        <span class="tabular-nums text-text-base md:text-right">{{ $run['cost'] }}</span>
                                    </summary>
                                    <div class="flex flex-col gap-3 border-t border-line-soft bg-surface-sunken px-4 py-3 text-sm text-text-base">
                                        <ol class="flex flex-col gap-1 border-l-2 border-line-strong pl-3">
                                            @foreach ($run['steps'] as $step => $time)
                                                <li><span class="tabular-nums">{{ $time }}</span> — {{ $step }}</li>
                                            @endforeach
                                        </ol>
                                        @if ($run['changed_sections'] > 0)
                                            <p>{{ trans_choice('{1} Ein Abschnitt geändert.|[2,*] :count Abschnitte geändert.', $run['changed_sections'], ['count' => $run['changed_sections']]) }}</p>
                                        @endif
                                        @if ($run['change_summary'])
                                            <p>{{ $run['change_summary'] }}</p>
                                        @endif
                                        @if ($run['run_error'])
                                            <p class="font-mono text-xs">{{ $run['status_value'] }}: {{ $run['run_error'] }}</p>
                                        @endif
                                    </div>
                                </details>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
