/*
 * Google Tag Manager und das Consent-Update — ohne Inline-Skript (#21)
 * ------------------------------------------------------------------
 * GA4 laedt NICHT mehr hier. Seit der Umstellung auf Consent Mode v2
 * (Advanced) steht der GA4-Block fest im <head>:
 * resources/views/components/analytics-meta.blade.php gibt den
 * Consent-Default ('denied'), gtag.js und gtag('config', …) in dieser
 * Reihenfolge aus. gtag.js laedt dort IMMER — die Reihenfolge laesst sich aus
 * einem Bundle heraus nicht garantieren, und lief gtag.js vor dem Default,
 * ginge der erste Hit mit Cookies raus.
 *
 * Hier bleibt der Tag Manager, der weiter erst nach der Einwilligung laedt,
 * und das 'consent update' nach einer Zustimmung.
 *
 * Markup (alles aus <x-analytics-meta>):
 *
 *   <meta name="ga-id"           content="G-XXXXXXX">   GA4-Messkennung
 *   <meta name="gtm-id"          content="GTM-XXXXXX">  Tag-Manager-Behaelter
 *   <meta name="analytics-defer" content="1500">        ms nach 'load' (optional)
 *
 * Die Kennungen loest App\Analytics\AnalyticsResolver auf; steht kein Meta im
 * Dokument, messt das Portal nicht und dieses Modul tut nichts.
 *
 * Einwilligung
 * ------------
 * GA4 laeuft vor der Einwilligung cookielos (Consent Mode Advanced). Fuer GTM
 * gilt weiter: vorher geht kein Request zu Google. Gelesen wird die
 * Entscheidung aus den Cookies, die beide Cookie-Hinweise setzen
 * (resources/js/app.js, Alpine-Komponente cookieConsent, und
 * themes/sun-v2/js/modules/cookie-consent.js):
 *
 *   cookie_consent_statistics=1  -> analytics_storage granted
 *   cookie_consent_marketing=1   -> ad_storage, ad_user_data, ad_personalization
 *
 * Wer waehrend des Besuchs zustimmt, wird ueber die Ereignisse des Adapters
 * erfasst (resources/js/consent-adapter.js, 'consent:statistics' und
 * 'consent:marketing') — beide Cookie-Hinweise melden ihre Entscheidung dort
 * ueber melde(). Ueber denselben Weg kommt auch eine Entscheidung aus einem
 * frueheren Besuch nach.
 *
 * Das 'consent update' laeuft NUR bei einer Zustimmung (#3). Bei 'Nur
 * notwendige' bleibt der Default 'denied' aus dem <head> stehen; ein Update
 * mit 'denied' waere derselbe Zustand und bloss ein zusaetzlicher Aufruf.
 */

import { beiEntscheidung } from './consent-adapter';

function metaInhalt(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    const wert = el?.getAttribute('content')?.trim();

    return wert ? wert : null;
}

function cookieWert(name) {
    const treffer = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));

    return treffer ? decodeURIComponent(treffer[1]) : null;
}

/** Die gespeicherte Entscheidung. Ohne Entscheidung ist beides false. */
function einwilligung() {
    return {
        statistik: cookieWert('cookie_consent_statistics') === '1',
        marketing: cookieWert('cookie_consent_marketing') === '1',
    };
}

function ladeSkript(src, datensatz = {}) {
    const el = document.createElement('script');
    el.async = true;
    el.src = src;

    Object.entries(datensatz).forEach(([schluessel, wert]) => {
        el.dataset[schluessel] = wert;
    });

    document.head.appendChild(el);
}

/** Entweder sofort oder 'defer' Millisekunden nach dem Seitenaufbau. */
function spaeter(verzoegerung, tun) {
    if (verzoegerung === null) {
        tun();

        return;
    }

    const anstossen = () => window.setTimeout(tun, verzoegerung);

    if (document.readyState === 'complete') {
        anstossen();

        return;
    }

    window.addEventListener('load', anstossen, { once: true });
}

function gtag() {
    window.dataLayer = window.dataLayer || [];
    // Bewusst kein Pfeil und kein Rest-Parameter: gtag.js wertet 'arguments'
    // aus, der Aufruf muss so aussehen wie im Google-Schnipsel.
    window.dataLayer.push(arguments);
}

function erlaubnisse(wahl) {
    return {
        analytics_storage: wahl.statistik ? 'granted' : 'denied',
        ad_storage: wahl.marketing ? 'granted' : 'denied',
        ad_user_data: wahl.marketing ? 'granted' : 'denied',
        ad_personalization: wahl.marketing ? 'granted' : 'denied',
        // Der Cookie-Hinweis kennt keine eigene Kategorie 'Funktional'. Bei
        // 'Alle akzeptieren' sind beide Kategorien an, damit steht
        // functionality_storage wie gefordert auf 'granted'; wer in den
        // Einstellungen nur eine Kategorie waehlt, willigt ebenfalls ueber das
        // Notwendige hinaus ein (#3).
        functionality_storage: (wahl.statistik || wahl.marketing) ? 'granted' : 'denied',
    };
}

function ladeGtm(gtmId, verzoegerung) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });

    spaeter(verzoegerung, () => {
        ladeSkript(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtmId)}`, { gtm: 'true' });
    });
}

export function initAnalytics() {
    if (window.__analyticsEingerichtet) {
        return;
    }

    const gaId = metaInhalt('ga-id');
    const gtmId = metaInhalt('gtm-id');

    if (gaId === null && gtmId === null) {
        return;
    }

    window.__analyticsEingerichtet = true;

    const roh = metaInhalt('analytics-defer');
    const verzoegerung = roh === null ? null : Math.max(0, Number.parseInt(roh, 10) || 0);

    // gtag() steht schon als globale Funktion im <head> (der Block aus
    // <x-analytics-meta>); auf Seiten ohne GA4-Kennung legt dieses Modul sie an,
    // damit der dataLayer auch dort ein Ziel hat.
    window.gtag = window.gtag || gtag;

    let letztesUpdate = null;
    let gtmGeladen = false;

    /**
     * gtag.js liegt bereits im Dokument und laeuft mit 'denied'; mit der
     * Zustimmung wird der Standard nur hochgezogen — kein Skript wird
     * nachgeladen. Ohne Zustimmung (nur Notwendiges) passiert hier nichts.
     */
    const aktualisiereConsent = (wahl) => {
        if (gaId === null || (! wahl.statistik && ! wahl.marketing)) {
            return;
        }

        const signale = erlaubnisse(wahl);
        const schluessel = JSON.stringify(signale);

        // Beide Kategorien melden getrennt; derselbe Zustand reicht einmal.
        if (schluessel === letztesUpdate) {
            return;
        }

        letztesUpdate = schluessel;

        window.gtag('consent', 'update', signale);
    };

    const ladeGtmEinmal = (wahl) => {
        if (gtmId === null || gtmGeladen || ! wahl.statistik) {
            return;
        }

        gtmGeladen = true;

        ladeGtm(gtmId, verzoegerung);
    };

    const entscheidung = () => {
        const wahl = einwilligung();

        aktualisiereConsent(wahl);
        ladeGtmEinmal(wahl);
    };

    // Beide Kategorien: eine Zustimmung allein zur Werbung muss ad_* ebenso
    // hochziehen wie eine zur Statistik analytics_storage. Der Adapter ruft
    // sofort zurueck, wenn die Entscheidung schon vorliegt.
    beiEntscheidung('statistics', entscheidung);
    beiEntscheidung('marketing', entscheidung);
}
