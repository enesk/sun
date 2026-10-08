/*
 * Trust Bar: Zahlen zaehlen beim Heranscrollen hoch (VR-4) — ohne
 * Inline-Skript (#21). Markup: themes/{default,starter}/views/components/
 * trust-bar.blade.php, je Karte .trust-card mit data-target, optional
 * data-is-rating und data-stagger-delay.
 */

const DAUER = 1200;

function zaehle(karte) {
    const el = karte.querySelector('.trust-bar-value');

    if (!el) {
        return;
    }

    const istBewertung = karte.dataset.isRating === 'true';
    const ziel = Number.parseFloat(karte.dataset.target);
    const versatz = Number.parseInt(karte.dataset.staggerDelay, 10) || 0;

    if (Number.isNaN(ziel)) {
        return;
    }

    window.setTimeout(() => {
        const start = performance.now();

        const schritt = (jetzt) => {
            const fortschritt = Math.min((jetzt - start) / DAUER, 1);
            const wert = ziel * (1 - Math.pow(1 - fortschritt, 3));

            el.textContent = istBewertung
                ? wert.toFixed(1).replace('.', ',')
                : Math.round(wert).toLocaleString('de-DE');

            if (fortschritt < 1) {
                requestAnimationFrame(schritt);
            }
        };

        el.textContent = istBewertung ? '0,0' : '0';
        requestAnimationFrame(schritt);
    }, versatz);
}

export function initTrustBar() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const karten = document.querySelectorAll('.trust-card');

    if (karten.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const beobachter = new IntersectionObserver((eintraege) => {
        eintraege.forEach((eintrag) => {
            if (!eintrag.isIntersecting) {
                return;
            }

            beobachter.unobserve(eintrag.target);
            zaehle(eintrag.target);
        });
    }, { threshold: 0.3 });

    karten.forEach((karte) => beobachter.observe(karte));
}
