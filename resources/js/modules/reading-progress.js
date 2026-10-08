/*
 * Ratgeber-Artikel im Default-Theme: Lesefortschritt und aktiver Abschnitt im
 * Inhaltsverzeichnis — ohne Inline-Skript (#21). Beides ist Zugabe und bleibt
 * bei prefers-reduced-motion bzw. fehlendem IntersectionObserver einfach aus.
 * Markup: themes/{default,starter}/views/pages/blog/show.blade.php.
 */

function initFortschritt() {
    const balken = document.getElementById('reading-progress');
    const artikel = document.getElementById('blog-article');

    if (!balken || !artikel) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    balken.classList.add('blog-reading-progress--active');

    const aktualisiere = () => {
        const rand = artikel.getBoundingClientRect();
        const gesamt = artikel.offsetHeight - window.innerHeight;
        const anteil = gesamt <= 0 ? 1 : Math.min(1, Math.max(0, -rand.top / gesamt));

        balken.style.transform = `scaleX(${anteil})`;
    };

    window.addEventListener('scroll', aktualisiere, { passive: true });
    window.addEventListener('resize', aktualisiere, { passive: true });
    aktualisiere();
}

function initInhaltsverzeichnis() {
    const verweise = document.querySelectorAll('.ratgeber-toc__link');

    if (verweise.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    // Artikel und Seitenspalte tragen dieselbe Liste, je Ziel gibt es also
    // mehrere Verweise. Beide werden hervorgehoben; sichtbar ist immer nur einer.
    const gruppen = new Map();

    verweise.forEach((verweis) => {
        const ziel = document.getElementById(decodeURIComponent(verweis.hash.slice(1)));

        if (!ziel) {
            return;
        }

        if (!gruppen.has(ziel)) {
            gruppen.set(ziel, []);
        }

        gruppen.get(ziel).push(verweis);
    });

    const beobachter = new IntersectionObserver((eintraege) => {
        eintraege.forEach((eintrag) => {
            const gruppe = gruppen.get(eintrag.target);

            if (!gruppe || !eintrag.isIntersecting) {
                return;
            }

            verweise.forEach((v) => v.classList.remove('is-active'));
            gruppe.forEach((v) => v.classList.add('is-active'));
        });
    }, { rootMargin: '-88px 0px -70% 0px' });

    gruppen.forEach((_, ziel) => beobachter.observe(ziel));
}

export function initReadingProgress() {
    initFortschritt();
    initInhaltsverzeichnis();
}
