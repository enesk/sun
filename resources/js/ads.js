/*
 * Werbeplaetze — ohne Inline-Skript (#21)
 * ------------------------------------------------------------------
 * Gehoert zu resources/views/components/ad-slot.blade.php und uebernimmt die
 * beiden Bloecke, die dort vorher inline standen:
 *
 *  1. Auto Ads: der seitenweite Opt-out fuer den unteren Anker-Banner. Die
 *     Publisher-Kennung steht in <meta name="adsense-auto-client" content="ca-pub-…">.
 *  2. Nachladen unterhalb des Falzes: [data-lazy-ad] traegt den Code in einem
 *     <template data-ad-code> und bekommt ihn erst beim Heranscrollen eingesetzt.
 *
 * Der eingetragene Code selbst ist Admin-Inhalt aus der Tabelle ad_slots und
 * bringt eigene <script>-Tags mit. Die bekommen in der Blade-Komponente das
 * Nonce des Requests (App\Services\Security\CspNonce), damit die Policy ohne
 * 'unsafe-inline' auskommt.
 *
 * Dazu kommt der Katalog v2 (Ticket #5, initSlotAds unten): die Anker von
 * <x-sun.ad-slot>. Beide Generationen laufen bis SUN-AD-011 parallel und
 * stoeren sich nicht — v1 haengt an [data-lazy-ad], v2 an [data-ad-anchor].
 *
 * Einwilligung (Ticket #9, initConsentGate unten)
 * ------------------------------------------------------------------
 * Ein Anker, dessen Werbemittel eine Einwilligung braucht, traegt
 * `data-ad-consent="marketing"`; sein Fragment bleibt dann im <template>
 * liegen, und der AdSense-Loader steht in einem <template data-ad-head> im
 * Kopf der Seite. Beides ist inert — ohne Einwilligung geht kein Request an
 * Google. Kommt die Einwilligung im laufenden Besuch, wird nachgezogen:
 * erst die Head-Skripte, dann die Anker. Reserviert ist der Platz schon,
 * darum springt dabei nichts (CLS).
 *
 * Wird ausdruecklich abgelehnt, klappt der Platz ein, sofern der Anker
 * `data-ad-on-denied="collapse"` traegt (config('ad-slots.consent.on_denied')).
 *
 * Woran die Entscheidung haengt, weiss nur resources/js/consent-adapter.js —
 * ein Wechsel des Einwilligungs-Werkzeugs betrifft diese Datei nicht.
 *
 * Impression-/Viewability-Zaehlen (Ticket #12, initImpressionTracking unten)
 * ------------------------------------------------------------------
 * Ein Container mit `data-ad-placement` wird nach dem Einsetzen des
 * Werbemittels beobachtet: war er zu `data-ad-track`-Anteil (50 %) eine
 * Sekunde zusammenhaengend im Viewport, zaehlt das als Impression. Alle
 * Impressionen eines Seitenaufrufs gehen gebuendelt per sendBeacon an
 * POST /_ads/impressions — ohne Cookie, ohne Kennung und darum ohne
 * Einwilligung. Abgeschaltet (config('ad-slots.impression_tracking') = false)
 * fehlen die Attribute und hier passiert nichts.
 */

import { beiEntscheidung, DENIED, erlaubt, GRANTED } from './consent-adapter';

const LAZY = '[data-lazy-ad]';

/*
 * Katalog v2 (#5): Anker der Komponente <x-sun.ad-slot>.
 * Bewusst `data-ad-anchor` und nicht `data-ad-slot` — letzteres ist das
 * AdSense-Attribut am <ins>-Block und waere nach dem Einsetzen doppelt.
 */
const SLOT = '[data-ad-anchor]';
const FRAGMENT = 'template[data-ad-fragment]';
const PUSH = 'ins[data-ads-push]';
const VORLAUF = '0px 0px 200px 0px';

/* Zurueckgehaltene Head-Skripte (#9), z. B. der AdSense-Loader. */
const HEAD = 'template[data-ad-head]';

