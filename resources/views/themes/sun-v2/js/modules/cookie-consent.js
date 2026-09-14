/**
 * Cookie-Hinweis (LEGAL-3) ohne Alpine.
 *
 * Gleiche Ablage wie die Alpine-Komponente `cookieConsent` in
 * resources/js/app.js — localStorage-Schluessel, Ersatz-Cookie und die drei
 * Server-Cookies sind identisch, damit eine Entscheidung auf einem Portal mit
 * altem Theme auch unter sun-v2 gilt und umgekehrt.
 *
 * Markup: partials/sun/cookie-consent.blade.php
 *   [data-cookie-banner]            Hinweisleiste beim Erstbesuch
 *   [data-cookie-modal]             <dialog> mit der Auswahl
 *   [data-cookie-toggle="<name>"]   Schalter (role="switch") fuer statistics/marketing
 *   [data-cookie-action="<name>"]   settings | essential | all | save | close
 * Oeffnen von aussen: window.dispatchEvent(new Event('open-cookie-settings'))
 */

const STORAGE_KEY = 'cookie_consent_preferences';
const LIFETIME_DAYS = 365;
const CATEGORIES = ['statistics', 'marketing'];

function readStored() {
    try {
        const data = localStorage.getItem(STORAGE_KEY);

        if (data) {
            return JSON.parse(data);
        }
    } catch (e) {
        // localStorage gesperrt — Cookie pruefen
    }

    try {
        const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + STORAGE_KEY + '=([^;]*)'));

        if (match) {
            return JSON.parse(decodeURIComponent(match[1]));
        }
    } catch (e) {
        // unlesbar — wie keine Entscheidung behandeln
    }

    return null;
}

function writeCookie(name, value, maxAge) {
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function store(state) {
    const prefs = {
        essential: true,
        statistics: state.statistics,
        marketing: state.marketing,
        timestamp: Date.now(),
        version: 1,
    };
    const maxAge = LIFETIME_DAYS * 86400;

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch (e) {
        writeCookie(STORAGE_KEY, encodeURIComponent(JSON.stringify(prefs)), maxAge);
    }

    writeCookie('cookie_consent_given', '1', maxAge);
    writeCookie('cookie_consent_statistics', state.statistics ? '1' : '0', maxAge);
    writeCookie('cookie_consent_marketing', state.marketing ? '1' : '0', maxAge);
}

function applyConsent(state) {
    if (state.statistics) {
        window.dispatchEvent(new CustomEvent('cookie-consent-statistics', { detail: { allowed: true } }));
    }
}

export function initCookieConsent() {
    const banner = document.querySelector('[data-cookie-banner]');
    const modal = document.querySelector('[data-cookie-modal]');

    if (!banner || !modal) {
        return;
    }

    const stored = readStored();
    const state = {
        statistics: Boolean(stored?.statistics),
        marketing: Boolean(stored?.marketing),
    };

    const renderToggles = () => {
        modal.querySelectorAll('[data-cookie-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-checked', String(state[toggle.dataset.cookieToggle]));
        });
    };

    const finish = () => {
        store(state);
        banner.hidden = true;

        if (modal.open) {
            modal.close();
        }

        applyConsent(state);
    };

    const actions = {
        settings: () => {
            const current = readStored();

            CATEGORIES.forEach((name) => {
                state[name] = Boolean(current?.[name] ?? state[name]);
            });
            renderToggles();
            banner.hidden = true;

            if (!modal.open) {
                modal.showModal();
            }
        },
        essential: () => {
            CATEGORIES.forEach((name) => {
                state[name] = false;
            });
            finish();
        },
        all: () => {
            CATEGORIES.forEach((name) => {
                state[name] = true;
            });
            finish();
        },
        save: finish,
        close: () => modal.close(),
    };

    document.querySelectorAll('[data-cookie-action]').forEach((button) => {
        button.addEventListener('click', () => actions[button.dataset.cookieAction]?.());
    });

    modal.querySelectorAll('[data-cookie-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const name = toggle.dataset.cookieToggle;

            state[name] = !state[name];
            renderToggles();
        });
    });

    // Klick auf den abgedunkelten Hintergrund schliesst die Auswahl
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.close();
        }
    });

    window.addEventListener('open-cookie-settings', actions.settings);

    renderToggles();

    if (stored) {
        applyConsent(state);

        return;
    }

    // Wie im alten Theme: kurz verzoegert, damit zuerst der Inhalt steht
    setTimeout(() => {
        banner.hidden = false;
    }, 300);
}
