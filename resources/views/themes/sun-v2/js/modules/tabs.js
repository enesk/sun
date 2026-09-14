/**
 * Umschalter mit role="tab" (Seite "Ist das dein Betrieb?"): [data-tab]
 * blendet das Panel [data-panel] mit gleichem Namen ein. Ein Tab mit
 * data-tab-hash wird direkt aktiv, wenn die Adresse auf diesen Anker endet
 * (z. B. #aendern aus "Änderung vorschlagen" im Profil).
 */
const ACTIVE = ['bg-white', 'text-zinc-900', 'shadow-sm'];
const INACTIVE = ['text-zinc-500'];

export function initTabs() {
    const tabs = [...document.querySelectorAll('[data-tab]')];

    if (tabs.length === 0) {
        return;
    }

    const panels = [...document.querySelectorAll('[data-panel]')];

    const activate = (name) => {
        tabs.forEach((tab) => {
            const on = tab.dataset.tab === name;

            tab.setAttribute('aria-selected', String(on));
            ACTIVE.forEach((cls) => tab.classList.toggle(cls, on));
            INACTIVE.forEach((cls) => tab.classList.toggle(cls, !on));
        });

        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.panel !== name));
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.tab)));

    const fromHash = tabs.find((tab) => tab.dataset.tabHash && `#${tab.dataset.tabHash}` === window.location.hash);

    if (fromHash) {
        activate(fromHash.dataset.tab);
    }
}
