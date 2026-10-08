/*
 * CSP im echten Browser nachmessen (#28)
 * ---------------------------------------------------------------------------
 * Serverseitig laesst sich nur pruefen, was im HTML steht. Was AdSense, der
 * Tag Manager, Turnstile oder Livewire zur Laufzeit nachschieben, zeigt erst
 * ein Browser. Dieses Skript laedt eine Liste von Portal-Seiten in einem
 * laufenden Chrome (CDP) und schneidet jeden CSP-Verstoss mit:
 *
 *   * `securitypolicyviolation` im Dokument (meldet auch im Report-Only-Modus)
 *   * Browser-Log (Log.entryAdded), faengt zusaetzlich Unterrahmen
 *   * abgebrochene Anfragen mit blockedReason "csp"
 *
 * Voraussetzungen (siehe docs/messungen/csp-browser-messung-*.md):
 *   npm run build
 *   CSP_MODE=report  (eigene .env, php artisan serve)
 *   "Google Chrome" --headless=new --remote-debugging-port=9222 \
 *     --host-resolver-rules="MAP portal.test 127.0.0.1:<port>"
 *
 * Aufruf:
 *   node scripts/csp-messung.mjs --seiten=/tmp/seiten.json --aus=/tmp/csp.json
 *
 * Die Seitenliste ist ein Array aus { name, url, scrollen? }.
 */

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...rest] = a.replace(/^--/, '').split('=');
        return [k, rest.join('=') || true];
    }),
);

const CDP_PORT = args.port || '9222';
const WARTEN = Number(args.warten || 6000);
const seiten = JSON.parse(await (await import('node:fs/promises')).readFile(args.seiten, 'utf8'));

const LAUSCHER = `
(() => {
    window.__cspVerstoesse = [];
    document.addEventListener('securitypolicyviolation', (e) => {
        window.__cspVerstoesse.push({
            direktive: e.effectiveDirective || e.violatedDirective,
            blockiert: e.blockedURI,
            quelle: e.sourceFile || '',
            zeile: e.lineNumber || 0,
            auswirkung: e.disposition,
            dokument: e.documentURI,
            muster: e.sample ? String(e.sample).slice(0, 120) : '',
        });
    });
})();
`;

