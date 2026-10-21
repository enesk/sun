{{--
    Hinweis an einer Textart, die zentral für alle Portale gepflegt wird
    (Ticket #17). Eingebunden mit $art ('Impressum', …).

    Steht hier, weil ein Feld, das sich ausfüllen lässt, aber nichts bewirkt,
    schlimmer ist als ein gesperrtes: Der Betreiber des Portals soll an der
    Stelle erfahren, warum seine Änderung nichts ändern würde — und wo sie
    hingehört (Muster aus Vorgabe #61 zur Anschrift).
--}}
<div class="flex gap-3 rounded-lg p-3 text-sm"
     style="background-color: var(--dash-bg-hover); border: 1px solid var(--dash-border);"
     role="status">
    <svg class="w-5 h-5 shrink-0" style="color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <div style="color: var(--dash-text-secondary);">
        <p class="font-medium" style="color: var(--dash-text-primary);">
            {{ $art }} wird zentral gepflegt
        </p>
        <p class="mt-1">
            Dieser Text ist für alle Portale gleich und wird vom Betreiber des Netzes
            gepflegt. Ihr Portal setzt beim Anzeigen nur noch die eigenen Angaben ein —
            Anschrift, E-Mail und Telefonnummer. Eine Änderung in diesem Feld wird nicht
            gespeichert und nicht angezeigt.
        </p>
    </div>
</div>
