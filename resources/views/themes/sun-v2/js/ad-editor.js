/*
 * Editor-Modus der Werbeplaetze — Bruecke zum Panel (Ticket #6)
 * ------------------------------------------------------------------
 * Laedt NUR im Editor-Modus: das Layout bindet dieses Bundle allein dann ein,
 * wenn der signierte Token gilt (themes/sun-v2/views/layouts/sun.blade.php).
 * Ein normaler Besucher bekommt davon kein Byte — darum ein eigener
 * Vite-Eintrag und kein Modul von app.js.
 *
 * Gegenseite ist die Filament-Seite „Werbeplaetze" (#7), die diese Seite in
 * einem iframe zeigt. Beide Seiten liegen auf **verschiedenen** Herkuenften
 * (Portal: Tenant-Domain, Panel: zentrale Domain). Darum:
 *
 *  * gesendet wird immer mit ausdruecklicher targetOrigin — nie '*',
 *  * empfangen wird nur von genau dieser Herkunft; alles andere wird
 *    verworfen, ohne Antwort.
 *
 * Die Herkunft steht in config/ad-slots.php ('editor_origin') und kommt als
 * data-Attribut am <body> an. Fehlt sie, laeuft die Bruecke nicht an — eine
 * Vorschau ohne Gegenseite ist harmlos, eine ohne Herkunftspruefung nicht.
 *
 * Protokoll: docs/ad-slots.md 6.2. Jede Nachricht ist
 * { type, payload, catalogVersion }; eine Nachricht mit abweichender
 * catalogVersion wird verworfen (das Panel laedt dann neu).
 */

const FRAME = '[data-ad-editor-frame]';
const HIGHLIGHT_MS = 2000;

let origin = '';
let catalogVersion = 0;
let pageType = '';
let highlightTimer = null;

/** Sichtbar heisst: nicht display:none — die Geraetetrennung der Container. */
function istSichtbar(el) {
    return el.getClientRects().length > 0;
}

function rahmen() {
    return Array.from(document.querySelectorAll(FRAME));
}

/**
 * Geraeteklasse der Vorschau. Abgeleitet aus den sichtbaren Containern und
 * nicht aus der Fensterbreite: welcher Container sichtbar ist, entscheidet
 * der lg-Haltepunkt des Themes — dieselbe Grenze, die auch die Belegung je
 * Geraet bestimmt. Nur wenn alle sichtbaren Anker fuer beide Geraete gelten
 * ('all'), bleibt die Breite als Auskunft uebrig.
 */
function geraet() {
    const sichtbar = rahmen().filter(istSichtbar);
    const gebunden = sichtbar.find((el) => el.dataset.adSlotDevice !== 'all');

    if (gebunden) {
        return gebunden.dataset.adSlotDevice;
    }

    return window.matchMedia('(min-width: 64rem)').matches ? 'desktop' : 'mobile';
}

function slotListe() {
    return rahmen()
        .filter(istSichtbar)
        .map((el) => ({
            key: el.dataset.adSlotKey,
            label: el.dataset.adSlotLabel,
            device: el.dataset.adSlotDevice,
            minHeight: Number(el.dataset.adSlotMinHeight || 0),
            filled: el.dataset.adSlotFilled === '1',
            placementId: el.dataset.adSlotPlacement ? Number(el.dataset.adSlotPlacement) : null,
        }));
}

function sende(type, payload) {
    if (!origin) {
        return;
    }

    window.parent.postMessage({ type, payload, catalogVersion }, origin);
}

function meldeSlots() {
    sende('slots:ready', { pageType, device: geraet(), slots: slotListe() });
}

function hebeAuf() {
    if (highlightTimer) {
        window.clearTimeout(highlightTimer);
        highlightTimer = null;
    }

    rahmen().forEach((el) => delete el.dataset.adEditorHighlight);
}

/**
 * Hervorheben und in den Blick holen. Zwei Sekunden, dann von selbst zurueck:
 * ein dauerhaft hervorgehobener Rahmen wandert sonst unbemerkt mit, wenn im
 * Panel schon der naechste Platz bearbeitet wird.
 */
function hebeHervor(key) {
    hebeAuf();

    if (!key) {
        return;
    }

    const ziel = rahmen().find((el) => el.dataset.adSlotKey === key && istSichtbar(el));

    if (!ziel) {
        return;
    }

    ziel.dataset.adEditorHighlight = '1';
    ziel.scrollIntoView({ behavior: 'smooth', block: 'center' });

    highlightTimer = window.setTimeout(hebeAuf, HIGHLIGHT_MS);
}

/**
 * Zustand eines Rahmens nachziehen, ohne Reload — die Scrollposition im
 * iframe bleibt, und das Panel muss die Vorschau nicht neu aufbauen. Das
 * Werbemittel selbst erscheint hier nie: im Editor-Modus rendert kein
 * Provider.
 */
function aktualisiere({ key, filled, label }) {
    rahmen()
        .filter((el) => el.dataset.adSlotKey === key)
        .forEach((el) => {
            el.dataset.adSlotFilled = filled ? '1' : '0';

            const zustand = el.querySelector('[data-ad-slot-state]');

            if (zustand) {
                zustand.textContent = filled ? (label || '') : zustand.dataset.adEmptyLabel || zustand.textContent;
            }

            el.classList.toggle('border-emerald-500', Boolean(filled));
            el.classList.toggle('bg-emerald-50/60', Boolean(filled));
            el.classList.toggle('border-dashed', !filled);
            el.classList.toggle('border-zinc-400', !filled);

            if (zustand) {
                zustand.classList.toggle('bg-emerald-600', Boolean(filled));
                zustand.classList.toggle('text-white', Boolean(filled));
                zustand.classList.toggle('bg-white', !filled);
                zustand.classList.toggle('text-zinc-500', !filled);
                zustand.classList.toggle('ring-1', !filled);
                zustand.classList.toggle('ring-zinc-300', !filled);
            }
        });
}

function empfange(event) {
    if (event.origin !== origin) {
        return;
    }

    const nachricht = event.data;

    if (!nachricht || typeof nachricht !== 'object') {
        return;
    }

    // Anderer Katalogstand im Panel: die Schluessel der Nachricht muessen
    // nicht mehr zu den Ankern dieser Seite passen. Verworfen statt geraten.
    if (Number(nachricht.catalogVersion) !== catalogVersion) {
        return;
    }

    const payload = nachricht.payload || {};

    if (nachricht.type === 'slot:highlight') {
        hebeHervor(payload.key || null);

        return;
    }

    if (nachricht.type === 'placement:updated') {
        aktualisiere(payload);

        return;
    }

    if (nachricht.type === 'device:changed') {
        // Aendert bewusst nichts am Rendering: die echte Umschaltung ist die
        // Breite des iframes (docs/ad-slots.md 6.2). Gemeldet wird nur die
        // neue Ankerliste, weil sie geraeteabhaengig ist.
        meldeSlots();
    }
}

export function initAdEditor() {
    const daten = document.body.dataset;

    origin = daten.adEditorOrigin || '';
    catalogVersion = Number(daten.adCatalogVersion || 0);
    pageType = daten.adPageType || '';

    if (!origin) {
        return;
    }

    document.addEventListener('click', (event) => {
        const ziel = event.target.closest(FRAME);

        if (!ziel) {
            return;
        }

        event.preventDefault();
        sende('slot:clicked', {
            key: ziel.dataset.adSlotKey,
            device: ziel.dataset.adSlotDevice,
            pageType,
        });
    });

    window.addEventListener('message', empfange);
    meldeSlots();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdEditor);
} else {
    initAdEditor();
}
