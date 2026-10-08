/*
 * Kontaktklicks auf dem Firmenprofil (STAT-1) — ohne Inline-Skript (#21).
 *
 * Stand vorher in themes/{default,starter}/views/pages/companies/show.blade.php
 * und braucht jetzt nur noch ein Attribut am Profil-Container:
 *
 *   <div data-contact-tracking="{{ $company->id }}">
 *
 * Gemeldet wird je Art einmal: tel: -> phone, mailto: -> email, externer Link
 * im neuen Tab -> website. Dazu POST /tracking/contact-click; Telefon- und
 * Website-Klicks gehen zusaetzlich als Ereignis an die Betriebsstatistik (#15).
 */

import { sendStatsBeacon } from '../stats-beacon';

const ENDPOINT = '/tracking/contact-click';

function artVon(link) {
    const href = link.getAttribute('href') || '';

    if (href.startsWith('tel:')) {
        return 'phone';
    }

    if (href.startsWith('mailto:')) {
        return 'email';
    }

    if (/^https?:\/\//.test(href) && link.target === '_blank') {
        return 'website';
    }

    return null;
}

function melde(companyId, art, token) {
    const nutzlast = JSON.stringify({ company_id: companyId, contact_type: art, _token: token });

    if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([nutzlast], { type: 'application/json' }));

        return;
    }

    fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: nutzlast,
        keepalive: true,
    }).catch(() => {});
}

export function initContactTracking() {
    const bereich = document.querySelector('[data-contact-tracking]');

    if (!bereich || window.__kontaktTrackingGebunden) {
        return;
    }

    window.__kontaktTrackingGebunden = true;

    const companyId = Number(bereich.dataset.contactTracking);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const gemeldet = new Set();

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (!link) {
            return;
        }

        const art = artVon(link);

        if (art === null || gemeldet.has(art)) {
            return;
        }

        gemeldet.add(art);

        // Betriebsstatistik (#15): nur Telefon und Website sind dort Ereignisse.
        const ereignis = art === 'phone' ? 'phone_click' : (art === 'website' ? 'website_click' : null);

        if (ereignis !== null) {
            sendStatsBeacon(companyId, ereignis, 'profile');
        }

        melde(companyId, art, token);
    });
}