/* Plaetze, die auf die Einwilligung in Werbung warten (#9) — beide Generationen. */
const WARTEND = '[data-ad-consent]';

/*
 * Impression-/Viewability-Zaehlen (#12). Ein Container traegt
 * `data-ad-placement` und `data-ad-track="<anteil>:<dauer>"` nur, wenn
 * config('ad-slots.impression_tracking') an ist und der Anker belegt ist —
 * fehlen die Attribute, passiert hier gar nichts.
 */
const IMPRESSION_ENDPOINT = '/_ads/impressions';
const GEZAEHLT = '[data-ad-placement]';

/* Grenze mobil/Desktop: der lg-Haltepunkt des Themes, wie in AdDevice. */
const DESKTOP_AB = '(min-width: 1024px)';

/* Verzoegerung, mit der gesammelte Meldungen rausgehen (ms). */
const SENDEVERZUG = 3000;

/*
 * Auto Ads stehen nur im Dokument, wenn die Einwilligung beim Seitenaufbau
 * schon vorlag — die Komponente <x-sun.auto-ads> rendert sonst gar nicht
 * (#9). Die Pruefung hier ist die zweite Verteidigungslinie; nachgezogen
 * werden Auto Ads bewusst NICHT im laufenden Besuch: sie platzieren ausserhalb
 * jeder Reservierung und wuerden das Layout nach dem Klick verschieben.
 * Beim naechsten Seitenaufruf laufen sie.
 */
function initAutoAds() {
    const client = document.querySelector('meta[name="adsense-auto-client"]')?.getAttribute('content');

    if (!client || !erlaubt('marketing')) {
        return;
    }

    (window.adsbygoogle = window.adsbygoogle || []).push({
        google_ad_client: client,
        enable_page_level_ads: true,
        overlays: { bottom: false },
    });
}

/**
 * Wartet dieser Platz noch auf die Einwilligung? Dann bleibt sein Werbemittel
 * im <template> — auch wenn er im Blick ist (#9). Gilt fuer beide
 * Generationen: der Anker aus dem Katalog v2 und der Container des
 * Altsystems tragen dasselbe `data-ad-consent`.
 */
function wartetAufEinwilligung(halter) {
    return 'adConsent' in halter.dataset && !erlaubt(halter.dataset.adConsent);
}

/**
 * Ein aus <template> geklontes <script> fuehrt der Browser nicht zuverlaessig
 * aus — und das Nonce-Attribut kann beim Klonen verloren gehen. Darum wird
 * jedes Skript neu angelegt: Attribute uebernehmen, Nonce ausdruecklich setzen
 * (sonst blockiert die Policy den AdSense-Aufruf), Inhalt uebertragen.
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

/**
 * Der Code steckt in einem <template>: so laedt der Browser vorher nichts.
 * Beim Einsetzen bekommen eingebettete Rahmen loading="lazy" — Skripte, die
 * AdSense selbst nachlaedt, sind davon nicht betroffen.
 */
function setzeEin(halter) {
    const vorlage = halter.querySelector('template[data-ad-code]');

    if (!vorlage) {
        return;
    }

    const teil = vorlage.content.cloneNode(true);

    teil.querySelectorAll('iframe').forEach((rahmen) => {
        rahmen.setAttribute('loading', 'lazy');
    });

    teil.querySelectorAll('script').forEach((skript) => {
        skript.replaceWith(neuesSkript(skript));
    });

    vorlage.parentNode.replaceChild(teil, vorlage);
}

function initLazyAds() {
    const halter = Array.from(document.querySelectorAll(LAZY)).filter(
        (el) => !el.dataset.adGeladen && !wartetAufEinwilligung(el),
    );

    if (halter.length === 0) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        halter.forEach((el) => {
            el.dataset.adGeladen = '1';
            setzeEin(el);
        });

        return;
    }

    const beobachter = new IntersectionObserver((eintraege, selbst) => {
        eintraege.forEach((eintrag) => {
            if (!eintrag.isIntersecting) {
                return;
            }

            selbst.unobserve(eintrag.target);
            eintrag.target.dataset.adGeladen = '1';
            setzeEin(eintrag.target);
        });
    }, { rootMargin: '0px 0px 200px 0px' });

    halter.forEach((el) => beobachter.observe(el));
}

