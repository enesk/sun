{{--
    Zustandszeile der Search-Console-Property (#116), Vorgabe zu #107, §2.2.

    Sechs Zustaende, jeder mit Text UND Farbe — eine Pille, die nur farbig
    ist, sagt einem Menschen mit Farbsehschwaeche nichts. Der Bereich ist
    aria-live="polite", damit das Ergebnis von "Zugriff prüfen" auch ohne
    Sichtkontakt ankommt.
--}}
@php
    $state = $page->gscState();
    $blocked = $page->gscCheckBlockedReason();
@endphp

<div class="flex flex-wrap items-start justify-between gap-content-3">
    <div class="min-w-0 flex-1" aria-live="polite">
        <span class="content-status content-status--{{ $state['class'] }}">{{ $state['label'] }}</span>

        <p class="mt-content-2 text-content-body text-text-base">{{ $state['text'] }}</p>

        @if ($page->gscPropertyError() !== null)
            {{-- Ein schon gespeicherter unbrauchbarer Wert soll nicht erst
                 beim naechsten Speichern auffallen. --}}
            <p class="mt-content-2 text-content-body" style="color: var(--color-status-failed-fg)">
                {{ $page->gscPropertyError() }}
            </p>
        @endif

        @if (! empty($state['detail']))
            <p class="mt-content-1 text-content-label text-text-muted">{{ $state['detail'] }}</p>
        @endif
    </div>

    <div class="shrink-0">
        <x-filament::button
            type="button"
            color="gray"
            wire:click="checkGscAccess"
            wire:loading.attr="disabled"
            wire:target="checkGscAccess"
            :disabled="$blocked !== null"
            :title="$blocked"
        >
            <x-filament::loading-indicator class="inline h-4 w-4" wire:loading wire:target="checkGscAccess" />
            <span wire:loading.remove wire:target="checkGscAccess">{{ __('Zugriff prüfen') }}</span>
            <span wire:loading wire:target="checkGscAccess">{{ __('Prüfe …') }}</span>
        </x-filament::button>

        {{-- Der Grund steht als Hilfetext da, nicht nur im Tooltip: ein
             Tooltip erreicht weder Tastatur noch Vorlesesoftware. --}}
        @if ($blocked !== null)
            <p class="mt-content-1 max-w-xs text-content-label text-text-muted">{{ $blocked }}</p>
        @endif
    </div>
</div>
