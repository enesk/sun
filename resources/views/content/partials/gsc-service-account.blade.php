{{--
    Warnung zur Portal-Domain und Adresse des Dienstkontos (#116), §2.1/§2.4.

    Die Adresse ist bewusst statischer Text und kein gesperrtes Formularfeld:
    deaktivierte Felder fallen aus der Tabulatorfolge und lassen sich nicht
    markieren. Die Kopier-Schaltflaeche traegt ein sichtbares Label und meldet
    den Erfolg als Text, nicht nur als Farbwechsel.
--}}
@php
    $warning = $page->gscDomainWarning();
    $email = $page->gscServiceAccountEmail();
@endphp

@if ($warning !== null)
    <div
        class="mb-content-3 flex items-start gap-content-3 rounded-content-lg border p-content-4 text-content-body"
        style="background: var(--color-status-review-bg); border-color: var(--color-status-review-fill)"
        role="status"
    >
        <svg
            class="h-5 w-5 shrink-0"
            style="color: var(--color-status-review-fill)"
            fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
        </svg>

        <p class="text-text-base">{{ $warning }}</p>
    </div>
@endif

<div class="rounded-content-lg bg-surface-card p-content-4">
    <p class="text-content-label text-text-muted">{{ __('Dienstkonto') }}</p>

    @if ($email === null)
        <p class="mt-content-1 text-content-body text-text-base">
            {{ __('Noch kein Dienstkonto hinterlegt (GOOGLE_SERVICE_ACCOUNT_JSON).') }}
        </p>
    @else
        <div class="mt-content-1 flex flex-wrap items-center gap-content-3" x-data="{ copied: false }">
            <p class="font-semibold text-text-strong">{{ $email }}</p>

            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                x-on:click="navigator.clipboard.writeText(@js($email)); copied = true; setTimeout(() => copied = false, 4000)"
            >
                {{ __('Adresse kopieren') }}
            </x-filament::button>

            <span x-show="copied" x-cloak aria-live="polite" class="text-content-label" style="color: var(--color-status-published-fg)">
                {{ __('Kopiert') }}
            </span>
        </div>

        <p class="mt-content-2 text-content-label text-text-muted">
            {{ __('Diese Adresse in der Search Console unter Einstellungen → Nutzer und Berechtigungen mit Leserecht eintragen.') }}
        </p>
    @endif
</div>
