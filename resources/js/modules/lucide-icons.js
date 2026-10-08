/*
 * Lucide-Icons im Default-Theme — ohne Inline-Skript (#21).
 *
 * Stand vorher in themes/{default,starter}/views/layouts/app.blade.php. Das
 * UMD-Bundle kommt per <script src> von unpkg und legt window.lucide an;
 * gezeichnet wird beim Seitenaufbau und nach jedem Livewire-Morph.
 */

function zeichne() {
    window.lucide?.createIcons();
}

export function initLucideIcons() {
    if (window.__lucideGebunden) {
        return;
    }

    window.__lucideGebunden = true;

    zeichne();

    document.addEventListener('DOMContentLoaded', zeichne);
    document.addEventListener('livewire:navigated', zeichne);
    document.addEventListener('livewire:morph.updated', zeichne);
}
