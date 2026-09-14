/*
 * Anfrage-Dialog "Angebot anfragen" auf dem Firmenprofil (Vorlage sun-v2--profil.html).
 *
 * Headless an die oeffentliche Funnel-Runtime-API des Leadsystems angebunden:
 * Dialog oeffnet -> POST /sessions, "Weiter" -> PATCH /answers, "Angebot
 * anfragen" -> POST /submit. Die Sitzung rueckt nur vor, wenn die Pruefung des
 * Servers besteht; 422-Fehler landen direkt unter den Feldern.
 */

const TITLES = { 1: 'Was brauchst du?', 2: 'Wo und wann?', 3: 'Wie erreichen wir dich?', done: 'Danke!' };
const TEL_HINT = 'Der Betrieb ruft dich zurück – darum brauchen wir die Nummer.';
const SESSION_KEY = 'wl_session';

class ApiError extends Error {
    constructor(status, message, validation = null) {
        super(message);
        this.status = status;
        this.validation = validation;
    }
}

function answersFromStep(form, step, companyKey) {
    const data = new FormData(form);

    if (step === 1) {
        // Firmenprofil, auf dem die Anfrage gestellt wird: kein Funnel-Feld,
        // das Leadsystem legt unbekannte Schluessel unveraendert als Antwort ab
        return { leistung: data.get('leistung'), beschreibung: data.get('beschreibung'), firmenprofil: companyKey };
    }

    if (step === 2) {
        return { plz: data.get('plz'), wann: data.get('wann'), objekt: data.get('objekt') };
    }

    const answers = {
        firmenprofil: companyKey,
        name: data.get('name'),
        telefon: data.get('tel'),
        erreichbar: data.getAll('erreichbar[]'),
        email: data.get('email') || null,
    };

    // Einwilligung: true oder weglassen
    if (data.get('weitere') === '1') {
        answers.weitere_betriebe = true;
    }

    return answers;
}

