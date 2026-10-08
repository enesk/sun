/*
 * Passwort ein-/ausblenden (Login, Passwort setzen, Passwort bestaetigen).
 *
 * Stand vorher als Inline-Block in den Auth-Views; seit #21 haengt es an
 * Markup, damit die CSP ohne 'unsafe-inline' durchgesetzt werden kann:
 *
 *   <button data-toggle-pass data-pass-scope="#resetCard"
 *           data-label-show="…" data-label-hide="…">
 *
 * Ohne data-pass-scope gilt das umgebende <form>, sonst der gefundene
 * Bereich. Umgeschaltet werden alle [data-pass] darin — die Seite "Neues
 * Passwort" hat zwei Felder und schaltet beide gemeinsam.
 */

function felder(knopf) {
    const auswahl = knopf.dataset.passScope;
    const bereich = (auswahl ? document.querySelector(auswahl) : null) ?? knopf.closest('form') ?? document;

    return [...bereich.querySelectorAll('[data-pass]')];
}

export function initPasswordToggle() {
    document.querySelectorAll('[data-toggle-pass]').forEach((knopf) => {
        if (knopf.dataset.toggleGebunden === '1') {
            return;
        }

        knopf.dataset.toggleGebunden = '1';

        knopf.addEventListener('click', () => {
            const ziele = felder(knopf);

            if (ziele.length === 0) {
                return;
            }

            const versteckt = ziele[0].type === 'password';

            ziele.forEach((feld) => {
                feld.type = versteckt ? 'text' : 'password';
            });

            const label = versteckt ? knopf.dataset.labelHide : knopf.dataset.labelShow;

            if (label) {
                knopf.setAttribute('aria-label', label);
            }
        });
    });
}
