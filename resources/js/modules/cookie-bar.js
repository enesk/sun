/*
 * Cookie-Hinweis von spatie/laravel-cookie-consent — ohne Inline-Skript (#21).
 *
 * Die Paketvorlage (resources/views/vendor/cookie-consent/index.blade.php)
 * trug ihr JavaScript inline und baute Cookie-Name, -Laufzeit und -Domain per
 * Blade hinein. Seit #21 stehen diese Werte als data-Attribute am Dialog:
 *
 *   <div class="js-cookie-consent" data-cookie-consent
 *        data-cookie-name="…" data-cookie-lifetime="365"
 *        data-cookie-domain="…" data-cookie-secure data-cookie-same-site="lax">
 *
 * window.laravelCookieConsent bleibt erhalten — die Paketvorlagen und eigene
 * Views rufen consentWithCookies()/hideCookieDialog() darueber auf.
 */

const WERT = '1';

function dialoge() {
    return [...document.getElementsByClassName('js-cookie-consent')];
}

function verstecke() {
    dialoge().forEach((dialog) => {
        dialog.style.display = 'none';
    });
}

function vorhanden(name) {
    return document.cookie.split('; ').indexOf(`${name}=${WERT}`) !== -1;
}

function setze(einstellungen) {
    const ablauf = new Date(Date.now() + einstellungen.lifetime * 24 * 60 * 60 * 1000);

    const teile = [
        `${einstellungen.name}=${WERT}`,
        `expires=${ablauf.toUTCString()}`,
        'path=/',
    ];

    if (einstellungen.domain) {
        teile.push(`domain=${einstellungen.domain}`);
    }

    if (einstellungen.secure) {
        teile.push('secure');
    }

    if (einstellungen.sameSite) {
        teile.push(`samesite=${einstellungen.sameSite}`);
    }

    document.cookie = teile.join(';');
}

export function initCookieBar() {
    const dialog = document.querySelector('[data-cookie-consent]');

    if (!dialog || window.laravelCookieConsent) {
        return;
    }

    const einstellungen = {
        name: dialog.dataset.cookieName || 'laravel_cookie_consent',
        lifetime: Number.parseInt(dialog.dataset.cookieLifetime, 10) || 365,
        domain: dialog.dataset.cookieDomain || '',
        secure: dialog.dataset.cookieSecure !== undefined,
        sameSite: dialog.dataset.cookieSameSite || '',
    };

    const zustimmen = () => {
        setze(einstellungen);
        verstecke();
        window.location.reload();
    };

    if (vorhanden(einstellungen.name)) {
        verstecke();
    }

    [...document.getElementsByClassName('js-cookie-consent-agree')].forEach((knopf) => {
        knopf.addEventListener('click', zustimmen);
    });

    window.laravelCookieConsent = {
        consentWithCookies: zustimmen,
        hideCookieDialog: verstecke,
    };
}
