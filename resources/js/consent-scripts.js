/*
 * Zurueckgehaltenes Fremd-HTML nach der Einwilligung einsetzen (#28)
 * ------------------------------------------------------------------
 * Gehoert zu resources/views/components/tracking-scripts.blade.php. Liegt beim
 * Seitenaufbau keine Entscheidung vor, gibt der Server das Tracking-HTML aus
 * der Verwaltung (config('app.tracking_scripts')) in einem
 *
 *   <template data-consent-script="statistics|marketing">
 *
 * aus. Ein <template> ist inert: der Browser laedt daraus nichts, es geht also
 * kein Request hinaus, bevor der Besucher entschieden hat. Kommt die
 * Einwilligung im laufenden Besuch, wird das Fragment an genau der Stelle
 * eingesetzt, an der die Vorlage steht — meist im <head>.
 *
 * Bei ausdruecklicher Ablehnung gibt es nichts zu tun: dann steht gar keine
 * Vorlage im Dokument (der Server gibt sie nicht aus).
 *
 * Woran die Entscheidung haengt, weiss nur resources/js/consent-adapter.js.
 * Der Platzhalter ist absichtlich allgemein gehalten — `data-consent-script`
 * traegt die Kategorie selbst, weitere Bloecke brauchen hier keine Zeile.
 *
 * Die Kopie von neuesSkript() in resources/js/ads.js bleibt dort: die Werbung
 * ist eine eigene Spur mit eigenen Ankern, und beide sollen sich nicht
 * gegenseitig mitziehen.
 */

import { beiEntscheidung, GRANTED, KATEGORIEN } from './consent-adapter';

const VORLAGE = 'template[data-consent-script]';

/**
 * Ein aus <template> geklontes <script> fuehrt der Browser nicht aus — und das
 * Nonce kann beim Klonen verloren gehen. Darum jedes Skript neu anlegen:
 * Attribute uebernehmen, Nonce ausdruecklich setzen (sonst blockiert die
 * Policy den Schnipsel), Inhalt uebertragen.
 */
function neuesSkript(alt) {
    const neu = document.createElement('script');

    [...alt.attributes].forEach((attr) => neu.setAttribute(attr.name, attr.value));

    // nonce liegt als Eigenschaft, nicht zwingend als Attribut vor.
    const nonce = alt.nonce || alt.getAttribute('nonce');

    if (nonce) {
        neu.setAttribute('nonce', nonce);
        neu.nonce = nonce;
    }

    neu.text = alt.text;

    return neu;
}

function setzeEin(vorlage) {
    const teil = vorlage.content.cloneNode(true);

    teil.querySelectorAll('script').forEach((skript) => {
        skript.replaceWith(neuesSkript(skript));
    });

    vorlage.parentNode.replaceChild(teil, vorlage);
}

function setzeKategorieEin(kategorie) {
    document.querySelectorAll(VORLAGE).forEach((vorlage) => {
        if (vorlage.dataset.consentScript === kategorie) {
            setzeEin(vorlage);
        }
    });
}

export function initConsentScripts() {
    if (document.querySelector(VORLAGE) === null) {
        return;
    }

    // Jede Kategorie einzeln: ein Dokument kann Vorlagen beider Kategorien
    // tragen, und sie werden unabhaengig voneinander freigegeben.
    Object.keys(KATEGORIEN).forEach((kategorie) => {
        beiEntscheidung(kategorie, (zustand) => {
            if (zustand === GRANTED) {
                setzeKategorieEin(kategorie);
            }
        });
    });
}
