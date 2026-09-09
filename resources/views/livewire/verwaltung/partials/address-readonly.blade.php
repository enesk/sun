{{--
    Schreibgeschützte Wiedergabe der Anschrift (Vorgabe #61, §4.2).
    Reiner Text, kein gesperrtes Formularfeld — daher kein disabled/aria-hidden/tabindex.
    editHref: setzt den In-Seiten-Sprung „Bearbeiten" (nur im Reiter „Kontakt & Social",
    wo der Alpine-Ablauf jumpToAnschrift() im Geltungsbereich liegt).
--}}
@props(['label', 'value' => null, 'origin', 'editHref' => null])

<div>
    <div class="dash-label">{{ $label }}</div>
    <div style="display: flex; align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
        @if(filled($value))
            <span style="white-space: pre-line; font-size: 0.875rem; color: var(--dash-text-primary);">{{ $value }}</span>
        @else
            <span style="font-size: 0.875rem; color: var(--dash-text-muted);">Noch nicht hinterlegt</span>
        @endif

        <span style="height: 20px; display: inline-flex; align-items: center; padding: 0 0.5rem; background: var(--dash-bg-secondary); color: var(--dash-text-muted); border-radius: 6px; font-size: 0.6875rem;">{{ $origin }}</span>

        @if($editHref)
            <a href="{{ $editHref }}"
               @click.prevent="tab = 'workspace'; $nextTick(() => jumpToAnschrift())"
               style="font-size: 0.875rem; text-decoration: underline; color: var(--portal-primary);">Bearbeiten</a>
        @endif
    </div>
</div>
