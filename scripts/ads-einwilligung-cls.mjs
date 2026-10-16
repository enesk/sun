/*
 * CLS der Werbeplaetze bei einer Einwilligung IM laufenden Besuch (#25)
 * ---------------------------------------------------------------------------
 * Die Zusage aus docs/ad-slots.md 9.1 lautet: der Platz ist vor der
 * Einwilligung in Katalog-Hoehe reserviert, und wenn die Einwilligung mitten
 * im Besuch kommt, springt nichts. Lighthouse kann das nicht messen — es
 * klickt den Cookie-Hinweis nicht. Darum hier derselbe Messwert (CLS) wie in
 * docs/ad-slots.md 7.4, aber in drei Abschnitten:
 *
 *   1. laden und bis zum Seitenende scrollen            -> CLS "vor Klick"
 *   2. [data-cookie-action="all"] klicken, warten        -> CLS "nach Klick"
 *   3. noch einmal durch die Seite scrollen              -> CLS "gesamt"
 *
 * Gezaehlt wird wie in der Metrik: layout-shift ohne `hadRecentInput`. Dazu
 * meldet das Skript je Verschiebung die betroffenen Elemente, damit sichtbar
 * ist, ob ein Werbeplatz-Container dabei war.
 *
 * Voraussetzungen wie beim Mitschnitt (docs/ad-slots.md 9.5): npm run build,
 * php artisan serve, Chrome headless mit --remote-debugging-port=9222 und
 * --host-resolver-rules.
 *
 * Aufruf:
 *   node scripts/ads-einwilligung-cls.mjs --url=http://portal.test/ --aus=/tmp/cls.json
 *
 * Nachgezogene Entscheidung (#33)
 * ---------------------------------------------------------------------------
 * `--vorentschieden=alles|statistik|werbung` legt die Entscheidung VOR dem
 * Seitenaufbau in localStorage, laesst die Cookies aber leer — der Fall, dass
 * das Einwilligungs-Cookie abgelaufen oder geraeumt ist. Der Server rendert
 * dann den Platzhalter, und der Cookie-Hinweis zieht die Cookies nach und
 * meldet. In diesem Modus wird nicht geklickt (der Hinweis erscheint gar
 * nicht); Beleg ist `offeneFragmente: 0` und `geladen: true` an den Ankern im
 * selben Seitenaufruf.
 */

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...rest] = a.replace(/^--/, '').split('=');
        return [k, rest.join('=') || true];
    }),
);

const CDP_PORT = args.port || '9222';
const URLS = String(args.url || '').split(',').filter((u) => u !== '');
const BREITE = Number(args.breite || 360);
const HOEHE = Number(args.hoehe || 800);
const WARTEN = Number(args.warten || 8000);
const KLICK = args.klick || '[data-cookie-action="all"]';

const VORENTSCHIEDEN = String(args.vorentschieden || '');
const VOR_STATISTIK = VORENTSCHIEDEN === 'alles' || VORENTSCHIEDEN === 'statistik';
const VOR_WERBUNG = VORENTSCHIEDEN === 'alles' || VORENTSCHIEDEN === 'werbung';

/*
 * Entscheidung in localStorage, Cookies leer (#33). Laeuft als
 * addScriptToEvaluateOnNewDocument, also vor allen Seitenskripten und mit dem
 * Origin der Seite. Schluessel und Form wie in
 * themes/sun-v2/js/modules/cookie-consent.js.
 */
const VORBELEGUNG = `
(() => {
    try {
        localStorage.setItem('cookie_consent_preferences', JSON.stringify({
            essential: true,
            statistics: ${VOR_STATISTIK},
            marketing: ${VOR_WERBUNG},
            timestamp: Date.now(),
            version: 1,
        }));
    } catch (e) {
        // localStorage gesperrt — dann ist der Modus nicht messbar
    }
})();
`;