/* ------------------------------------------------------------------
 * Katalog v2 (Ticket #5)
 *
 * Jeder belegte Anker steht als reservierter Container im HTML, das
 * Werbemittel liegt darin in einem <template>. Eingesetzt wird es erst,
 * wenn der Container sichtbar in den Viewport kommt (200 px Vorlauf).
 *
 * Das erledigt drei Dinge auf einmal:
 *
 *  1. Nachladen unterhalb des Falzes — nichts laedt im Voraus.
 *  2. Geraetetrennung: der Container der falschen Geraeteklasse ist per
 *     Tailwind-Haltepunkt display:none und loest darum nie aus. Mobil und
 *     Desktop koennen verschiedene Belegungen haben, ohne dass beide laden.
 *  3. `adsbygoogle.push({})` fuellt immer den naechsten unbelegten
 *     <ins>-Block in Dokumentreihenfolge. Weil nur der sichtbare Block
 *     ueberhaupt im DOM landet, kann der Aufruf nicht auf einem
 *     ausgeblendeten Block landen.
 * ------------------------------------------------------------------ */

function istSichtbar(el) {
    return el.getClientRects().length > 0;
}

/**
 * AdSense anstossen. Ohne Argument fuellt push() den naechsten unbelegten
 * <ins>-Block — darum erst einsetzen, dann je eingesetztem Block einmal
 * pushen. Fehlt der Loader (Einwilligung noch offen), ist adsbygoogle ein
 * Array und der Aufruf wird spaeter nachgeholt; einen Konsolenfehler gibt
 * es dadurch nicht.
 */
function stosseAn(bereich) {
    bereich.querySelectorAll(PUSH).forEach((block) => {
        if (block.dataset.adsPushed) {
            return;
        }

        block.dataset.adsPushed = '1';

        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch (e) {
            // AdSense nicht erreichbar oder blockiert — der Platz bleibt leer.
        }
    });
}

function setzeSlotEin(halter) {
    if (halter.dataset.adGeladen) {
        return;
    }

    const vorlage = halter.querySelector(FRAGMENT);

    if (!vorlage) {
        return;
    }

    halter.dataset.adGeladen = '1';
    const ziel = vorlage.parentNode;
    ziel.replaceChild(vorlage.content.cloneNode(true), vorlage);
    stosseAn(ziel);

    // Gezaehlt wird erst ab hier (#12): vorher stand da nur die
    // Reservierung. Ein Platz, der auf die Einwilligung wartet oder
    // eingeklappt ist, kommt nie hierher und zaehlt darum auch nicht.
    beobachteImpression(halter);
}

function initSlotAds() {
    const halter = Array.from(document.querySelectorAll(SLOT)).filter(
        (el) => !el.dataset.adGeladen && el.querySelector(FRAGMENT) && !wartetAufEinwilligung(el),
    );

    if (halter.length === 0) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        halter.filter(istSichtbar).forEach(setzeSlotEin);

        return;
    }

    // Oberhalb des Falzes (Katalog-sort 10, Seitenspalte oben) nicht auf den
    // Beobachter warten — der Platz ist ohnehin im Blick.
    halter.filter((el) => 'adEager' in el.dataset && istSichtbar(el)).forEach(setzeSlotEin);

    const offen = halter.filter((el) => !el.dataset.adGeladen);

    if (offen.length === 0) {
        return;
    }

    const beobachter = new IntersectionObserver((eintraege, selbst) => {
        eintraege.forEach((eintrag) => {
            if (!eintrag.isIntersecting) {
                return;
            }

            selbst.unobserve(eintrag.target);
            setzeSlotEin(eintrag.target);
        });
    }, { rootMargin: VORLAUF });

    offen.forEach((el) => beobachter.observe(el));
}

