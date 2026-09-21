{{--
    Warteschlange der Prüfung (#33, design/guide-dashboard.md §8.1): Einträge
    72 px hoch, Thema (2 Zeilen), darunter Portal · Art · Abschnitte, rechts
    die Wartezeit (ab 24 h in status-review-fg). Aktiver Eintrag mit linker
    Kante content-600 und Fläche content-50. Klick öffnet ohne Seitenwechsel.
    Erwartet: $queue (RunOverviewService::reviewQueue()), $current (Schlüssel),
    $networkWide (bool).
--}}
@use('App\Guide\Filament\Resources\ReviewRunResource')
<ul class="divide-y divide-line-soft">
    @foreach ($queue as $row)
        @php
            $isCurrent = $row['__key'] === $current;
            $isOld = $row['waiting_since'] !== null && \Illuminate\Support\Carbon::parse($row['waiting_since'])->lt(now()->subDay());
        @endphp
        <li wire:key="queue-{{ $row['__key'] }}">
            <button
                type="button"
                wire:click="showEntry('{{ $row['__key'] }}')"
                @if ($isCurrent) aria-current="true" @endif
                @class([
                    'flex min-h-[72px] w-full items-start justify-between gap-content-3 border-s-[3px] px-content-4 py-content-2 text-start hover:bg-surface-sunken',
                    'border-content-600 bg-content-50' => $isCurrent,
                    'border-transparent' => ! $isCurrent,
                ])
            >
                <span class="min-w-0">
                    <span class="line-clamp-2 text-content-table font-medium text-text-strong">{{ $row['question'] }}</span>
                    <span class="mt-content-1 block text-[12px] text-text-muted">
                        {{ collect([
                            $networkWide ? $row['tenant_name'] : null,
                            $row['awaits_outline'] ? __('Gliederung sperren') : ($row['mode'] === 'create' ? __('Neuanlage') : __('Aktualisierung')),
                            $row['changed_sections'] > 0 ? trans_choice('{1} 1 Abschnitt|[2,*] :count Abschnitte', $row['changed_sections'], ['count' => $row['changed_sections']]) : null,
                        ])->filter()->implode(' · ') }}
                    </span>
                </span>
                <span class="shrink-0 whitespace-nowrap text-[12px] {{ $isOld ? '' : 'text-text-muted' }}" @if ($isOld) style="color: var(--color-status-review-fg)" @endif>
                    {{ $row['waiting_since'] !== null ? ReviewRunResource::waitingLabel($row['waiting_since']) : '' }}
                </span>
            </button>
        </li>
    @endforeach
</ul>