const BEOBACHTER = `
(() => {
    window.__cls = 0;
    window.__verschiebungen = [];
    new PerformanceObserver((liste) => {
        for (const e of liste.getEntries()) {
            if (e.hadRecentInput) { continue; }
            window.__cls += e.value;
            window.__verschiebungen.push({
                wert: e.value,
                zeit: Math.round(e.startTime),
                quellen: (e.sources || []).map((q) => {
                    const el = q.node;
                    if (!el || !el.tagName) { return '(entfernt)'; }
                    const anker = el.closest ? el.closest('[data-ad-anchor]') : null;
                    return [
                        el.tagName.toLowerCase(),
                        el.className ? '.' + String(el.className).trim().split(/\\s+/).slice(0, 3).join('.') : '',
                        anker ? ' @' + anker.dataset.adAnchor : '',
                    ].join('');
                }),
            });
        }
    }).observe({ type: 'layout-shift', buffered: true });
})();
`;

const ZUSTAND = `JSON.stringify({
    cls: window.__cls,
    verschiebungen: window.__verschiebungen,
    anker: [...document.querySelectorAll('[data-ad-anchor]')].map((el) => ({
        schluessel: el.dataset.adAnchor,
        geraet: el.dataset.adDevice,
        wartetAufEinwilligung: 'adConsent' in el.dataset,
        geladen: 'adGeladen' in el.dataset,
        eingeklappt: el.classList.contains('ad-slot--collapsed'),
        sichtbar: el.getClientRects().length > 0,
        hoehe: Math.round(el.getBoundingClientRect().height),
    })),
    offeneHeadVorlagen: document.querySelectorAll('template[data-ad-head]').length,
    offeneFragmente: document.querySelectorAll('template[data-ad-fragment]').length,
    adsbygoogleGeladen: !!(window.adsbygoogle && window.adsbygoogle.loaded),
    eingesetzteAnzeigen: document.querySelectorAll('ins.adsbygoogle[data-adsbygoogle-status]').length,
})`;

class Cdp {
    constructor(ws) {
        this.ws = ws;
        this.id = 0;
        this.offen = new Map();
        ws.addEventListener('message', (ev) => {
            const nachricht = JSON.parse(ev.data);
            if (nachricht.id && this.offen.has(nachricht.id)) {
                const { ok, fehler } = this.offen.get(nachricht.id);
                this.offen.delete(nachricht.id);
                nachricht.error ? fehler(new Error(JSON.stringify(nachricht.error))) : ok(nachricht.result);
            }
        });
    }

    static async verbinden(url) {
        const ws = new WebSocket(url);
        await new Promise((ok, fehler) => {
            ws.addEventListener('open', ok, { once: true });
            ws.addEventListener('error', fehler, { once: true });
        });
        return new Cdp(ws);
    }

    senden(method, params = {}) {
        const id = ++this.id;
        this.ws.send(JSON.stringify({ id, method, params }));
        return new Promise((ok, fehler) => this.offen.set(id, { ok, fehler }));
    }
}

const schlafen = (ms) => new Promise((ok) => setTimeout(ok, ms));

async function lesen(cdp) {
    const a = await cdp.senden('Runtime.evaluate', { expression: ZUSTAND, returnByValue: true });
    return JSON.parse(a.result.value);
}

/** Langsam durch die Seite, damit die Anker ihren Beobachter wirklich ausloesen. */
async function durchscrollen(cdp) {
    await cdp.senden('Runtime.evaluate', {
        expression: `(async () => {
            const schritt = window.innerHeight * 0.75;
            for (let y = 0; y < document.body.scrollHeight; y += schritt) {
                window.scrollTo(0, y);
                await new Promise((ok) => setTimeout(ok, 400));
            }
            window.scrollTo(0, 0);
        })()`,
        awaitPromise: true,
    });
    await schlafen(2000);
}

