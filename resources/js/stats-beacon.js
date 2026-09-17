/*
 * Betriebsstatistik (#15): meldet Telefon-, Website- und Anfrage-Klicks an
 * POST /stats/beacon. Zugeordnet wird ueber das naechste Element mit
 * data-stats-company (Profilseite, Ergebniskarte), die Quelle steht in
 * data-stats-source. Erkannt werden tel:-Links, [data-open-lead] und alles
 * mit data-stats-event (z.B. der Website-Knopf). Keine Cookies, keine IDs –
 * der Server bildet aus der Sitzung einen taeglich wechselnden Hash.
 */

const ENDPOINT = '/stats/beacon';

function eventFor(target) {
    const explicit = target.closest('[data-stats-event]');
    if (explicit) {
        return explicit.getAttribute('data-stats-event');
    }

    if (target.closest('a[href^="tel:"]')) {
        return 'phone_click';
    }

    if (target.closest('[data-open-lead]')) {
        return 'quote_request';
    }

    return null;
}

export function sendStatsBeacon(companyId, event, source = null) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const payload = JSON.stringify({ company_id: Number(companyId), event, source, _token: token });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([payload], { type: 'application/json' }));
        return;
    }

    fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: payload,
        keepalive: true,
    }).catch(() => {});
}

export function initStatsBeacon() {
    if (window.__statsBeaconBound) {
        return;
    }
    window.__statsBeaconBound = true;

    const sent = new Set();

    document.addEventListener('click', (e) => {
        if (!(e.target instanceof Element)) {
            return;
        }

        const scope = e.target.closest('[data-stats-company]');
        if (!scope) {
            return;
        }

        const event = eventFor(e.target);
        if (!event) {
            return;
        }

        const companyId = scope.getAttribute('data-stats-company');
        const key = `${companyId}:${event}`;
        if (sent.has(key)) {
            return;
        }
        sent.add(key);

        sendStatsBeacon(companyId, event, scope.getAttribute('data-stats-source'));
    }, { capture: true });
}
