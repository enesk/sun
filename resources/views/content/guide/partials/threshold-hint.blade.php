{{--
    Hilfetext der Freigabeschwelle (design/guide-dashboard.md §11.1).
    Zeile 2 ist ein Hinweis, kein Fehler; der Container bleibt immer stehen,
    damit aria-live die Änderung beim Umschalten von YMYL ansagt.
--}}
<span class="block text-content-table text-text-muted">{{ __('50 bis 100. Liegt die Bewertung einer Fassung darunter, geht sie in die Prüfung.') }}</span>
<span class="block" aria-live="polite">
    @if ($notice !== null)
        <span class="mt-content-1 flex items-center gap-content-1 text-content-table text-text-base">
            <x-filament::icon icon="heroicon-m-information-circle" class="size-4 shrink-0" style="color: var(--color-status-scheduled-fg)" aria-hidden="true" />
            {{ $notice }}
        </span>
    @endif
</span>
