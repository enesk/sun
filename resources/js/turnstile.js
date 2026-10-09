/*
 * Cloudflare Turnstile — Verhalten im Browser (#5)
 * ------------------------------------------------------------------
 * Gehoert zu <x-turnstile /> (App\Turnstile\View\Components\Turnstile) und wird
 * nur auf Seiten geladen, auf denen die Komponente rendert: sie schiebt Modul
 * und api.js in den Stack 'scripts'.
 *
 * Explizites Rendering (api.js mit ?render=explicit&onload=onTurnstileLoad):
 * nur so kennen wir die Widget-Id und koennen zuruecksetzen, und nur so
 * zerstoert ein Livewire-Re-Render das Widget nicht.
 *
 * Kein Alpine, keine Inline-Handler (CSP ohne unsafe-inline, #11) — alles
 * haengt an data-Attributen, wie die uebrigen JS-Module des Themes.
 *
 * Tokens sind bei Cloudflare einmal verwendbar. Darum wird nach jedem
 * Formularlauf, der ein Token mitgenommen hat, zurueckgesetzt — sonst
 * antwortet Siteverify beim zweiten Versuch mit timeout-or-duplicate.
 */

const WURZEL = '[data-turnstile-root]';

/** Wurzel-Element -> Widget-Id von Cloudflare. */
const widgets = new WeakMap();

function istBereit() {
    return typeof window.turnstile !== 'undefined' && typeof window.turnstile.render === 'function';
}

function wurzeln(bereich = document) {
    if (!bereich || typeof bereich.querySelectorAll !== 'function') {
        return [];
    }

    const treffer = Array.from(bereich.querySelectorAll(WURZEL));

    if (typeof bereich.matches === 'function' && bereich.matches(WURZEL)) {
        treffer.unshift(bereich);
    }

    return treffer;
}

function eingabefeld(wurzel) {
    return wurzel.querySelector('[data-turnstile-input]');
}

function hatToken(wurzel) {
    const feld = eingabefeld(wurzel);

    return !!feld && feld.value !== '';
}

/** Livewire-Komponente, in der das Widget steckt — oder null im Blade-Formular. */
function livewireKomponente(wurzel) {
    const el = wurzel.closest('[wire\\:id]');

    if (!el || !window.Livewire) {
        return null;
    }

    return window.Livewire.find(el.getAttribute('wire:id')) ?? null;
}

/**
 * Submit sperren, bis ein Token vorliegt. Nur Knoepfe, die wir selbst gesperrt
 * haben, werden spaeter wieder freigegeben — fremde disabled-Zustaende bleiben
 * unberuehrt: wire:loading.attr waehrend eines Requests, und vor allem das
 * @disabled($done) der Firmeneintragung, das nach dem Abschluss einen zweiten
 * Eintrag verhindert (#16). Ein Gate, das auch die freigibt, haette den
 * Dublettenschutz ausgehebelt.
 */
function schalte(wurzel, frei) {
    if (wurzel.dataset.gate !== 'submit') {
        return;
    }

    const formular = wurzel.closest('form');

    if (!formular) {
        return;
    }

    formular.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach((knopf) => {
        if (frei) {
            if (knopf.dataset.turnstileGesperrt === '1') {
                delete knopf.dataset.turnstileGesperrt;
                knopf.disabled = false;
                knopf.removeAttribute('aria-disabled');
            }

            return;
        }

        if (!knopf.disabled) {
            knopf.dataset.turnstileGesperrt = '1';
            knopf.disabled = true;
            knopf.setAttribute('aria-disabled', 'true');
        }
    });
}

function uebernimm(wurzel, token) {
    const feld = eingabefeld(wurzel);

    if (feld) {
        feld.value = token;
    }

    const modell = wurzel.dataset.wireModel;

    if (modell) {
        // Dritter Parameter false: nur merken, nicht sofort zum Server schicken.
        // Das Token reist mit dem naechsten Commit, also mit dem Submit.
        livewireKomponente(wurzel)?.set(modell, token, false);
    }

    schalte(wurzel, true);
}

function leere(wurzel) {
    const feld = eingabefeld(wurzel);

    if (feld) {
        feld.value = '';
    }

    const modell = wurzel.dataset.wireModel;

    if (modell) {
        livewireKomponente(wurzel)?.set(modell, '', false);
    }

    schalte(wurzel, false);
}

