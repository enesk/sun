/*
 * Google Analytics 4 und Tag Manager — ohne Inline-Skript (#21)
 * ------------------------------------------------------------------
 * Vorher stand gtag() als Inline-Block in drei analytics.blade.php. Mit
 * durchgesetzter CSP (config/csp.php, 'script-src' ohne 'unsafe-inline')
 * laeuft das nicht mehr, also haengt die Einrichtung jetzt wie die uebrigen
 * Module an Markup:
 *
 *   <meta name="ga-id"           content="G-XXXXXXX">   GA4-Messkennung
 *   <meta name="gtm-id"          content="GTM-XXXXXX">  Tag-Manager-Behaelter
 *   <meta name="analytics-defer" content="1500">        ms nach 'load' (optional)
 *
 * Ohne 'analytics-defer' laedt gtag.js sofort. Mit — so macht es sun-v2 —
 * landen die Aufrufe erst im dataLayer und das ~170 KB grosse Skript kommt
 * nach dem Seitenaufbau nach, damit der LCP nicht darauf wartet.
 *
 * Der Consent-Standard bleibt wie zuvor 'granted': der Cookie-Hinweis der
 * Portale informiert, er schaltet GA nicht ab (siehe resources/js/app.js,
 * Alpine-Komponente cookieConsent).
 */

function metaInhalt(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    const wert = el?.getAttribute('content')?.trim();

    return wert ? wert : null;
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

function richteGaEin(gaId, verzoegerung) {
    window.dataLayer = window.dataLayer || [];

    // Bewusst kein Pfeil und kein Rest-Parameter: gtag.js wertet 'arguments'
    // aus, der Aufruf muss so aussehen wie im Google-Schnipsel.
    function gtag() {
        window.dataLayer.push(arguments);
    }

    window.gtag = window.gtag || gtag;

    gtag('consent', 'default', {
        analytics_storage: 'granted',
        ad_storage: 'granted',
        ad_user_data: 'granted',
        ad_personalization: 'granted',
    });

    gtag('js', new Date());
    gtag('config', gaId, { anonymize_ip: true });

    spaeter(verzoegerung, () => {
        ladeSkript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`, { ga: 'true' });
    });
}

function richteGtmEin(gtmId, verzoegerung) {
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

    if (gaId !== null) {
        richteGaEin(gaId, verzoegerung);
    }

    if (gtmId !== null) {
        richteGtmEin(gtmId, verzoegerung);
    }
}