async function messen(url) {
    const antwort = await fetch(`http://127.0.0.1:${CDP_PORT}/json/new?${encodeURIComponent('about:blank')}`, { method: 'PUT' });
    const ziel = await antwort.json();
    const cdp = await Cdp.verbinden(ziel.webSocketDebuggerUrl);

    await cdp.senden('Page.enable');
    await cdp.senden('Runtime.enable');
    // Cookies UND localStorage: der Cookie-Hinweis stellt eine frueher
    // getroffene Entscheidung aus localStorage wieder her und schreibt die
    // Cookies daraus neu (#25) — sonst misst der Lauf eine Seite, die die
    // Einwilligung schon hat.
    await cdp.senden('Network.clearBrowserCookies');
    await cdp.senden('Storage.clearDataForOrigin', {
        origin: new URL(url).origin,
        storageTypes: 'cookies,local_storage,indexeddb,cache_storage,websql,service_workers',
    });
    await cdp.senden('Emulation.setDeviceMetricsOverride', {
        width: BREITE, height: HOEHE, deviceScaleFactor: 2, mobile: true,
    });
    await cdp.senden('Page.addScriptToEvaluateOnNewDocument', { source: BEOBACHTER });

    if (VORENTSCHIEDEN !== '') {
        await cdp.senden('Page.addScriptToEvaluateOnNewDocument', { source: VORBELEGUNG });
    }

    await cdp.senden('Page.navigate', { url });
    await schlafen(WARTEN);
    await durchscrollen(cdp);

    // Der entscheidende Fall: der wartende Anker steht IM BLICK, wenn die
    // Einwilligung kommt. CLS zaehlt nur Verschiebungen im sichtbaren
    // Bereich — ein Nachzug weit unterhalb des Falzes wuerde die Metrik gar
    // nicht erreichen und waere als Beleg wertlos.
    if (args.ankerInBlick !== 'nein') {
        await cdp.senden('Runtime.evaluate', {
            expression: `(() => {
                const el = document.querySelector('[data-ad-consent]');
                if (!el) { return false; }
                el.scrollIntoView({ block: 'center' });
                return true;
            })()`,
            returnByValue: true,
        });
        await schlafen(2000);
    }

    const vorKlick = await lesen(cdp);

    // Mit nachgezogener Entscheidung gibt es keinen Hinweis zum Klicken: die
    // Seite muss die Anzeigen von allein eingesetzt haben.
    if (VORENTSCHIEDEN !== '') {
        await durchscrollen(cdp);
        const gesamtOhneKlick = await lesen(cdp);

        cdp.ws.close();
        await fetch(`http://127.0.0.1:${CDP_PORT}/json/close/${ziel.id}`);

        return { url, vorentschieden: VORENTSCHIEDEN, geklickt: false, vorKlick, nachKlick: vorKlick, gesamt: gesamtOhneKlick };
    }

    const klick = await cdp.senden('Runtime.evaluate', {
        expression: `!!document.querySelector(${JSON.stringify(KLICK)})?.click() || !!document.querySelector(${JSON.stringify(KLICK)})`,
        returnByValue: true,
    });

    await schlafen(WARTEN);
    const nachKlick = await lesen(cdp);

    await durchscrollen(cdp);
    const gesamt = await lesen(cdp);

    cdp.ws.close();
    await fetch(`http://127.0.0.1:${CDP_PORT}/json/close/${ziel.id}`);

    return { url, geklickt: klick.result.value === true, vorKlick, nachKlick, gesamt };
}

const ergebnisse = [];

for (const url of URLS) {
    process.stderr.write(`-> ${url}\n`);
    const e = await messen(url);
    ergebnisse.push(e);
    process.stderr.write(`   CLS vor Klick ${e.vorKlick.cls.toFixed(4)} · nach Klick ${e.nachKlick.cls.toFixed(4)} · gesamt ${e.gesamt.cls.toFixed(4)}\n`);
}

const bericht = { gemessen: new Date().toISOString(), breite: BREITE, seiten: ergebnisse };

if (args.aus) {
    await (await import('node:fs/promises')).writeFile(args.aus, JSON.stringify(bericht, null, 2));
}

console.log(JSON.stringify(ergebnisse.map((e) => ({
    url: e.url,
    geklickt: e.geklickt,
    clsVorKlick: e.vorKlick.cls,
    clsNachKlick: e.nachKlick.cls,
    clsGesamt: e.gesamt.cls,
    // #33: im Modus --vorentschieden der eigentliche Beleg
    offeneFragmenteAmEnde: e.gesamt.offeneFragmente,
    offeneHeadVorlagenAmEnde: e.gesamt.offeneHeadVorlagen,
    geladeneAnker: e.gesamt.anker.filter((a) => a.geladen).length,
    wartendeAnker: e.gesamt.anker.filter((a) => a.wartetAufEinwilligung && ! a.geladen).length,
})), null, 2));