/* ------------------------------------------------------------------
 * Impression-/Viewability-Zaehlen (Ticket #12)
 *
 * Gezaehlt wird wie bei AdSense und nach der MRC-Vorgabe fuer Display:
 * mindestens 50 % der Containerflaeche fuer eine zusammenhaengende Sekunde
 * im Viewport. Schwelle und Dauer stehen am Container (`data-ad-track`),
 * nicht hier — sie kommen aus config/ad-slots.php.
 *
 * Gemeldet wird gebuendelt und nicht je Anker: alle Impressionen eines
 * Seitenaufrufs gehen in EINEM navigator.sendBeacon an /_ads/impressions,
 * spaetestens beim Verlassen der Seite.
 *
 * Uebertragen werden Katalogschluessel, Belegungs-Id und Geraeteklasse —
 * kein Cookie, keine Kennung, nichts Personenbezogenes. Darum braucht das
 * Beacon keine Einwilligung und laeuft auch fuer eine Direktbuchung ueber den
 * HtmlProvider, fuer die es kein AdSense-Reporting gibt.
 * ------------------------------------------------------------------ */

/* Laufende Haltefristen je Container (Sekunde noch nicht um). */
const fristen = new WeakMap();

/* Schon gemeldete Container dieses Seitenaufrufs — je Platz genau eine Impression. */
const gemeldet = new Set();

const warteschlange = [];

let impressionBeobachter = null;
let sendefrist = null;

/**
 * Das Geraet, auf dem jemand wirklich sitzt — nicht die Geraetewahl des
 * Ankers. Eine Belegung mit `all` rendert EINEN Container fuer beide; ohne
 * diese Messung waere der Vergleich Mobil/Desktop fuer sie blind.
 */
function geraet() {
    return window.matchMedia(DESKTOP_AB).matches ? 'desktop' : 'mobile';
}

/** `data-ad-track="0.5:1000"` → { anteil, dauer }. */
function messvorgabe(halter) {
    const [anteil, dauer] = (halter.dataset.adTrack || '').split(':');

    return {
        anteil: Math.min(1, Math.max(0.01, Number.parseFloat(anteil) || 0.5)),
        dauer: Math.max(0, Number.parseInt(dauer, 10) || 1000),
    };
}

function sende() {
    sendefrist = null;

    if (warteschlange.length === 0) {
        return;
    }

    const rumpf = JSON.stringify({ slots: warteschlange.splice(0, warteschlange.length) });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(IMPRESSION_ENDPOINT, new Blob([rumpf], { type: 'application/json' }));

        return;
    }

    fetch(IMPRESSION_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: rumpf,
        keepalive: true,
    }).catch(() => {});
}

/**
 * Nicht sofort senden: ein Seitenaufruf bringt meist mehrere Anker in den
 * Blick, und die sollen in einem Beacon landen. Beim Verlassen der Seite geht
 * der Rest ohnehin raus (initImpressionTracking).
 */
function planeSenden() {
    if (sendefrist !== null) {
        return;
    }

    sendefrist = window.setTimeout(sende, SENDEVERZUG);
}

function zaehle(halter) {
    const slot = halter.dataset.adAnchor;
    const placement = Number.parseInt(halter.dataset.adPlacement, 10);

    if (!slot || !Number.isInteger(placement)) {
        return;
    }

    const schluessel = `${slot}:${placement}`;

    if (gemeldet.has(schluessel)) {
        return;
    }

    gemeldet.add(schluessel);
    warteschlange.push({ slot, placement, device: geraet() });
    planeSenden();
}

