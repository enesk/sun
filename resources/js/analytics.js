/*
 * Google Analytics 4 und Tag Manager — ohne Inline-Skript (#21), erst nach
 * Einwilligung (#14)
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
 * Die Kennungen loest App\Analytics\AnalyticsResolver auf; steht kein Meta im
 * Dokument, messt das Portal nicht und dieses Modul tut nichts.
 *
 * Ohne 'analytics-defer' laedt gtag.js sofort nach der Einwilligung. Mit — so
 * macht es sun-v2 — landen die Aufrufe erst im dataLayer und das ~170 KB
 * grosse Skript kommt nach dem Seitenaufbau nach, damit der LCP nicht darauf
 * wartet.
 *
 * Einwilligung (#14)
 * ------------------
 * Der Consent-Standard ist 'denied', und vor der Einwilligung geht KEIN
 * Request zu Google: gtag.js und gtm.js werden erst geladen, wenn die
 * Statistik-Einwilligung vorliegt. Vorher stand hier 'granted' und der
 * Cookie-Hinweis war wirkungslos — die Datenschutzerklaerung aller Portale
 * sagt aber ausdruecklich "Analyse-Cookies (nur mit Einwilligung),
 * Rechtsgrundlage Art. 6 Abs. 1 lit. a DSGVO".
 *
 * Gelesen wird die Entscheidung aus den Cookies, die beide Hinweise setzen
 * (resources/js/app.js, Alpine-Komponente cookieConsent, und
 * themes/sun-v2/js/modules/cookie-consent.js):
 *
 *   cookie_consent_statistics=1  -> analytics_storage granted, Skript laedt
 *   cookie_consent_marketing=1   -> ad_storage, ad_user_data, ad_personalization
 *
 * Wer waehrend des Besuchs zustimmt, wird ueber das Ereignis
 * 'cookie-consent-statistics' erfasst; das senden beide Hinweise.
 */

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
    };
}

/**
 * Vorbereiten, ohne etwas zu laden: Standard 'denied' und die Aufrufe, die
 * gtag.js beim Laden aus dem dataLayer nachholt. Ohne Einwilligung bleibt es
 * dabei — der dataLayer ist eine Liste im Speicher, kein Request.
 */
function bereiteGaVor(gaId) {
    window.gtag = window.gtag || gtag;

    gtag('consent', 'default', erlaubnisse({ statistik: false, marketing: false }));
    gtag('js', new Date());
    gtag('config', gaId, { anonymize_ip: true });
}

function ladeGa(gaId, verzoegerung) {
    spaeter(verzoegerung, () => {
        ladeSkript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`, { ga: 'true' });
    });
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

    if (gaId !== null) {
        bereiteGaVor(gaId);
    }

    let geladen = false;

    const messen = () => {
        const wahl = einwilligung();

        if (! wahl.statistik || geladen) {
            return;
        }

        geladen = true;

        if (gaId !== null) {
            gtag('consent', 'update', erlaubnisse(wahl));
            ladeGa(gaId, verzoegerung);
        }

        if (gtmId !== null) {
            ladeGtm(gtmId, verzoegerung);
        }
    };

    // Entscheidung aus einem frueheren Besuch: sofort messen. Sonst warten,
    // bis der Cookie-Hinweis eine Zustimmung meldet.
    messen();

    if (! geladen) {
        window.addEventListener('cookie-consent-statistics', messen);
    }
}
