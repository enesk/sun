/*
 * Theme sun-v2 — Verhalten der Portalseiten
 * ------------------------------------------------------------------
 * Bewusst ohne eigenes Alpine: gebraucht werden nur Mobilmenue,
 * Cookie-Hinweis, die aufklappbaren Filter der Suche, "Mehr lesen" auf dem
 * Firmenprofil, der Umschalter der Uebernahme-Seite und der Anfrage-Dialog
 * (Funnel-Runtime-API des Leadsystems). Alles haengt an
 * data-Attributen im Markup. Livewire (samt Alpine) laedt nur dort, wo eine
 * Livewire-Komponente steht, etwa Bewertungs- und Uebernahmeformular.
 */

import { initMobileMenu } from './modules/mobile-menu';
import { initCookieConsent } from './modules/cookie-consent';
import { initDisclosures } from './modules/disclosure';
import { initReadMore } from './modules/read-more';
import { initTabs } from './modules/tabs';
import { initLeadDialog } from './modules/lead-dialog';

function boot() {
    initMobileMenu();
    initCookieConsent();
    initDisclosures();
    initReadMore();
    initTabs();
    initLeadDialog();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
