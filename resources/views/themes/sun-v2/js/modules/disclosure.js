/**
 * Auf- und Zuklappen fuer Filter-Pillen der Suche.
 *
 * Schalter mit [data-disclosure] blendet das Element aus aria-controls ein
 * und aus. Die Panels liegen bewusst ausserhalb der horizontal scrollbaren
 * Filterleiste — ein absolut positioniertes Menue darin wuerde abgeschnitten.
 * Schalter mit gleichem data-disclosure-Wert bilden eine Gruppe: es ist
 * immer hoechstens ein Panel offen.
 */
export function initDisclosures() {
    const buttons = [...document.querySelectorAll('[data-disclosure]')];

    buttons.forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));

        if (!panel) {
            return;
        }

        button.addEventListener('click', () => {
            const open = panel.hidden;

            buttons
                .filter((other) => other !== button && other.dataset.disclosure === button.dataset.disclosure)
                .forEach((other) => {
                    other.setAttribute('aria-expanded', 'false');
                    const otherPanel = document.getElementById(other.getAttribute('aria-controls'));

                    if (otherPanel) {
                        otherPanel.hidden = true;
                    }
                });

            panel.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
        });
    });
}
