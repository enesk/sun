/*
 * Anmeldekarte: Umschalter zwischen Anmelden, Passwort vergessen und
 * "Link gesendet" (Vorlage elektrikerportal-login.html).
 *
 * Stand vorher als Inline-Block in auth/login.blade.php; seit #21 haengt es an
 * Markup, damit die CSP ohne 'unsafe-inline' durchgesetzt werden kann:
 *
 *   <div data-panel-group>
 *     <div data-panel="login">…</div>
 *     <a data-goto="reset">…</a>
 *
 * Der Bereich "sent" ist flex, nicht block — darum die Sonderbehandlung.
 */

export function initLoginPanels() {
    document.querySelectorAll('[data-panel-group]').forEach((gruppe) => {
        if (gruppe.dataset.panelsGebunden === '1') {
            return;
        }

        gruppe.dataset.panelsGebunden = '1';

        const zeige = (name) => {
            gruppe.querySelectorAll('[data-panel]').forEach((bereich) => {
                const aktiv = bereich.dataset.panel === name;
                bereich.classList.toggle('hidden', !aktiv);

                if (bereich.dataset.panel === 'sent') {
                    bereich.classList.toggle('flex', aktiv);
                }
            });

            gruppe.querySelector(`[data-panel="${name}"] input:not([type=hidden])`)?.focus();
        };

        gruppe.querySelectorAll('[data-goto]').forEach((verweis) => {
            verweis.addEventListener('click', (event) => {
                event.preventDefault();
                zeige(verweis.dataset.goto);
            });
        });
    });
}
