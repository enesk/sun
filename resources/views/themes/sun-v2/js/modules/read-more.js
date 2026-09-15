/**
 * "Mehr lesen" auf dem Firmenprofil: Schalter [data-toggle-more] mit
 * aria-controls blendet im Zielelement alle [data-more] ein und aus.
 * Die Texte stehen in data-label-more/data-label-less am Schalter.
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
            // Beschriftung aus portal.profile.about.* (data-label-more/-less)
            button.textContent = open ? button.dataset.labelMore : button.dataset.labelLess;
        });
    });
}
