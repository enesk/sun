/**
 * Mobilmenue aus der Vorlage: Schalter mit [data-menu-toggle] blendet das
 * Element mit der ID aus aria-controls ein und aus.
 */
export function initMobileMenu() {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const menu = document.getElementById(button.getAttribute('aria-controls'));

        if (!menu) {
            return;
        }

        button.addEventListener('click', () => {
            const open = menu.classList.toggle('hidden') === false;

            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
        });
    });
}