/** Token verwerfen und eine frische Aufgabe anfordern. */
function setzeZurueck(wurzel) {
    leere(wurzel);

    const id = widgets.get(wurzel);

    if (!istBereit()) {
        return;
    }

    if (id === undefined) {
        // Noch kein Widget da (z. B. Schritt gerade eingeblendet): neu rendern.
        rendere(wurzel);

        return;
    }

    window.turnstile.reset(id);
}

function rendere(wurzel) {
    if (!istBereit() || widgets.has(wurzel)) {
        return;
    }

    const halter = wurzel.querySelector('[data-turnstile-widget]') ?? wurzel;

    const id = window.turnstile.render(halter, {
        sitekey: wurzel.dataset.sitekey,
        action: wurzel.dataset.action,
        theme: wurzel.dataset.theme || 'auto',
        language: wurzel.dataset.language || 'de',
        size: wurzel.dataset.size || 'normal',
        appearance: wurzel.dataset.appearance || 'always',
        // Cloudflare legt sonst ein zweites Feld cf-turnstile-response an —
        // zwei gleichnamige Felder in einem Formular, und PHP nimmt das letzte.
        // Das Token verwaltet ausschliesslich [data-turnstile-input].
        'response-field': false,
        callback: (token) => uebernimm(wurzel, token),
        // Token ist nach wenigen Minuten ungueltig: wegwerfen und neu stellen.
        'expired-callback': () => setzeZurueck(wurzel),
        'timeout-callback': () => setzeZurueck(wurzel),
        // Netzproblem bei Cloudflare: Submit bleibt gesperrt, die Rule
        // entscheidet serverseitig ueber den Fail-Mode (docs/turnstile.md §5).
        'error-callback': () => {
            leere(wurzel);

            return false;
        },
    });

    if (id !== undefined && id !== null) {
        widgets.set(wurzel, id);
    }

    schalte(wurzel, false);
}

function boote(bereich = document) {
    wurzeln(bereich).forEach((wurzel) => {
        rendere(wurzel);
        // Ein Morph ersetzt den Submit-Knopf samt seinem disabled, waehrend das
        // Widget im ignorierten Bereich stehen bleibt. Die Sperre deshalb nach
        // jedem Umlauf am Token-Stand ausrichten — sonst ist der Knopf nach dem
        // ersten Livewire-Request frei, obwohl kein Token vorliegt (#53).
        schalte(wurzel, hatToken(wurzel));
    });
}

/* --- Livewire ------------------------------------------------------------- */

let livewireVerbunden = false;

function haengeAnLivewire() {
    if (livewireVerbunden || !window.Livewire || typeof window.Livewire.hook !== 'function') {
        return;
    }

    livewireVerbunden = true;

    window.Livewire.hook('commit', ({ component, succeed, fail }) => {
        const verbraucht = wurzeln(component.el).filter(hatToken);

        const nacharbeiten = () => {
            // Ein Tick spaeter: dann ist der Morph durch und ein neu
            // eingeblendeter Schritt steht im DOM.
            setTimeout(() => {
                // Erst neue Widgets (ein Schritt kann gerade eingeblendet
                // worden sein), dann die verbrauchten Tokens erneuern.
                boote();
                verbraucht.forEach(setzeZurueck);
            }, 0);
        };

        succeed(nacharbeiten);
        fail(nacharbeiten);
    });
}

/* --- Einstieg ------------------------------------------------------------- */

function start() {
    boote();
    haengeAnLivewire();
}

// api.js ruft das auf, sobald es geladen ist.
window.onTurnstileLoad = start;

// Umgekehrte Reihenfolge (api.js war schneller): selbst anfangen.
if (istBereit()) {
    start();
}

document.addEventListener('livewire:init', haengeAnLivewire);
document.addEventListener('livewire:navigated', () => boote());

// Serverseitig ausgeloestes Zuruecksetzen: $this->dispatch('turnstile:reset')
// bzw. window.dispatchEvent(new CustomEvent('turnstile:reset')). Mit
// detail.action nur die Widgets dieser Aktion.
window.addEventListener('turnstile:reset', (event) => {
    const aktion = event.detail?.action ?? null;

    wurzeln().filter((wurzel) => aktion === null || wurzel.dataset.action === aktion).forEach(setzeZurueck);
});
