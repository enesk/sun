/*
 * Einwilligung — Adapter zum Cookie-Hinweis der Portale (#9)
 * ------------------------------------------------------------------
 * Die EINZIGE Stelle, die weiss, wie das Einwilligungs-Werkzeug der Portale
 * aussieht. Alles andere (resources/js/ads.js, resources/js/analytics.js)
 * fragt nur noch diesen Adapter. Wird der Hinweis ausgetauscht — eigenes
 * Werkzeug, CMP eines Anbieters, TCF-String —, aendert sich diese Datei und
 * sonst nichts.
 *
 * Heutiges Werkzeug: der eigene Cookie-Hinweis (LEGAL-3) in zwei Fassungen,
 * die dieselbe Ablage benutzen —
 *   resources/js/app.js                               (Alpine, Themes default/starter)
 *   themes/sun-v2/js/modules/cookie-consent.js        (ohne Alpine, sun-v2)
 *
 * Ablage: drei Klartext-Cookies, 365 Tage.
 *
 *   cookie_consent_given=1          es gibt eine Entscheidung
 *   cookie_consent_statistics=1|0   Analyse
 *   cookie_consent_marketing=1|0    Werbung  <- das hier zaehlt fuer Werbung
 *
 * Klartext, weil der Browser sie schreibt; damit `request()->cookie()` sie
 * serverseitig sieht, stehen sie in der Ausnahmeliste von EncryptCookies
 * (bootstrap/app.php, #21). Serverseitiges Gegenstueck: App\Ads\Support\AdConsent.
 *
 * Drei Zustaende, nicht zwei:
 *
 *   'granted'  eingewilligt
 *   'denied'   ausdruecklich abgelehnt
 *   'pending'  noch keine Entscheidung (Cookie fehlt)
 *
 * Der Unterschied zwischen 'denied' und 'pending' entscheidet ueber das
 * Layout: bei 'pending' bleibt der reservierte Platz stehen (die Zustimmung
 * kann noch kommen und der Platz darf dann nicht wachsen), bei 'denied' darf
 * er einklappen.
 *
 * Ereignisse
 * ----------
 * Beide Hinweise melden ihre Entscheidung ueber `melde()`. Der Adapter
 * sendet daraufhin an `window`:
 *
 *   consent:marketing     { detail: { granted, state } }
 *   consent:statistics    { detail: { granted, state } }
 *
 * Dazu weiter 'cookie-consent-statistics' — dieses Ereignis ist aelter (#14)
 * und bleibt fuer Fremd-Schnipsel bestehen, die daran haengen koennten.
 * resources/js/analytics.js hoert seit #3 auf 'consent:statistics' und
 * 'consent:marketing' und feuert daraufhin das 'consent update' an gtag.
 */

export const KATEGORIEN = {
    statistics: 'cookie_consent_statistics',
    marketing: 'cookie_consent_marketing',
};

/**
 * Was `melde()` in diesem Seitenaufruf bereits verschickt hat, je Kategorie.
 * Ein `window`-Ereignis erreicht nur Lauscher, die zum Zeitpunkt des Versands
 * stehen; wer sich spaeter registriert, braucht diesen Merker (#33).
 */
const gemeldet = {};

export const GRANTED = 'granted';
export const DENIED = 'denied';
export const PENDING = 'pending';

/** Ereignisname je Kategorie — derselbe Wert wie config('ad-slots.consent.event'). */
export function ereignis(kategorie) {
    return `consent:${kategorie}`;
}

function cookieWert(name) {
    const treffer = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));

    return treffer ? decodeURIComponent(treffer[1]) : null;
}

/** Zustand einer Kategorie: 'granted' | 'denied' | 'pending'. */
export function zustand(kategorie) {
    const wert = cookieWert(KATEGORIEN[kategorie]);

    if (wert === '1') {
        return GRANTED;
    }

    return wert === '0' ? DENIED : PENDING;
}

export function erlaubt(kategorie) {
    return zustand(kategorie) === GRANTED;
}

export function abgelehnt(kategorie) {
    return zustand(kategorie) === DENIED;
}

/**
 * Auf Aenderungen hoeren. Der Rueckruf bekommt den Zustand als Zeichenkette
 * und laeuft bei jeder gemeldeten Entscheidung — auch bei einer Ablehnung,
 * denn die Ausspielung muss darauf reagieren koennen (Einklappen).
 *
 * Liegt die Entscheidung schon vor (frueherer Besuch), laeuft der Rueckruf
 * ohne Umweg sofort: `sofort: false` schaltet das ab.
 */
export function beiEntscheidung(kategorie, rueckruf, { sofort = true } = {}) {
    const jetzt = zustand(kategorie);

    // `sofort: false` heisst "nur Entscheidungen ab jetzt" — aber eine
    // Meldung, die VOR dieser Registrierung lief, ist nicht "schon immer da"
    // gewesen, sie ist nur verpasst. Darum zaehlt sie trotzdem (#33):
    // der Cookie-Hinweis zieht eine Entscheidung aus localStorage nach und
    // meldet sie, bevor die Lauscher stehen; ohne das hier bleibt der schon
    // reservierte Platz bis zum naechsten Seitenaufruf leer.
    if ((sofort || gemeldet[kategorie] !== undefined) && jetzt !== PENDING) {
        rueckruf(jetzt);
    }

    window.addEventListener(ereignis(kategorie), (event) => {
        rueckruf(event.detail?.state ?? zustand(kategorie));
    });
}

/**
 * Die Entscheidung melden. Rufen die beiden Cookie-Hinweise auf, NACHDEM sie
 * die Cookies geschrieben haben — der Adapter liest den Zustand aus den
 * Cookies und nicht aus dem uebergebenen Objekt, damit beide Lesewege
 * dasselbe sagen.
 */
export function melde() {
    Object.keys(KATEGORIEN).forEach((kategorie) => {
        const state = zustand(kategorie);

        if (state === PENDING) {
            return;
        }

        gemeldet[kategorie] = state;

        window.dispatchEvent(new CustomEvent(ereignis(kategorie), {
            detail: { granted: state === GRANTED, state },
        }));
    });

    // Aelteres Ereignis der Messung (#14) — resources/js/analytics.js haengt
    // daran und laedt gtag.js erst hier.
    if (erlaubt('statistics')) {
        window.dispatchEvent(new CustomEvent('cookie-consent-statistics', { detail: { allowed: true } }));
    }
}
