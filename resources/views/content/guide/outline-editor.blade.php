{{--
    Gliederungs-Editor (#15, design/guide-dashboard.md §5.4).
    Gesperrt: lesbare Liste mit Sprungzielen, einzige Bedienung "Entsperren …".
    In Bearbeitung: Zeile je Überschrift mit Ziehgriff (x-sortable aus dem
    Filament-Bundle), Ebene, Textfeld, Sprungziel, ↑ ↓ ← → und Löschen.
    Tastatur: Fokus auf der Zeile, Alt+↑/↓ verschieben, Alt+→ einrücken,
    Alt+← ausrücken; jede Verschiebung sagt die Live-Region an.
--}}
<div
    x-data
    x-on:guide-outline-focus.window="$nextTick(() => document.querySelector(`[data-outline-row='${$event.detail.key}']`)?.focus())"
    class="flex flex-col gap-4"
>
    <div class="sr-only" aria-live="polite" role="status">{{ $announcement }}</div>

    @if ($locked)
        <div class="content-refresh-strip content-refresh-strip--neutral justify-between">
            <span class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5" />
                {{ __('Gesperrt am :date. Überschriften und Sprungziele sind fest.', ['date' => $lockedAtLabel ?? '–']) }}
            </span>
            {{ $this->unlockAction }}
        </div>

        <ol class="rounded-xl border border-line-strong bg-surface-card">
            @foreach ($rows as $row)
                <li @class([
                    'flex items-baseline justify-between gap-4 border-b border-line-soft px-4 py-3 last:border-b-0',
                    'pl-10' => $row['level'] === 3,
                ])>
                    <span @class([
                        'text-content-body text-text-strong',
                        'font-semibold' => $row['level'] === 2,
                    ])>{{ $row['heading'] }}</span>
                    <span class="font-mono text-xs text-text-muted">#{{ $row['id'] }}</span>
                </li>
            @endforeach
        </ol>
    @else
        @if ($isProposal)
            <div class="content-refresh-strip content-refresh-strip--marked">
                {{ __('Vorschlag des Systems — prüfen, anpassen und sperren. Erst mit der Sperre entsteht der Artikel.') }}
            </div>
        @elseif ($hasArticle)
            <div class="content-refresh-strip content-refresh-strip--marked">
                {{ __('Entsperrt. Nach dem Sperren wird der Artikel beim nächsten Lauf komplett neu geschrieben; bis dahin bleibt die veröffentlichte Fassung online.') }}
            </div>
        @elseif ($rows === [])
            <div class="content-refresh-strip content-refresh-strip--neutral">
                {{ __('Noch kein Vorschlag. Der erste Lauf schlägt eine Gliederung vor — oder Sie legen sie hier selbst an.') }}
            </div>
        @endif

        @foreach ($violations[''] ?? [] as $message)
            <p class="text-sm text-status-failed-fg">{{ $message }}</p>
        @endforeach

        <ul
            x-sortable
            x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
            data-sortable-animation-duration="150"
            class="rounded-xl border border-line-strong bg-surface-card"
            aria-label="{{ __('Überschriften der Gliederung') }}"
        >
            @foreach ($rows as $index => $row)
                @php
                    $key = $row['key'];
                    $renamed = $row['id'] !== null && $row['original'] !== null && trim($row['heading']) !== $row['original'];
                @endphp
                <li
                    wire:key="outline-row-{{ $key }}"
                    x-sortable-item="{{ $key }}"
                    data-outline-row="{{ $key }}"
                    tabindex="0"
                    aria-label="{{ __('Überschrift :position von :total, Ebene H:level: :heading', ['position' => $index + 1, 'total' => count($rows), 'level' => $row['level'], 'heading' => $row['heading']]) }}"
                    x-on:keydown.alt.arrow-up.prevent="$wire.moveUp(@js($key))"
                    x-on:keydown.alt.arrow-down.prevent="$wire.moveDown(@js($key))"
                    x-on:keydown.alt.arrow-right.prevent="$wire.indent(@js($key))"
                    x-on:keydown.alt.arrow-left.prevent="$wire.outdent(@js($key))"
                    class="flex flex-col gap-1 border-b border-line-soft bg-surface-card px-2 py-1 last:border-b-0 focus-visible:outline-2 focus-visible:outline-content-600"
                >
                    <div @class(['flex min-h-12 items-center gap-2', 'pl-6' => $row['level'] === 3])>
                        <button
                            type="button"
                            x-sortable-handle
                            class="flex h-12 w-6 shrink-0 cursor-grab items-center justify-center text-text-muted"
                            aria-label="{{ __('Ziehen zum Verschieben') }}"
                            tabindex="-1"
                        >
                            <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-5 w-5" />
                        </button>

                        <span class="content-origin-pill shrink-0">H{{ $row['level'] }}</span>

                        <div class="flex min-w-0 flex-1 items-center gap-2" x-data="{ length: {{ mb_strlen($row['heading']) }} }">
                            <x-filament::input.wrapper class="min-w-0 flex-1">
                                <x-filament::input
                                    type="text"
                                    wire:model.blur="rows.{{ $index }}.heading"
                                    maxlength="{{ max($maxLength, mb_strlen($row['heading'])) }}"
                                    :aria-label="__('Text der Überschrift :position', ['position' => $index + 1])"
                                    x-on:input="length = $el.value.length"
                                    x-on:keydown.enter.prevent="$el.blur()"
                                    x-on:keydown.escape.prevent="$el.value = @js($row['heading']); length = $el.value.length; $el.blur()"
                                />
                            </x-filament::input.wrapper>
                            <span x-show="length >= {{ $maxLength - 10 }}" x-cloak class="shrink-0 text-xs tabular-nums text-text-muted" x-text="`${length}/{{ $maxLength }}`"></span>
                        </div>

                        <span class="hidden shrink-0 font-mono text-xs text-text-muted sm:inline">
                            {{ $row['id'] !== null ? '#'.$row['id'] : __('neu') }}
                        </span>

                        <div class="flex shrink-0 items-center">
                            <x-filament::icon-button icon="heroicon-m-arrow-up" color="gray" size="sm" :label="__('Nach oben')" wire:click="moveUp(@js($key))" :disabled="$index === 0" />
                            <x-filament::icon-button icon="heroicon-m-arrow-down" color="gray" size="sm" :label="__('Nach unten')" wire:click="moveDown(@js($key))" :disabled="$index === count($rows) - 1" />
                            <x-filament::icon-button icon="heroicon-m-arrow-left" color="gray" size="sm" :label="__('Ausrücken (H2)')" wire:click="outdent(@js($key))" :disabled="$row['level'] === 2" />
                            <x-filament::icon-button icon="heroicon-m-arrow-right" color="gray" size="sm" :label="__('Einrücken (H3)')" wire:click="indent(@js($key))" :disabled="$row['level'] === 3 || $index === 0" />
                            @if ($row['id'] === null)
                                <x-filament::icon-button icon="heroicon-m-trash" color="gray" size="sm" :label="__('Löschen')" wire:click="removeNewRow(@js($key))" />
                            @else
                                {{ ($this->removeAction)(['key' => $key]) }}
                            @endif
                        </div>
                    </div>

                    @if ($renamed)
                        <p @class(['pb-1 text-xs text-text-muted', 'pl-16' => $row['level'] === 3, 'pl-10' => $row['level'] === 2])>
                            {{ __('Sprungziel bleibt #:id', ['id' => $row['id']]) }}
                        </p>
                    @endif

                    @foreach ($violations[$key] ?? [] as $message)
                        <p @class(['pb-1 text-xs text-status-failed-fg', 'pl-16' => $row['level'] === 3, 'pl-10' => $row['level'] === 2])>{{ $message }}</p>
                    @endforeach
                </li>
            @endforeach
        </ul>

        <div class="flex flex-wrap gap-4">
            <x-filament::link tag="button" wire:click="addRow(2)" icon="heroicon-m-plus">{{ __('Überschrift H2') }}</x-filament::link>
            <x-filament::link tag="button" wire:click="addRow(3)" icon="heroicon-m-plus" :disabled="$rows === []">{{ __('Überschrift H3') }}</x-filament::link>
        </div>

        <p class="text-xs text-text-muted">
            {{ __('Regeln: erste Überschrift ist H2 · :min bis :max H2 · höchstens :h3 H3 je H2 · keine leere oder doppelte Überschrift.', [
                'min' => \App\Guide\Support\OutlineDraft::MIN_H2,
                'max' => \App\Guide\Support\OutlineDraft::MAX_H2,
                'h3' => \App\Guide\Support\OutlineDraft::MAX_H3_PER_H2,
            ]) }}
        </p>

        <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t border-line-soft bg-surface-card px-4 py-4">
            <x-filament::link tag="button" color="gray" wire:click="discard" :disabled="! $dirty">
                {{ __('Änderungen verwerfen') }}
            </x-filament::link>

            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-text-base">
                    @if ($hasArticle)
                        {{ __('Nach dem Sperren wird der Artikel komplett neu geschrieben · ≈ :cost', ['cost' => \App\Guide\Support\Usd::format(\App\Guide\Support\Usd::estimate('create'))]) }}
                    @elseif ($changed > 0)
                        {{ trans_choice('{1} Eine Überschrift geändert oder neu|[2,*] :count Überschriften geändert oder neu', $changed, ['count' => $changed]) }}
                    @endif
                </span>

                @if ($violations !== [])
                    <span class="text-sm text-status-failed-fg">{{ __('Sperren erst, wenn alle Regeln erfüllt sind.') }}</span>
                @endif

                <x-filament::button color="gray" wire:click="saveDraft" :disabled="! $dirty">
                    {{ __('Entwurf speichern') }}
                </x-filament::button>

                {{ $this->lockAction }}
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
