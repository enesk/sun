{{-- Platzhalter-Referenz für die Rechtstexte (#50). Standardmäßig zugeklappt. --}}
<div x-data="{ open: false }" class="mt-3">
    <button type="button" @click="open = ! open"
            class="inline-flex items-center gap-1.5 text-xs font-medium transition-colors"
            style="color: var(--dash-text-muted);"
            onmouseover="this.style.color='var(--dash-text-primary)'"
            onmouseout="this.style.color='var(--dash-text-muted)'"
            :aria-expanded="open ? 'true' : 'false'">
        <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        Verfügbare Platzhalter
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="mt-2 rounded-lg p-3"
         style="border: 1px solid var(--dash-border); background-color: var(--dash-bg-secondary);">
        <p class="dash-input-hint" style="margin-top: 0;">
            Diese Platzhalter werden beim Anzeigen der Seite durch Ihre Portaldaten ersetzt.
            Schreiben Sie sie genau so, mit eckigen Klammern — auch in Links, etwa
            <code>mailto:[BETREIBER_EMAIL]</code>. Überschreiben Sie sie nicht mit festen Angaben,
            sonst veraltet der Text bei jeder Änderung Ihrer Stammdaten.
        </p>
        <dl class="mt-2 space-y-1.5">
            @foreach (\App\Services\TenantBrandingService::LEGAL_PLACEHOLDERS as $placeholder => $description)
                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                    <dt class="text-xs font-mono shrink-0" style="color: var(--dash-text-primary); min-width: 12rem;">{{ $placeholder }}</dt>
                    <dd class="text-xs" style="color: var(--dash-text-muted);">{{ $description }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