function beobachter() {
    if (impressionBeobachter !== null) {
        return impressionBeobachter;
    }

    // Feste Stufen statt der Schwelle des Containers: ein Beobachter fuer
    // alle Anker, die Schwelle wird im Rueckruf verglichen. Sonst braeuchte
    // jeder Anker mit eigener Vorgabe seinen eigenen Beobachter.
    impressionBeobachter = new IntersectionObserver((eintraege) => {
        eintraege.forEach((eintrag) => {
            const halter = eintrag.target;
            const { anteil, dauer } = messvorgabe(halter);

            if (eintrag.intersectionRatio >= anteil) {
                if (fristen.has(halter)) {
                    return;
                }

                fristen.set(halter, window.setTimeout(() => {
                    fristen.delete(halter);
                    impressionBeobachter.unobserve(halter);
                    zaehle(halter);
                }, dauer));

                return;
            }

            // Wieder aus dem Blick, bevor die Sekunde um war: nicht gezaehlt.
            const frist = fristen.get(halter);

            if (frist !== undefined) {
                window.clearTimeout(frist);
                fristen.delete(halter);
            }
        });
    }, { threshold: [0, 0.25, 0.5, 0.75, 1] });

    return impressionBeobachter;
}

/**
 * Einen eingesetzten Container ueberwachen. Ohne `data-ad-placement` (Zaehlen
 * abgeschaltet, Anker unbelegt oder Editor-Modus) passiert nichts, ebenso
 * ohne IntersectionObserver — Sichtbarkeit laesst sich dann nicht messen, und
 * eine ungemessene Impression ist schlechter als keine.
 */
function beobachteImpression(halter) {
    if (!halter.matches(GEZAEHLT) || !('IntersectionObserver' in window)) {
        return;
    }

    if (document.body?.dataset.adEditor !== undefined) {
        return;
    }

    beobachter().observe(halter);
}

/**
 * Beim Seitenwechsel raus mit dem Rest. `visibilitychange` ist der
 * verlaessliche Weg (auch beim Wechsel in einen anderen Tab auf Mobilgeraeten),
 * `pagehide` faengt den Rueckwaerts-Cache ab.
 */
function initImpressionTracking() {
    if (window.__adImpressionsBound) {
        return;
    }

    window.__adImpressionsBound = true;

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            sende();
        }
    });

    window.addEventListener('pagehide', sende);
}

/* ------------------------------------------------------------------
 * Einwilligung (Ticket #9)
 * ------------------------------------------------------------------ */

/**
 * Die zurueckgehaltenen Head-Skripte ausgeben. Aus dem <template> geklont
 * fuehrt der Browser ein <script> nicht aus — darum durch neuesSkript(), das
 * auch das Nonce mitnimmt.
 */
function ladeHeadSkripte() {
    document.querySelectorAll(HEAD).forEach((vorlage) => {
        vorlage.content.querySelectorAll('script').forEach((skript) => {
            document.head.appendChild(neuesSkript(skript));
        });

        vorlage.remove();
    });
}

/**
 * Abgelehnt: reservierte Plaetze einklappen, soweit so konfiguriert.
 *
 * Klasse UND Inline-Stil: die Klasse ist der Weg in sun-v2 (dort setzt sie
 * schon der Server), der Inline-Stil der fuer die Themes default/starter, die
 * sie nicht kennen — und er gewinnt auch gegen ein `md:block` aus der
 * Geraetesichtbarkeit des Altsystems.
 */
function klappeEin() {
    document.querySelectorAll(WARTEND).forEach((halter) => {
        if (halter.dataset.adOnDenied !== 'collapse') {
            return;
        }

        halter.classList.add('ad-slot--collapsed');
        halter.style.display = 'none';
    });
}

/**
 * Nachziehen ohne Reload. Nur fuer Entscheidungen WAEHREND des Besuchs: lag
 * die Einwilligung beim Seitenaufbau vor, hat der Server das Fragment und den
 * Loader ohnehin ohne <template> ausgegeben (`sofort: false`).
 */
function initConsentGate() {
    if (document.querySelector(WARTEND) === null && document.querySelector(HEAD) === null) {
        return;
    }

    beiEntscheidung('marketing', (zustand) => {
        if (zustand === GRANTED) {
            ladeHeadSkripte();
            initLazyAds();
            initSlotAds();

            return;
        }

        if (zustand === DENIED) {
            klappeEin();
        }
    }, { sofort: false });
}

export function initAds() {
    initAutoAds();
    initImpressionTracking();
    initLazyAds();
    initSlotAds();
    initConsentGate();
}
