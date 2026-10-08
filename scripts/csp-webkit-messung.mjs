/*
 * CSP in WebKit nachmessen (#29)
 * ---------------------------------------------------------------------------
 * Gegenstueck zu scripts/csp-messung.mjs (Chrome/CDP). Safari selbst laesst
 * sich hier nicht fernsteuern — `safaridriver` verlangt "Allow remote
 * automation" in den Safari-Einstellungen, was nur ein Mensch am Rechner
 * freigibt (nachgeprueft 08.10.2026). Gemessen wird deshalb dieselbe Engine
 * ohne Safari-Huelle: der WebKit-Build von Playwright, installiert in einem
 * Temp-Ordner ausserhalb des Projekts. Das Projekt bekommt dadurch keine neue
 * Abhaengigkeit — der Pfad kommt per --playwright herein.
 *
 * Gemessen wird dasselbe wie in Chrome: CSP-Verstoesse ueber das
 * `securitypolicyviolation`-Ereignis im Dokument, dazu Konsolenfehler und
 * fehlgeschlagene Anfragen, plus die Gegenprobe (laedt AdSense wirklich,
 * tragen die geklonten Skripte ein Nonce, bleibt eine Vorlage offen).
 *
 * Voraussetzungen:
 *   npm run build; Portal unter der Mess-Domain erreichbar (/etc/hosts),
 *   CSP_MODE=enforce — alles stellt scripts/csp-safari-sichtpruefung.sh her.
 *
 * Aufruf:
 *   node scripts/csp-webkit-messung.mjs --seiten=/tmp/seiten.json \
 *     --aus=/tmp/csp-webkit.json --playwright=/tmp/webkit-messung
 *   node scripts/csp-webkit-messung.mjs --selbsttest   # Gegenprobe der Messung
 *
 * --selbsttest beantwortet die Frage, die ein Lauf mit 0 Verstoessen offen
 * laesst: faengt der Lauscher in WebKit ueberhaupt etwas? Dafuer serviert das
 * Skript selbst eine Seite mit strenger Policy und absichtlichem Verstoss
 * (Inline-Skript ohne Nonce, Bild von fremder Herkunft) und erwartet
 * mindestens einen Treffer. Exit 3, wenn nichts ankommt — dann ist jedes
 * "0 Verstoesse" eines echten Laufs wertlos.
 *
 * Die Seitenliste ist ein Array aus { name, url, scrollen?, klick? } —
 * gleiches Format wie bei scripts/csp-messung.mjs.
 */

import { createServer } from 'node:http';
import { readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...rest] = a.replace(/^--/, '').split('=');
        return [k, rest.join('=') || true];
    }),
);

const WARTEN = Number(args.warten || 8000);
const WURZEL = path.resolve(args.playwright || '/tmp/webkit-messung');
const SELBSTTEST_PORT = Number(args.selbsttestPort || 8131);
const seiten = args.selbsttest
    ? [{ name: 'Selbsttest (erwartet Verstoesse)', url: `http://127.0.0.1:${SELBSTTEST_PORT}/`, scrollen: false }]
    : JSON.parse(await readFile(args.seiten, 'utf8'));

process.env.PLAYWRIGHT_BROWSERS_PATH ||= path.join(WURZEL, 'browsers');

const fordere = createRequire(path.join(WURZEL, 'nichts.cjs'));
const { webkit } = fordere('playwright');

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

const GEGENPROBE = `JSON.stringify({
    turnstileSkript: document.querySelectorAll('script[src*="challenges.cloudflare.com"]').length,
    adsSkripte: document.querySelectorAll('script[src*="googlesyndication.com"]').length,
    adsbygoogleGeladen: !!(window.adsbygoogle && window.adsbygoogle.loaded),
    anzeigenRahmen: document.querySelectorAll('ins.adsbygoogle iframe').length,
    eingesetzteAnzeigen: document.querySelectorAll('ins.adsbygoogle[data-adsbygoogle-status]').length,
    offeneVorlagen: document.querySelectorAll('template[data-ad-code]').length,
    werbeSkripte: [...document.querySelectorAll('script[src*="googlesyndication"]')]
        .map((s) => ((s.nonce || s.getAttribute('nonce')) ? 'mit Nonce' : 'OHNE NONCE')),
    dataLayer: Array.isArray(window.dataLayer) ? window.dataLayer.length : -1,
    livewire: typeof window.Livewire,
    alpine: typeof window.Alpine,
})`;

const schlafen = (ms) => new Promise((ok) => setTimeout(ok, ms));

