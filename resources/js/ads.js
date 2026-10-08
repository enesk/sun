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
 */

const LAZY = '[data-lazy-ad]';

function initAutoAds() {
    const client = document.querySelector('meta[name="adsense-auto-client"]')?.getAttribute('content');

    if (!client) {
        return;
    }

    (window.adsbygoogle = window.adsbygoogle || []).push({
        google_ad_client: client,
        enable_page_level_ads: true,
        overlays: { bottom: false },
    });
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
    const halter = Array.from(document.querySelectorAll(LAZY)).filter((el) => !el.dataset.adGeladen);

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

export function initAds() {
    initAutoAds();
    initLazyAds();
}