class Cdp {
    constructor(ws) {
        this.ws = ws;
        this.id = 0;
        this.offen = new Map();
        this.ereignisse = [];
        ws.addEventListener('message', (ev) => {
            const nachricht = JSON.parse(ev.data);
            if (nachricht.id && this.offen.has(nachricht.id)) {
                const { ok, fehler } = this.offen.get(nachricht.id);
                this.offen.delete(nachricht.id);
                nachricht.error ? fehler(new Error(JSON.stringify(nachricht.error))) : ok(nachricht.result);
                return;
            }
            this.ereignisse.push(nachricht);
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

    schliessen() {
        this.ws.close();
    }
}

const schlafen = (ms) => new Promise((ok) => setTimeout(ok, ms));

async function ziel(url) {
    const antwort = await fetch(`http://127.0.0.1:${CDP_PORT}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' });
    return antwort.json();
}

async function zielSchliessen(id) {
    await fetch(`http://127.0.0.1:${CDP_PORT}/json/close/${id}`);
}

async function messen(seite) {
    const ziellage = await ziel('about:blank');
    const cdp = await Cdp.verbinden(ziellage.webSocketDebuggerUrl);

    await cdp.senden('Page.enable');
    await cdp.senden('Runtime.enable');
    await cdp.senden('Log.enable');
    await cdp.senden('Network.enable');
    await cdp.senden('Page.addScriptToEvaluateOnNewDocument', { source: LAUSCHER });

    await cdp.senden('Page.navigate', { url: seite.url });
    await schlafen(WARTEN);

    if (seite.scrollen !== false) {
        await cdp.senden('Runtime.evaluate', {
            expression: 'window.scrollTo(0, document.body.scrollHeight)',
        });
        await schlafen(3000);
    }

    // Dinge, die erst auf Klick anlaufen: der Anfrage-Dialog spricht beim
    // Oeffnen mit der Funnel-API (connect-src).
    if (seite.klick) {
        await cdp.senden('Runtime.evaluate', {
            expression: `document.querySelector(${JSON.stringify(seite.klick)})?.click()`,
        });
        await schlafen(Number(seite.klickWarten || 6000));
    }

    const ausDokument = await cdp.senden('Runtime.evaluate', {
        expression: 'JSON.stringify(window.__cspVerstoesse || [])',
        returnByValue: true,
    });

    const kopf = await cdp.senden('Runtime.evaluate', {
        expression: 'document.documentElement.outerHTML.length',
        returnByValue: true,
    });

    // Gegenprobe: ohne diese Werte ist ein Lauf ohne Verstoesse nichts wert —
    // er koennte auch bedeuten, dass Turnstile, AdSense oder Livewire gar
    // nicht erst geladen haben.
    const pruefung = await cdp.senden('Runtime.evaluate', {
        expression: `JSON.stringify({
            turnstileSkript: document.querySelectorAll('script[src*="challenges.cloudflare.com"]').length,
            turnstileRahmen: document.querySelectorAll('iframe[src*="challenges.cloudflare.com"]').length,
            adsSkripte: document.querySelectorAll('script[src*="googlesyndication.com"]').length,
            adsbygoogleGeladen: !!(window.adsbygoogle && window.adsbygoogle.loaded),
            anzeigenRahmen: document.querySelectorAll('ins.adsbygoogle iframe').length,
            dataLayer: Array.isArray(window.dataLayer) ? window.dataLayer.length : -1,
            gtag: typeof window.gtag,
            livewire: typeof window.Livewire,
            alpine: typeof window.Alpine,
            offeneVorlagen: document.querySelectorAll('template[data-ad-code]').length,
            eingesetzteAnzeigen: document.querySelectorAll('ins.adsbygoogle[data-adsbygoogle-status]').length,
            dialogOffen: document.getElementById('leadDialog') ? !document.getElementById('leadDialog').classList.contains('hidden') : null,
        })`,
        returnByValue: true,
    });

    const anfragen = [
        ...new Set(
            cdp.ereignisse
                .filter((e) => e.method === 'Network.requestWillBeSent')
                .map((e) => {
                    try {
                        return new URL(e.params.request.url).host;
                    } catch (fehler) {
                        return '';
                    }
                })
                .filter((h) => h !== '' && !h.endsWith('.test')),
        ),
    ].sort();

    const protokoll = cdp.ereignisse
        .filter((e) => e.method === 'Log.entryAdded')
        .map((e) => e.params.entry)
        .filter((e) => /Content Security Policy|Refused to/i.test(e.text || ''))
        .map((e) => ({ quelle: e.source, stufe: e.level, text: e.text, url: e.url || '' }));

    const geblockt = cdp.ereignisse
        .filter((e) => e.method === 'Network.loadingFailed' && e.params.blockedReason === 'csp')
        .map((e) => ({ typ: e.params.type, grund: e.params.blockedReason }));

    const konsole = cdp.ereignisse
        .filter((e) => e.method === 'Runtime.consoleAPICalled' && e.params.type === 'error')
        .map((e) => (e.params.args || []).map((a) => a.value || a.description || '').join(' '))
        .filter((t) => t.trim() !== '');

    cdp.schliessen();
    await zielSchliessen(ziellage.id);

    return {
        name: seite.name,
        url: seite.url,
        htmlLaenge: kopf.result.value,
        pruefung: JSON.parse(pruefung.result.value),
        fremdeHosts: anfragen,
        verstoesse: JSON.parse(ausDokument.result.value),
        protokoll,
        geblockt,
        konsolenfehler: konsole,
    };
}

const ergebnisse = [];

for (const seite of seiten) {
    process.stderr.write(`-> ${seite.name} ${seite.url}\n`);
    try {
        const e = await messen(seite);
        ergebnisse.push(e);
        process.stderr.write(`   ${e.verstoesse.length} Verstoesse, ${e.protokoll.length} Log-Zeilen\n`);
    } catch (fehler) {
        ergebnisse.push({ name: seite.name, url: seite.url, fehler: String(fehler) });
        process.stderr.write(`   FEHLER ${fehler}\n`);
    }
}

const alle = ergebnisse.flatMap((e) => (e.verstoesse || []).map((v) => ({ ...v, seite: e.name })));
const nachDirektive = {};

for (const v of alle) {
    const schluessel = `${v.direktive} ${v.blockiert}`;
    nachDirektive[schluessel] = (nachDirektive[schluessel] || 0) + 1;
}

const bericht = { gemessen: new Date().toISOString(), seiten: ergebnisse, zusammenfassung: nachDirektive };

if (args.aus) {
    await (await import('node:fs/promises')).writeFile(args.aus, JSON.stringify(bericht, null, 2));
}

console.log(JSON.stringify(nachDirektive, null, 2));