// Gegenprobe der Messung selbst: strenge Policy, zwei gewollte Verstoesse.
let selbsttestServer = null;
if (args.selbsttest) {
    selbsttestServer = createServer((anfrage, antwort) => {
        antwort.writeHead(200, {
            'content-type': 'text/html; charset=utf-8',
            'content-security-policy': "default-src 'self'; script-src 'self'; img-src 'self'",
        });
        antwort.end(
            '<!doctype html><title>Selbsttest</title>'
            + '<img src="https://www.gstatic.com/generate_204" alt="">'
            + '<script>window.__gelaufen = true;</scr' + 'ipt>'
            + '<p>Selbsttest</p>',
        );
    });
    await new Promise((ok) => selbsttestServer.listen(SELBSTTEST_PORT, '127.0.0.1', ok));
    process.stderr.write(`Selbsttest-Server auf 127.0.0.1:${SELBSTTEST_PORT}\n`);
}

const browser = await webkit.launch();
const ergebnisse = [];

for (const seite of seiten) {
    process.stderr.write(`-> ${seite.name} ${seite.url}\n`);
    const kontext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    await kontext.addInitScript(LAUSCHER);

    const konsole = [];
    const protokoll = [];
    const fehlgeschlagen = [];
    const hosts = new Set();
    const blatt = await kontext.newPage();

    blatt.on('console', (m) => {
        const text = m.text();
        if (m.type() === 'error') {
            konsole.push(text);
        }
        if (/Content Security Policy|Refused to|blocked by/i.test(text)) {
            protokoll.push({ stufe: m.type(), text });
        }
    });
    blatt.on('pageerror', (f) => konsole.push(String(f)));
    blatt.on('request', (r) => {
        try {
            const host = new URL(r.url()).host;
            if (host && !host.endsWith('.test')) {
                hosts.add(host);
            }
        } catch (fehler) {
            /* data:-URLs und aehnliches */
        }
    });
    blatt.on('requestfailed', (r) => fehlgeschlagen.push({ url: r.url().slice(0, 160), grund: r.failure()?.errorText || '' }));

    try {
        await blatt.goto(seite.url, { waitUntil: 'load', timeout: 60000 });
        await schlafen(WARTEN);

        if (seite.scrollen !== false) {
            await blatt.evaluate('window.scrollTo(0, document.body.scrollHeight)');
            await schlafen(4000);
        }
        if (seite.klick) {
            await blatt.evaluate(`document.querySelector(${JSON.stringify(seite.klick)})?.click()`);
            await schlafen(Number(seite.klickWarten || 6000));
        }

        const verstoesse = JSON.parse(await blatt.evaluate('JSON.stringify(window.__cspVerstoesse || [])'));
        const pruefung = JSON.parse(await blatt.evaluate(GEGENPROBE));
        const htmlLaenge = await blatt.evaluate('document.documentElement.outerHTML.length');

        ergebnisse.push({
            name: seite.name,
            url: seite.url,
            htmlLaenge,
            pruefung,
            fremdeHosts: [...hosts].sort(),
            verstoesse,
            protokoll,
            fehlgeschlagen,
            konsolenfehler: konsole,
        });
        process.stderr.write(`   ${verstoesse.length} Verstoesse, ${protokoll.length} Log-Zeilen\n`);
    } catch (fehler) {
        ergebnisse.push({ name: seite.name, url: seite.url, fehler: String(fehler) });
        process.stderr.write(`   FEHLER ${fehler}\n`);
    }

    await kontext.close();
}

const version = browser.version();
await browser.close();
if (selbsttestServer) {
    await new Promise((ok) => selbsttestServer.close(ok));
}

const zusammenfassung = {};
for (const e of ergebnisse) {
    for (const v of e.verstoesse || []) {
        const schluessel = `${v.direktive} ${v.blockiert}`;
        zusammenfassung[schluessel] = (zusammenfassung[schluessel] || 0) + 1;
    }
}

const bericht = { gemessen: new Date().toISOString(), engine: `WebKit ${version} (Playwright)`, seiten: ergebnisse, zusammenfassung };

if (args.aus) {
    await writeFile(args.aus, JSON.stringify(bericht, null, 2));
}

console.log(JSON.stringify({ engine: bericht.engine, zusammenfassung }, null, 2));

if (args.selbsttest) {
    const treffer = Object.keys(zusammenfassung).length;
    const protokollzeilen = ergebnisse.reduce((summe, e) => summe + (e.protokoll || []).length, 0);
    if (treffer === 0 && protokollzeilen === 0) {
        process.stderr.write('Selbsttest fehlgeschlagen: WebKit meldet keinen der gewollten Verstoesse.\n');
        process.exit(3);
    }
    process.stderr.write(`Selbsttest in Ordnung: ${treffer} Verstoss-Arten, ${protokollzeilen} Log-Zeilen.\n`);
}
