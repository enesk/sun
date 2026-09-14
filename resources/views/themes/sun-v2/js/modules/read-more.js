/**
 * "Mehr lesen" auf dem Firmenprofil: Schalter [data-toggle-more] mit
 * aria-controls blendet im Zielelement alle [data-more] ein und aus.
 */
export function initReadMore() {
    document.querySelectorAll('[data-toggle-more]').forEach((button) => {
        const target = document.getElementById(button.getAttribute('aria-controls'));

        if (!target) {
            return;
        }

        button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') === 'true';

            target.querySelectorAll('[data-more]').forEach((paragraph) => paragraph.classList.toggle('hidden', open));
            button.setAttribute('aria-expanded', String(!open));
            button.textContent = open ? 'Mehr lesen' : 'Weniger anzeigen';
        });
    });
}