export function initLeadDialog() {
    const dialog = document.getElementById('leadDialog');
    const form = document.getElementById('leadForm');

    if (!dialog || !form) {
        return;
    }

    const apiBase = (dialog.dataset.leadApi || '').replace(/\/$/, '');
    const token = dialog.dataset.leadToken || '';
    const company = dialog.dataset.company || '';
    // Funnel-Feld firmenprofil ist Text: Profil-Link des Betriebs, nie vom Nutzer
    let companyKey = null;
    try {
        const profile = JSON.parse(dialog.dataset.companyKey || 'null');
        companyKey = profile ? String(profile.url || profile.slug || '') : null;
    } catch {
        companyKey = null;
    }
    const steps = [...form.querySelectorAll('[data-step]')];
    const body = form.querySelector('[data-lead-body]');
    const nextButton = form.querySelector('[data-next]');
    const prevButton = form.querySelector('[data-prev]');
    const errorBox = form.querySelector('[data-lead-error]');
    const telHint = document.getElementById('telHint');

    let current = 1;
    let sessionToken = sessionStorage.getItem(SESSION_KEY);
    let busy = false;

    async function api(method, path, payload) {
        let response;

        try {
            response = await fetch(`${apiBase}${path}`, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: payload ? JSON.stringify(payload) : undefined,
            });
        } catch {
            throw new ApiError(0, 'Keine Verbindung. Bitte prüfe dein Internet und versuch es noch einmal.');
        }

        const json = await response.json().catch(() => ({}));

        if (response.status === 422) {
            throw new ApiError(422, json.message || '', json.errors || {});
        }

        if (!response.ok) {
            throw new ApiError(response.status, json.message || '');
        }

        return json.data;
    }

    // Kampagnenparameter der Seitenadresse gehen beim Sitzungsstart mit
    function utmParams() {
        const params = new URLSearchParams(window.location.search);
        const utm = {};
        params.forEach((value, key) => {
            if (key.startsWith('utm_')) {
                utm[key] = value;
            }
        });

        return Object.keys(utm).length ? utm : null;
    }

    async function startSession() {
        const query = new URLSearchParams(utmParams() || {}).toString();
        const state = await api('POST', `/funnels/${token}/sessions${query ? `?${query}` : ''}`,
            sessionToken ? { session_token: sessionToken } : {});
        sessionToken = state.session_token;
        sessionStorage.setItem(SESSION_KEY, sessionToken);

        return state;
    }

    function setBusy(value) {
        busy = value;
        nextButton.disabled = value;
        nextButton.setAttribute('aria-busy', String(value));
    }

    function clearErrors() {
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('.border-red-500').forEach((el) => el.classList.remove('border-red-500'));
        telHint.textContent = TEL_HINT;
        telHint.classList.remove('text-red-600');
        telHint.classList.add('text-zinc-500');
    }

    function showGeneralError(error) {
        const messages = {
            429: 'Gerade kommen sehr viele Anfragen an. Bitte warte einen Moment und versuch es dann noch einmal.',
            403: 'Anfragen sind von dieser Seite aus gerade nicht möglich. Bitte ruf den Betrieb direkt an.',
        };
        errorBox.textContent = messages[error.status] || error.message || 'Das hat nicht geklappt. Bitte versuch es noch einmal.';
        errorBox.classList.remove('hidden');
    }

    function showValidation(errors) {
        let first = null;

        Object.entries(errors).forEach(([key, list]) => {
            const field = key.split('.')[0];
            const message = Array.isArray(list) ? list[0] : String(list);
            const input = form.querySelector(`[name="${field === 'telefon' ? 'tel' : field}"]`)
                || form.querySelector(`[name="${field}[]"]`);

            if (field === 'telefon') {
                telHint.textContent = message;
                telHint.classList.add('text-red-600');
                telHint.classList.remove('text-zinc-500');
            } else {
                const target = form.querySelector(`[data-error-for="${field}"]`);
                if (target) {
                    target.textContent = message;
                    target.classList.remove('hidden');
                } else {
                    errorBox.textContent = message;
                    errorBox.classList.remove('hidden');
                }
            }

            if (input && input.classList.contains('input')) {
                input.classList.add('border-red-500');
            }

            first = first || input;
        });

        first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function show(step) {
        current = step;
        steps.forEach((el) => el.classList.toggle('hidden', el.dataset.step !== String(step)));
        body.scrollTop = 0;
        document.getElementById('leadTitle').textContent = TITLES[step];
        document.getElementById('leadStep').textContent = step === 'done' ? `Anfrage an ${company}` : `Schritt ${step} von 3`;
        prevButton.classList.toggle('hidden', step === 1 || step === 'done');
        nextButton.textContent = step === 3 ? 'Angebot anfragen' : (step === 'done' ? 'Schließen' : 'Weiter');
        form.querySelector('[data-privacy]').classList.toggle('hidden', step === 'done');
    }

    async function open() {
        dialog.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        clearErrors();
        show(1);

        if (!token || !apiBase) {
            showGeneralError({ status: 0, message: 'Anfragen sind gerade nicht möglich. Bitte ruf den Betrieb direkt an.' });

            return;
        }

        try {
            setBusy(true);
            await startSession();
        } catch (error) {
            // Abgelaufene Sitzung aus dem Speicher: einmal frisch starten
            if (sessionToken && error.status !== 429) {
                sessionToken = null;
                sessionStorage.removeItem(SESSION_KEY);
                try {
                    await startSession();
                } catch (retry) {
                    showGeneralError(retry);
                }
            } else {
                showGeneralError(error);
            }
        } finally {
            setBusy(false);
        }
    }

    function close() {
        dialog.classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function next() {
        if (busy) {
            return;
        }

        if (current === 'done') {
            close();

            return;
        }

        clearErrors();

        if (current === 3) {
            const name = form.querySelector('#name');
            const tel = form.querySelector('#tel');
            const missing = [name, tel].filter((input) => !input.value.trim());

            if (missing.length) {
                missing.forEach((input) => input.classList.add('border-red-500'));
                telHint.textContent = 'Gib Name und Telefonnummer an, damit der Betrieb dich erreichen kann.';
                telHint.classList.add('text-red-600');
                telHint.classList.remove('text-zinc-500');

                return;
            }
        }

        if (!sessionToken) {
            showGeneralError({ status: 0, message: 'Anfragen sind gerade nicht möglich. Bitte ruf den Betrieb direkt an.' });

            return;
        }

        setBusy(true);

        try {
            if (current === 3) {
                await api('POST', `/funnels/${token}/sessions/${sessionToken}/submit`, {
                    answers: answersFromStep(form, 3, companyKey),
                    website: form.querySelector('[name="website"]')?.value ?? '',
                });
                sessionStorage.removeItem(SESSION_KEY);
                sessionToken = null;
                show('done');

                return;
            }

            await api('PATCH', `/funnels/${token}/sessions/${sessionToken}/answers`, {
                answers: answersFromStep(form, current, companyKey),
            });
            show(current + 1);
        } catch (error) {
            if (error.status === 422) {
                showValidation(error.validation || {});
            } else {
                showGeneralError(error);
            }
        } finally {
            setBusy(false);
        }
    }

    document.querySelectorAll('[data-open-lead]').forEach((button) => button.addEventListener('click', open));
    dialog.querySelectorAll('[data-close-lead]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.classList.contains('hidden')) {
            close();
        }
    });
    prevButton.addEventListener('click', () => {
        clearErrors();
        show(current - 1);
    });
    nextButton.addEventListener('click', next);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        next();
    });
}
