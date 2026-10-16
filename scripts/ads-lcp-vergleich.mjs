/*
 * LCP und CLS mit und ohne Einwilligung vergleichen (#31, §3.3 Aussage 2)
 * ---------------------------------------------------------------------------
 * `scripts/ads-einwilligung-cls.mjs` misst die Verschiebung beim Klick auf den
 * Cookie-Hinweis. Fuer den Pilot fehlt daneben der LCP-Vergleich "mit Werbung
 * gegen ohne Werbung". Auf einem Portal, das Werbung erst nach der
 * Einwilligung laedt, ist das ohne Umschalten der Belegung messbar:
 *
 *   * **ohne Einwilligung**  — kein AdSense-Request, nur die reservierte
 *     Hoehe. Das ist der Zustand "vor der Belegung" fuer LCP und CLS.
 *   * **mit Einwilligung**   — Cookies vor dem Seitenaufbau gesetzt
 *     (`cookie_consent_given`, `cookie_consent_marketing`), also ohne Klick
 *     und ohne den Sprung des Hinweises in der Messung.
 *
 * Gemessen wird je Zustand `--laeufe` mal, berichtet wird der Median. LCP ist
 * der letzte `largest-contentful-paint`-Eintrag, CLS die Summe der
 * `layout-shift`-Eintraege ohne `hadRecentInput` — dieselbe Rechnung wie in
 * den uebrigen Messskripten.
 *
 * Voraussetzung: Chrome headless mit --remote-debugging-port=9222.
 *
 * Aufruf:
 *   node scripts/ads-lcp-vergleich.mjs --url=https://portal/a,https://portal/b \
 *     --breite=360 --laeufe=3 --aus=/tmp/lcp.json
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
const WARTEN = Number(args.warten || 9000);
const LAEUFE = Number(args.laeufe || 3);

const BEOBACHTER = `
(() => {
    window.__cls = 0;
    window.__lcp = 0;
    new PerformanceObserver((liste) => {
        for (const e of liste.getEntries()) {
            if (e.hadRecentInput) { continue; }
            window.__cls += e.value;
        }
    }).observe({ type: 'layout-shift', buffered: true });
    new PerformanceObserver((liste) => {
        for (const e of liste.getEntries()) { window.__lcp = Math.round(e.startTime); }
    }).observe({ type: 'largest-contentful-paint', buffered: true });
})();
`;

const ZUSTAND = `JSON.stringify({
    cls: window.__cls,
    lcp: window.__lcp,
    anker: [...document.querySelectorAll('[data-ad-anchor]')].map((el) => ({
        schluessel: el.dataset.adAnchor,
        geladen: 'adGeladen' in el.dataset,
        hoehe: Math.round(el.getBoundingClientRect().height),
    })),
    eingesetzteAnzeigen: document.querySelectorAll('ins.adsbygoogle[data-adsbygoogle-status]').length,
    adsbygoogleGeladen: !!(window.adsbygoogle && window.adsbygoogle.loaded),
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

async function durchscrollen(cdp) {
    await cdp.senden('Runtime.evaluate', {
        expression: `(async () => {
            const schritt = Math.round(window.innerHeight * 0.8);
            for (let y = 0; y < document.body.scrollHeight; y += schritt) {
                window.scrollTo(0, y);
                await new Promise((ok) => setTimeout(ok, 350));
            }
            window.scrollTo(0, 0);
        })()`,
        awaitPromise: true,
    });
}

async function messen(url, mitEinwilligung) {
    const antwort = await fetch(`http://127.0.0.1:${CDP_PORT}/json/new?${encodeURIComponent('about:blank')}`, { method: 'PUT' });
    const ziel = await antwort.json();
    const cdp = await Cdp.verbinden(ziel.webSocketDebuggerUrl);

    await cdp.senden('Page.enable');
    await cdp.senden('Runtime.enable');
    await cdp.senden('Network.clearBrowserCookies');
    await cdp.senden('Storage.clearDataForOrigin', {
        origin: new URL(url).origin,
        storageTypes: 'cookies,local_storage,indexeddb,cache_storage,websql,service_workers',
    });
    await cdp.senden('Emulation.setDeviceMetricsOverride', {
        width: BREITE, height: HOEHE, deviceScaleFactor: 2, mobile: BREITE < 900,
    });

    if (mitEinwilligung) {
        const domain = new URL(url).hostname;

        for (const [name, value] of [
            ['cookie_consent_given', '1'],
            ['cookie_consent_marketing', '1'],
            ['cookie_consent_statistics', '1'],
        ]) {
            await cdp.senden('Network.setCookie', { name, value, domain, path: '/' });
        }
    }

    await cdp.senden('Page.addScriptToEvaluateOnNewDocument', { source: BEOBACHTER });
    await cdp.senden('Page.navigate', { url });
    await schlafen(WARTEN);
    await durchscrollen(cdp);
    await schlafen(2000);

    const roh = await cdp.senden('Runtime.evaluate', { expression: ZUSTAND, returnByValue: true });

    cdp.ws.close();
    await fetch(`http://127.0.0.1:${CDP_PORT}/json/close/${ziel.id}`);

    return JSON.parse(roh.result.value);
}

const median = (werte) => {
    const sortiert = [...werte].sort((a, b) => a - b);

    return sortiert[Math.floor(sortiert.length / 2)];
};

const bericht = { gemessen: new Date().toISOString(), breite: BREITE, laeufe: LAEUFE, seiten: [] };

for (const url of URLS) {
    const zeile = { url, ohneEinwilligung: [], mitEinwilligung: [] };

    for (let i = 0; i < LAEUFE; i += 1) {
        zeile.ohneEinwilligung.push(await messen(url, false));
        zeile.mitEinwilligung.push(await messen(url, true));
    }

    zeile.median = {
        lcpOhne: median(zeile.ohneEinwilligung.map((e) => e.lcp)),
        lcpMit: median(zeile.mitEinwilligung.map((e) => e.lcp)),
        clsOhne: median(zeile.ohneEinwilligung.map((e) => e.cls)),
        clsMit: median(zeile.mitEinwilligung.map((e) => e.cls)),
    };
    zeile.median.lcpAbweichungProzent = zeile.median.lcpOhne === 0
        ? null
        : Math.round(((zeile.median.lcpMit - zeile.median.lcpOhne) / zeile.median.lcpOhne) * 1000) / 10;

    bericht.seiten.push(zeile);

    console.log(`-> ${url}`);
    console.log(`   LCP ohne ${zeile.median.lcpOhne} ms · mit ${zeile.median.lcpMit} ms · ${zeile.median.lcpAbweichungProzent} %`);
    console.log(`   CLS ohne ${zeile.median.clsOhne.toFixed(4)} · mit ${zeile.median.clsMit.toFixed(4)}`);
}

if (args.aus) {
    await (await import('node:fs/promises')).writeFile(args.aus, JSON.stringify(bericht, null, 2));
}
